<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CallContentPlace;
use App\Enums\CallContentType;
use App\Enums\CallType;
use App\Enums\CustomFormType;
use App\Enums\CustomPageBaseType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\CustomPages\CustomForm;
use App\Models\CustomPages\CustomFormValue;
use App\Models\CustomPages\CustomPageDetail;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Support\CustomPages\CustomPageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * カスタムページの公開側(API・パス解決・呼び出しコンテンツ)と、公開側の URL にかかわる管理画面の入力。
 */
class CustomPagePublicTest extends TestCase
{
    use RefreshDatabase;

    private function createType(string $name, CustomPageBaseType $baseType, string $label = 'レシピ', int $sortOrder = 0): CustomPageType
    {
        $type = CustomPageType::factory()->create(['name' => $name, 'label' => $label, 'base_type' => $baseType, 'sort_order' => $sortOrder]);
        (new CustomPageSchema)->create($type);

        return $type;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createArticleEntry(CustomPageType $type, array $attributes = []): CustomPageEntry
    {
        return CustomPageEntry::queryFor($type)->create([
            'title' => '肉じゃが',
            'content' => '<p>本文</p>',
            'approval' => ArticleApprovalStatus::Published,
            'publication_start_datetime' => now()->subDay(),
            ...$attributes,
        ]);
    }

    // --- API ---

    public function test_custom_page_types_api_returns_types_in_sort_order(): void
    {
        $this->createType('shop', CustomPageBaseType::SinglePage, '店舗', 1);
        $this->createType('recipe', CustomPageBaseType::Article, 'レシピ', 0);

        $response = $this->getJson(route('api.custom-page-types.index'));

        $response->assertOk();
        $response->assertExactJson(['data' => [
            ['name' => 'recipe', 'label' => 'レシピ', 'base_type' => 'article', 'path' => '/recipes'],
            ['name' => 'shop', 'label' => '店舗', 'base_type' => 'single_page', 'path' => '/shops'],
        ]]);
    }

    public function test_custom_pages_api_returns_only_published_article_entries_newest_first(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $older = $this->createArticleEntry($type, ['title' => '古い', 'slug' => 'old', 'publication_start_datetime' => '2026-09-01 00:00:00']);
        $newer = $this->createArticleEntry($type, ['title' => '新しい', 'publication_start_datetime' => '2026-09-20 00:00:00']);
        $this->createArticleEntry($type, ['title' => '下書き', 'approval' => ArticleApprovalStatus::Draft]);
        $this->createArticleEntry($type, ['title' => '予約', 'publication_start_datetime' => '2026-10-02 00:00:00']);

        $response = $this->getJson(route('api.custom-pages.index', 'recipe'));

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.path', '/recipes/'.$newer->id);
        $response->assertJsonPath('data.1.path', '/recipes/old');
        $response->assertJsonPath('data.0.published_at', '2026-09-20T00:00:00+09:00');
        $response->assertJsonPath('data.0.custom_page_type.base_type', 'article');
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_custom_pages_api_returns_single_page_entries_in_sort_order_and_404_for_unknown_type(): void
    {
        $type = $this->createType('shop', CustomPageBaseType::SinglePage, '店舗');
        $second = CustomPageEntry::queryFor($type)->create(['title' => '支店', 'slug' => 'shiten', 'short_sentences' => '概要', 'sort_order' => 1]);
        $first = CustomPageEntry::queryFor($type)->create(['title' => '本店', 'slug' => 'honten', 'short_sentences' => '概要', 'sort_order' => 0]);

        $response = $this->getJson(route('api.custom-pages.index', 'shop'));

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.short_sentences', '概要');
        $response->assertJsonPath('data.0.header_image_url', null);

        $this->getJson(route('api.custom-pages.index', 'unknown'))->assertNotFound();
        $type->delete();
        $this->getJson(route('api.custom-pages.index', 'shop'))->assertNotFound();
    }

    // --- パス解決 ---

    public function test_resolve_returns_custom_page_list_for_type_path(): void
    {
        $this->createType('recipe', CustomPageBaseType::Article);

        $response = $this->getJson(route('api.resolve', ['path' => '/recipes']));

        $response->assertOk();
        $response->assertJsonPath('type', 'custom_page_list');
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('custom_page_type.name', 'recipe');
        $response->assertJsonPath('call_contents', []);
    }

    public function test_resolve_returns_article_type_entry_with_custom_fields(): void
    {
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $text = CustomForm::queryFor($type)->create(['parts_name' => '材料', 'customs_form_type' => CustomFormType::Text, 'sort_order' => 0]);
        $checkbox = CustomForm::queryFor($type)->create(['parts_name' => 'ジャンル', 'customs_form_type' => CustomFormType::Checkbox, 'customs_form_options' => ['和食', '洋食'], 'sort_order' => 1]);
        $entry = $this->createArticleEntry($type, ['slug' => 'nikujaga']);
        CustomFormValue::queryFor($type)->create(['customs_recipe_form_id' => $text->id, 'user_make_recipe_id' => $entry->id, 'value' => 'じゃがいも']);
        CustomFormValue::queryFor($type)->create(['customs_recipe_form_id' => $checkbox->id, 'user_make_recipe_id' => $entry->id, 'value' => ['和食']]);

        $response = $this->getJson(route('api.resolve', ['path' => '/recipes/nikujaga']));

        $response->assertOk();
        $response->assertJsonPath('type', 'custom_page');
        $response->assertJsonPath('data.title', '肉じゃが');
        $response->assertJsonPath('data.content', '<p>本文</p>');
        $response->assertJsonPath('data.path', '/recipes/nikujaga');
        $response->assertJsonPath('data.custom_fields', [
            ['name' => '材料', 'type' => 'text', 'value' => 'じゃがいも'],
            ['name' => 'ジャンル', 'type' => 'checkbox', 'value' => ['和食']],
        ]);
        $response->assertJsonPath('custom_page_type.path', '/recipes');
    }

    public function test_resolve_finds_article_type_entry_without_slug_by_id(): void
    {
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $entry = $this->createArticleEntry($type);
        $withSlug = $this->createArticleEntry($type, ['slug' => 'curry']);

        $this->getJson(route('api.resolve', ['path' => '/recipes/'.$entry->id]))->assertOk()->assertJsonPath('data.id', $entry->id);
        // スラッグがあるページは id では開けない
        $this->getJson(route('api.resolve', ['path' => '/recipes/'.$withSlug->id]))->assertNotFound();
    }

    public function test_resolve_returns_single_page_type_entry_with_details(): void
    {
        $type = $this->createType('shop', CustomPageBaseType::SinglePage, '店舗');
        $entry = CustomPageEntry::queryFor($type)->create(['title' => '本店', 'slug' => 'honten', 'short_sentences' => '駅前']);
        CustomPageDetail::queryFor($type)->create(['user_make_shop_id' => $entry->id, 'sub_title' => '営業時間', 'sort_order' => 1]);
        CustomPageDetail::queryFor($type)->create(['user_make_shop_id' => $entry->id, 'sub_title' => 'アクセス', 'sort_order' => 0]);

        $response = $this->getJson(route('api.resolve', ['path' => '/shops/honten']));

        $response->assertOk();
        $response->assertJsonPath('data.short_sentences', '駅前');
        $this->assertSame(['アクセス', '営業時間'], array_column($response->json('data.details'), 'sub_title'));
        $response->assertJsonPath('data.custom_fields', []);
    }

    public function test_resolve_returns_404_for_unpublished_or_deeper_custom_page_paths(): void
    {
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $this->createArticleEntry($type, ['slug' => 'draft', 'approval' => ArticleApprovalStatus::Draft]);
        $this->createArticleEntry($type, ['slug' => 'nikujaga']);

        $this->getJson(route('api.resolve', ['path' => '/recipes/draft']))->assertNotFound();
        $this->getJson(route('api.resolve', ['path' => '/recipes/nikujaga/more']))->assertNotFound();
        $this->getJson(route('api.resolve', ['path' => '/recipes/missing']))->assertNotFound();
    }

    // --- 公開側の URL にかかわる管理画面の入力 ---

    public function test_type_cannot_use_reserved_or_taken_url_prefix(): void
    {
        $this->actingAsSuperAdmin();
        Article::factory()->create(['parent_path' => 'events', 'slug' => 'summer']);

        // /articles は chococo の記事一覧が使っている
        $this->post(route('admin.custom-page-types.store'), ['name' => 'article', 'label' => '記事', 'base_type' => 1])
            ->assertSessionHasErrors('name');
        // /events/summer の記事がある
        $this->post(route('admin.custom-page-types.store'), ['name' => 'event', 'label' => 'イベント', 'base_type' => 1])
            ->assertSessionHasErrors('name');
    }

    public function test_articles_and_single_pages_cannot_use_custom_page_url_prefix(): void
    {
        $this->actingAsSuperAdmin();
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $type->delete();

        // 論理削除済みの種類の URL の先頭も使えない
        $this->post(route('admin.articles.store'), ['title' => '記事', 'content' => '<p>本文</p>', 'parent_path' => 'recipes/2026', 'publication_start_datetime' => now()->format('Y-m-d H:i')])
            ->assertSessionHasErrors('parent_path');
        $this->post(route('admin.single-pages.store'), ['title' => '固定', 'short_sentences' => '概要', 'slug' => 'recipes', 'publication_start_datetime' => now()->format('Y-m-d H:i')])
            ->assertSessionHasErrors('slug');
        // 親パスの 2 階層目以降やスラッグ(親パスあり)では使える
        $this->post(route('admin.single-pages.store'), ['title' => '固定', 'short_sentences' => '概要', 'parent_path' => 'info', 'slug' => 'recipes', 'publication_start_datetime' => now()->format('Y-m-d H:i')])
            ->assertSessionHasNoErrors();
    }

    public function test_entry_slug_must_be_unique_within_type_and_images_are_saved(): void
    {
        $this->actingAsSuperAdmin();
        Storage::fake('public');
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $this->createArticleEntry($type, ['slug' => 'nikujaga']);
        $payload = ['title' => '肉じゃが2', 'content' => '<p>本文</p>', 'approval' => 'published', 'publication_start_datetime' => '2026-10-01 10:00'];

        $this->post(route('admin.custom-pages.entries.store', $type), [...$payload, 'slug' => 'nikujaga'])->assertSessionHasErrors('slug');
        $this->post(route('admin.custom-pages.entries.store', $type), [...$payload, 'slug' => '123'])->assertSessionHasErrors('slug');

        $this->post(route('admin.custom-pages.entries.store', $type), [
            ...$payload,
            'slug' => 'nikujaga-2',
            'thumbnail' => UploadedFile::fake()->image('wide.jpg', 2400, 1260),
        ])->assertSessionHasNoErrors();

        $entry = CustomPageEntry::queryFor($type)->where('slug', 'nikujaga-2')->firstOrFail();
        $this->assertStringStartsWith(Article::THUMBNAIL_DIRECTORY.'/', $entry->thumbnail);
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring(Storage::disk('public')->get($entry->thumbnail)), 0, 2));
    }

