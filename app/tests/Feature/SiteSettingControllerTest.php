<?php

namespace Tests\Feature;

use App\Enums\CallContentPlace;
use App\Enums\CallContentType;
use App\Enums\CallType;
use App\Enums\SocialService;
use App\Models\Administrator;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_site_setting_pages(): void
    {
        $response = $this->get(route('admin.site-settings.create'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.site-settings.create'));

        $response->assertOk();
    }

    public function test_store_creates_site_setting(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'description' => 'サイトの説明文',
        ]);

        $siteSetting = SiteSetting::where('site_title', 'テストサイト')->firstOrFail();
        $response->assertRedirect(route('admin.site-settings.show', $siteSetting));
        $this->assertSame('サイトの説明文', $siteSetting->description);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), []);

        $response->assertSessionHasErrors(['site_title']);
    }

    public function test_store_uploads_site_icon_and_site_image_with_expected_filenames(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $actor = Administrator::factory()->create();
        $icon = UploadedFile::fake()->image('icon.png');
        $image = UploadedFile::fake()->image('image.jpg');

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => '画像付きサイト',
            'site_icon' => $icon,
            'site_image' => $image,
        ]);

        $siteSetting = SiteSetting::where('site_title', '画像付きサイト')->firstOrFail();
        $response->assertRedirect(route('admin.site-settings.show', $siteSetting));

        $expectedIconPath = 'image/site_icon/'.now()->format('YmdHis').'_site_settings_'.$siteSetting->id.'.png';
        $expectedImagePath = 'image/site_image/'.now()->format('YmdHis').'_site_settings_'.$siteSetting->id.'.jpg';
        $this->assertSame($expectedIconPath, $siteSetting->site_icon);
        $this->assertSame($expectedImagePath, $siteSetting->site_image);
        Storage::disk('public')->assertExists($expectedIconPath);
        Storage::disk('public')->assertExists($expectedImagePath);
    }

    public function test_store_saves_call_content_sort_order_from_form_or_row_order(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);
        $row = fn (string $callName, ?int $sortOrder) => array_filter([
            'call_type' => CallType::Link->value,
            'call_name' => $callName,
            'content_model_relation_id' => $relation->id,
            'view_count' => 1,
            'place' => CallContentPlace::Top->value,
            'sort_order' => $sortOrder,
        ], fn ($value) => $value !== null);

        $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [$row('並び順指定', 5), $row('並び順なし', null)],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, CallContent::where('call_name', '並び順指定')->value('sort_order'));
        $this->assertSame(1, CallContent::where('call_name', '並び順なし')->value('sort_order'));
    }

    public function test_store_saves_call_content_title_and_subtitle(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);
        $row = fn (string $callName, array $headings) => [
            'call_type' => CallType::Link->value,
            'call_name' => $callName,
            'content_model_relation_id' => $relation->id,
            'view_count' => 1,
            'place' => CallContentPlace::Top->value,
            ...$headings,
        ];

        $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                $row('見出しあり', ['title' => 'About', 'subtitle' => 'このサイトについて']),
                $row('見出しなし', ['title' => '', 'subtitle' => '']),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('call_contents', ['call_name' => '見出しあり', 'title' => 'About', 'subtitle' => 'このサイトについて']);
        $this->assertDatabaseHas('call_contents', ['call_name' => '見出しなし', 'title' => null, 'subtitle' => null]);
    }

    public function test_store_rejects_call_content_title_longer_than_255_characters(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [[
                'call_type' => CallType::Link->value,
                'call_name' => '長すぎる見出し',
                'title' => str_repeat('あ', 256),
                'content_model_relation_id' => $relation->id,
                'view_count' => 1,
                'place' => CallContentPlace::Top->value,
            ]],
        ]);

        $response->assertSessionHasErrors('call_contents.0.title');
        $this->assertDatabaseMissing('call_contents', ['call_name' => '長すぎる見出し']);
    }

    public function test_store_creates_social_links_in_row_order(): void
    {
        $actor = Administrator::factory()->create();

        $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'social_links' => [
                ['service' => SocialService::YouTube->value, 'name' => 'YouTube', 'url' => 'https://www.youtube.com/@example'],
                ['service' => SocialService::X->value, 'name' => 'X', 'url' => 'https://x.com/example'],
            ],
        ])->assertSessionHasNoErrors();

        $links = SocialLink::query()->ordered()->get();
        $this->assertSame([SocialService::YouTube, SocialService::X], $links->pluck('service')->all());
        $this->assertSame([0, 1], $links->pluck('sort_order')->all());
        $this->assertSame('https://x.com/example', $links[1]->url);
    }

    public function test_store_rejects_social_link_with_invalid_url_or_service(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'social_links' => [
                ['service' => SocialService::GitHub->value, 'name' => 'GitHub', 'url' => 'javascript:alert(1)'],
                ['service' => 999, 'name' => '不明', 'url' => 'https://example.com'],
            ],
        ]);

        $response->assertSessionHasErrors(['social_links.0.url', 'social_links.1.service']);
        $this->assertDatabaseCount('social_links', 0);
    }

    public function test_update_syncs_social_links_creating_updating_and_deleting_rows(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();
        $kept = SocialLink::factory()->create(['service' => SocialService::GitHub, 'name' => '旧GitHub', 'sort_order' => 0]);
        $removed = SocialLink::factory()->create(['sort_order' => 1]);

        $this->actingAs($actor, 'admin')->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => $siteSetting->site_title,
            'social_links' => [
                ['service' => SocialService::Amazon->value, 'name' => 'ほしいものリスト', 'url' => 'https://www.amazon.jp/hz/wishlist/ls/example', 'sort_order' => 0],
                ['id' => $kept->id, 'service' => SocialService::GitHub->value, 'name' => 'GitHub', 'url' => 'https://github.com/example', 'sort_order' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('GitHub', $kept->fresh()->name);
        $this->assertSame(1, $kept->fresh()->sort_order);
        $this->assertSoftDeleted($removed);
        $this->assertDatabaseHas('social_links', ['name' => 'ほしいものリスト', 'service' => SocialService::Amazon->value, 'sort_order' => 0]);
    }

    public function test_show_displays_social_links(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();
        SocialLink::factory()->create(['name' => '表示確認用リンク']);

        $this->actingAs($actor, 'admin')->get(route('admin.site-settings.show', $siteSetting))
            ->assertOk()
            ->assertSee('表示確認用リンク');
    }

    public function test_store_creates_call_contents_together_with_site_setting(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::OriginalText->value,
                    'call_name' => '記事詳細',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 1,
                    'place' => CallContentPlace::Inside->value,
                ],
                [
                    'call_type' => CallType::LinkList->value,
                    'call_name' => '記事一覧',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 3,
                    'place' => CallContentPlace::Others->value,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('call_contents', 2);
        $this->assertDatabaseHas('call_contents', [
            'call_type' => CallType::OriginalText->value,
            'call_name' => '記事詳細',
            'content_model_relation_id' => $relation->id,
            'view_count' => 1,
            'place' => CallContentPlace::Inside->value,
        ]);
        $this->assertDatabaseHas('call_contents', [
            'call_type' => CallType::LinkList->value,
            'call_name' => '記事一覧',
            'content_model_relation_id' => $relation->id,
            'view_count' => 3,
            'place' => CallContentPlace::Others->value,
        ]);
    }

    public function test_store_fails_when_content_model_relation_content_type_is_not_allowed_for_call_type(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::ShortSentence->value,
                    'call_name' => '短文',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 1,
                    'place' => CallContentPlace::Top->value,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['call_contents.0.content_model_relation_id']);
    }

    public function test_store_fails_when_call_type_is_not_allowed_for_place(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::SinglePage, 'model_name' => 'SinglePage']);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::ShortSentence->value,
                    'call_name' => '短文',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 1,
                    'place' => CallContentPlace::Others->value,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['call_contents.0.call_type']);
        $response->assertSessionDoesntHaveErrors(['call_contents.0.place', 'call_contents.0.content_model_relation_id']);
    }

    public function test_store_fails_when_content_model_relation_is_not_allowed_for_place_and_call_type(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);

        // リンクリストはTopで選択可能だが、TopでArticleのリンクリストは許可されていない(Othersのみ)
        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::LinkList->value,
                    'call_name' => '記事リンク一覧',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 3,
                    'place' => CallContentPlace::Top->value,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['call_contents.0.content_model_relation_id']);
        $response->assertSessionDoesntHaveErrors(['call_contents.0.place', 'call_contents.0.call_type']);
    }

    public function test_store_fails_when_view_count_is_not_fixed_to_one_for_call_type(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::Link->value,
                    'call_name' => 'リンク',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 3,
                    'place' => CallContentPlace::Top->value,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['call_contents.0.view_count']);
    }

    public function test_store_fails_when_skill_list_call_type_uses_a_relation_other_than_user_detail(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Custom,
            'model_name' => 'Recipe',
        ]);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::SkillList->value,
                    'call_name' => 'スキル一覧',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 1,
                    'place' => CallContentPlace::Top->value,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['call_contents.0.content_model_relation_id']);
    }

    public function test_store_creates_call_content_with_skill_list_call_type_and_user_detail_relation(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Custom,
            'model_name' => 'UserDetail',
        ]);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::SkillList->value,
                    'call_name' => 'スキル一覧',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 3,
                    'place' => CallContentPlace::Inside->value,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('call_contents', [
            'call_type' => CallType::SkillList->value,
            'content_model_relation_id' => $relation->id,
            'view_count' => 3,
            'place' => CallContentPlace::Inside->value,
        ]);
    }

    public function test_store_fails_when_archive_call_type_uses_a_relation_other_than_article(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Custom,
            'model_name' => 'Recipe',
        ]);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::Archive->value,
                    'call_name' => 'レシピアーカイブ',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 5,
                    'place' => CallContentPlace::Others->value,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['call_contents.0.content_model_relation_id']);
    }

    public function test_store_creates_call_content_with_archive_call_type_and_article_relation(): void
    {
        $actor = Administrator::factory()->create();
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'Article',
        ]);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'call_contents' => [
                [
                    'call_type' => CallType::Archive->value,
                    'call_name' => '記事アーカイブ',
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 5,
                    'place' => CallContentPlace::Others->value,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('call_contents', [
            'call_type' => CallType::Archive->value,
            'content_model_relation_id' => $relation->id,
            'view_count' => 5,
            'place' => CallContentPlace::Others->value,
        ]);
    }

    public function test_update_changes_and_clears_call_content_title_and_subtitle(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Link,
            'content_model_relation_id' => $relation->id,
            'view_count' => 1,
            'place' => CallContentPlace::Top,
            'title' => '旧見出し',
            'subtitle' => '旧小見出し',
        ]);

        $this->actingAs($actor, 'admin')->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => $siteSetting->site_title,
            'call_contents' => [[
                'id' => $callContent->id,
                'call_type' => CallType::Link->value,
                'call_name' => $callContent->call_name,
                'title' => '新見出し',
                'subtitle' => '',
                'content_model_relation_id' => $relation->id,
                'view_count' => 1,
                'place' => CallContentPlace::Top->value,
            ]],
        ])->assertSessionHasNoErrors();

        $callContent->refresh();
        $this->assertSame('新見出し', $callContent->title);
        $this->assertNull($callContent->subtitle);
    }

    public function test_update_syncs_call_contents_creating_updating_and_deleting_rows(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();
        $relation = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'Article']);
        $otherRelation = ContentModelRelation::factory()->create(['content_type' => CallContentType::SinglePage, 'model_name' => 'SinglePage']);
        $kept = CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'content_model_relation_id' => $relation->id,
            'view_count' => 1,
            'place' => CallContentPlace::Top,
        ]);
        $removed = CallContent::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => $siteSetting->site_title,
            'call_contents' => [
                [
                    'id' => $kept->id,
                    'call_type' => CallType::LinkList->value,
                    'call_name' => $kept->call_name,
                    'content_model_relation_id' => $relation->id,
                    'view_count' => 5,
                    'place' => CallContentPlace::Others->value,
                ],
                [
                    'call_type' => CallType::Link->value,
                    'call_name' => '固定ページリンク',
                    'content_model_relation_id' => $otherRelation->id,
                    'view_count' => 1,
                    'place' => CallContentPlace::Top->value,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.site-settings.show', $siteSetting));
        $this->assertDatabaseHas('call_contents', [
            'id' => $kept->id,
            'view_count' => 5,
            'place' => CallContentPlace::Others->value,
        ]);
        $this->assertDatabaseHas('call_contents', [
            'call_name' => '固定ページリンク',
            'call_type' => CallType::Link->value,
            'content_model_relation_id' => $otherRelation->id,
        ]);
        $this->assertSoftDeleted('call_contents', ['id' => $removed->id]);
        $this->assertDatabaseCount('call_contents', 3);
    }

    public function test_update_removes_all_call_contents_when_none_are_submitted(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();
        $existing = CallContent::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => $siteSetting->site_title,
        ]);

        $response->assertRedirect(route('admin.site-settings.show', $siteSetting));
        $this->assertSoftDeleted('call_contents', ['id' => $existing->id]);
    }

    public function test_edit_screen_displays_existing_call_contents(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();
        CallContent::factory()->create(['call_name' => '編集画面確認用モデル']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.site-settings.edit', $siteSetting));

        $response->assertOk();
        $response->assertSee('編集画面確認用モデル');
    }

    public function test_show_displays_site_setting(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create(['site_title' => '表示確認サイト']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.site-settings.show', $siteSetting));

        $response->assertOk();
        $response->assertSee('表示確認サイト');
    }

    public function test_show_does_not_display_the_content_model_relations_link(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.site-settings.show', $siteSetting));

        $response->assertOk();
        $response->assertDontSee(route('admin.content-model-relations.index'), false);
    }

    public function test_show_displays_call_contents(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'call_name' => '詳細確認用モデル',
            'view_count' => 2,
            'place' => CallContentPlace::Inside,
        ]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.site-settings.show', $siteSetting));

        $response->assertOk();
        $response->assertSee($callContent->call_type->label());
        $response->assertSee('詳細確認用モデル');
        $response->assertSee($callContent->place->label());
        $response->assertSeeInOrder(['呼び出し名', '表示箇所', '呼び出し方', 'データ種別', '表示件数']);
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.site-settings.edit', $siteSetting));

        $response->assertOk();
    }

    public function test_update_modifies_site_setting(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create(['site_title' => '更新前サイト']);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => '更新後サイト',
            'description' => '更新後の説明文',
        ]);

        $response->assertRedirect(route('admin.site-settings.show', $siteSetting));
        $this->assertSame('更新後サイト', $siteSetting->fresh()->site_title);
        $this->assertSame('更新後の説明文', $siteSetting->fresh()->description);
    }

    public function test_update_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => '',
        ]);

        $response->assertSessionHasErrors(['site_title']);
    }

    public function test_update_replaces_site_icon_and_site_image_with_expected_filenames(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create();
        $icon = UploadedFile::fake()->image('new-icon.png');
        $image = UploadedFile::fake()->image('new-image.jpg');

        $response = $this->actingAs($actor, 'admin')->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => $siteSetting->site_title,
            'site_icon' => $icon,
            'site_image' => $image,
        ]);

        $response->assertRedirect(route('admin.site-settings.show', $siteSetting));

        $expectedIconPath = 'image/site_icon/'.now()->format('YmdHis').'_site_settings_'.$siteSetting->id.'.png';
        $expectedImagePath = 'image/site_image/'.now()->format('YmdHis').'_site_settings_'.$siteSetting->id.'.jpg';
        $this->assertSame($expectedIconPath, $siteSetting->fresh()->site_icon);
        $this->assertSame($expectedImagePath, $siteSetting->fresh()->site_image);
        Storage::disk('public')->assertExists($expectedIconPath);
        Storage::disk('public')->assertExists($expectedImagePath);
    }
}