    // --- 呼び出しコンテンツ ---

    public function test_call_contents_can_show_custom_pages(): void
    {
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $entry = $this->createArticleEntry($type, ['slug' => 'nikujaga']);
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Custom,
            'model_name' => 'Recipe',
            'table_name' => 'user_make_recipes',
        ]);
        $this->assertSame(CallType::CUSTOM_ARTICLE, $relation->matrixModelName());
        CallContent::factory()->create([
            'call_type' => CallType::Archive,
            'place' => CallContentPlace::Top,
            'view_count' => 3,
            'content_model_relation_id' => $relation->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonPath('data.0.call_type', 'archive');
        $response->assertJsonPath('data.0.user_make_recipes.0.id', $entry->id);
        $response->assertJsonPath('data.0.user_make_recipes.0.path', '/recipes/nikujaga');
        $response->assertJsonPath('data.0.user_make_recipes.0.custom_page_type.base_type', 'article');
    }

    public function test_site_setting_validates_call_contents_for_custom_pages_by_base_type(): void
    {
        $this->actingAsSuperAdmin();
        $this->createType('shop', CustomPageBaseType::SinglePage, '店舗');
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Custom,
            'model_name' => 'Shop',
            'table_name' => 'user_make_shops',
        ]);
        $row = ['call_name' => '店舗', 'content_model_relation_id' => $relation->id, 'view_count' => 5, 'place' => CallContentPlace::Top->value];

        // 固定ページ型はリンクリストを選べるが、アーカイブは選べない
        $this->post(route('admin.site-settings.store'), ['site_title' => 'テスト', 'call_contents' => [[...$row, 'call_type' => CallType::Archive->value]]])
            ->assertSessionHasErrors('call_contents.0.content_model_relation_id');
        $this->post(route('admin.site-settings.store'), ['site_title' => 'テスト', 'call_contents' => [[...$row, 'call_type' => CallType::LinkList->value]]])
            ->assertSessionHasNoErrors();
    }
}
