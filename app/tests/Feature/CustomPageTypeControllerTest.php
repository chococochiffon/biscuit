<?php

namespace Tests\Feature;

use App\Enums\CustomFormType;
use App\Enums\CustomPageBaseType;
use App\Models\CustomPages\CustomForm;
use App\Models\CustomPageType;
use App\Support\CustomPages\CustomPageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomPageTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_custom_page_type_pages(): void
    {
        $this->get(route('admin.custom-page-types.index'))->assertRedirect(route('admin.login'));
    }

    public function test_only_super_admins_can_use_custom_page_management(): void
    {
        $type = $this->createTypeWithTables('recipe');

        // 通常の管理者は、種類の管理と種類ごとのページのどちらも使えず、サイドメニューにも出ない
        $this->actingAsAdmin();
        $this->get(route('admin.custom-page-types.index'))->assertForbidden();
        $this->post(route('admin.custom-page-types.store'), ['name' => 'shop', 'label' => '店舗', 'base_type' => 1])->assertForbidden();
        $this->get(route('admin.custom-pages.entries.index', $type))->assertForbidden();
        $this->post(route('admin.custom-pages.entries.store', $type), ['title' => '肉じゃが'])->assertForbidden();
        $this->get(route('admin.articles.index'))->assertOk()->assertDontSee('カスタムページ管理');
        $this->assertFalse(Schema::hasTable('user_make_shops'));

        $this->actingAsSuperAdmin();
        $this->get(route('admin.custom-page-types.index'))->assertOk();
        $this->get(route('admin.articles.index'))->assertOk()->assertSee('カスタムページ管理');
    }

    public function test_store_creates_article_type_and_its_tables(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(route('admin.custom-page-types.store'), [
            'name' => 'recipe',
            'label' => 'レシピ',
            'base_type' => CustomPageBaseType::Article->value,
        ]);

        $type = CustomPageType::where('name', 'recipe')->firstOrFail();
        $response->assertRedirect(route('admin.custom-page-types.edit', $type));
        $this->assertSame(CustomPageBaseType::Article, $type->base_type);
        $this->assertTrue(Schema::hasTable('user_make_recipes'));
        $this->assertTrue(Schema::hasColumns('user_make_recipes', ['title', 'content', 'approval', 'publication_start_datetime']));
        // 記事型は詳細のテーブルを作らない
        $this->assertFalse(Schema::hasTable('user_make_recipe_details'));
        $this->assertTrue(Schema::hasColumns('customs_recipe_forms', ['parts_name', 'customs_form_type', 'customs_form_options']));
        $this->assertTrue(Schema::hasColumns('customs_recipe_form_values', ['customs_recipe_form_id', 'user_make_recipe_id', 'value']));
        $this->assertFalse(Schema::hasColumn('customs_recipe_form_values', 'user_make_recipe_detail_id'));
    }

    public function test_store_creates_single_page_type_with_details_table(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.custom-page-types.store'), [
            'name' => 'shop',
            'label' => '店舗',
            'base_type' => CustomPageBaseType::SinglePage->value,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Schema::hasColumns('user_make_shops', ['title', 'short_sentences', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('user_make_shop_details', ['user_make_shop_id', 'sub_title', 'contents', 'sort_order']));
        $this->assertTrue(Schema::hasColumn('customs_shop_form_values', 'user_make_shop_detail_id'));
    }

    public function test_store_normalizes_name_to_singular_snake_case(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.custom-page-types.store'), [
            'name' => 'BlogPosts',
            'label' => 'ブログ',
            'base_type' => CustomPageBaseType::Article->value,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('custom_page_types', ['name' => 'blog_post']);
        $this->assertTrue(Schema::hasTable('user_make_blog_posts'));
        $this->assertTrue(Schema::hasTable('customs_blog_post_forms'));
    }

    public function test_store_rejects_invalid_or_used_names(): void
    {
        $this->actingAsSuperAdmin();
        CustomPageType::factory()->create(['name' => 'recipe'])->delete();

        // 削除済みの種類のカスタム名も使えない(テーブルを残すため)
        $this->post(route('admin.custom-page-types.store'), ['name' => 'recipes', 'label' => 'レシピ', 'base_type' => 1])
            ->assertSessionHasErrors('name');
        $this->post(route('admin.custom-page-types.store'), ['name' => '1st_page', 'label' => 'ページ', 'base_type' => 1])
            ->assertSessionHasErrors('name');
        $this->post(route('admin.custom-page-types.store'), ['name' => 'rec-ipe', 'label' => 'ページ', 'base_type' => 1])
            ->assertSessionHasErrors('name');
    }

    public function test_store_rejects_name_whose_table_already_exists(): void
    {
        $this->actingAsSuperAdmin();
        Schema::create('user_make_events', fn ($table) => $table->id());

        $this->post(route('admin.custom-page-types.store'), ['name' => 'event', 'label' => 'イベント', 'base_type' => 1])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('custom_page_types', ['name' => 'event']);
    }

    public function test_store_rejects_more_than_the_limit(): void
    {
        $this->actingAsSuperAdmin();
        config(['limits.custom_page_types' => 2]);
        CustomPageType::factory()->count(2)->create();

        $response = $this->post(route('admin.custom-page-types.store'), ['name' => 'recipe', 'label' => 'レシピ', 'base_type' => 1]);

        $response->assertSessionHasErrors('name');
        $this->assertFalse(Schema::hasTable('user_make_recipes'));
    }

    public function test_update_changes_label_and_syncs_custom_forms(): void
    {
        $this->actingAsSuperAdmin();
        $type = $this->createTypeWithTables('recipe');
        $removed = CustomForm::queryFor($type)->create(['parts_name' => '削除する項目', 'customs_form_type' => CustomFormType::Text, 'sort_order' => 0]);
        $kept = CustomForm::queryFor($type)->create(['parts_name' => '材料', 'customs_form_type' => CustomFormType::Text, 'sort_order' => 1]);

        $response = $this->put(route('admin.custom-page-types.update', $type), [
            'label' => '料理レシピ',
            'forms' => [
                ['parts_name' => '難易度', 'customs_form_type' => CustomFormType::Radio->value, 'options' => " 簡単 \n普通\n\n難しい\n普通", 'sort_order' => 0],
                ['id' => $kept->id, 'parts_name' => '材料', 'customs_form_type' => CustomFormType::Textarea->value, 'options' => '無視される', 'sort_order' => 1],
            ],
        ]);

        $response->assertRedirect(route('admin.custom-page-types.edit', $type));
        $this->assertSame('料理レシピ', $type->fresh()->label);
        $forms = CustomForm::queryFor($type)->ordered()->get();
        $this->assertSame(['難易度', '材料'], $forms->pluck('parts_name')->all());
        // 選択肢は空行・重複を除いて JSON で保存し、選択肢を持たない入力形式では保存しない
        $this->assertSame(['簡単', '普通', '難しい'], $forms[0]->customs_form_options);
        $this->assertSame(CustomFormType::Textarea, $forms[1]->customs_form_type);
        $this->assertNull($forms[1]->customs_form_options);
        $this->assertSoftDeleted($type->formsTableName(), ['id' => $removed->id]);
    }

    public function test_update_requires_options_for_option_types(): void
    {
        $this->actingAsSuperAdmin();
        $type = $this->createTypeWithTables('recipe');

        $response = $this->put(route('admin.custom-page-types.update', $type), [
            'label' => 'レシピ',
            'forms' => [['parts_name' => '難易度', 'customs_form_type' => CustomFormType::Select->value, 'options' => '']],
        ]);

        $response->assertSessionHasErrors('forms.0.options');
    }

    public function test_destroy_soft_deletes_type_and_keeps_tables(): void
    {
        $this->actingAsSuperAdmin();
        $type = $this->createTypeWithTables('recipe');

        $this->delete(route('admin.custom-page-types.destroy', $type))->assertRedirect(route('admin.custom-page-types.index'));

        $this->assertSoftDeleted($type);
        $this->assertTrue(Schema::hasTable('user_make_recipes'));
    }

    public function test_sidebar_lists_custom_page_types(): void
    {
        $this->actingAsSuperAdmin();
        $type = CustomPageType::factory()->create(['label' => 'レシピ']);

        $response = $this->get(route('admin.custom-page-types.index'));

        $response->assertOk();
        $response->assertSee('カスタムページ管理');
        $response->assertSee(route('admin.custom-pages.entries.index', $type), false);
    }

    public function test_rolling_back_the_migration_drops_tables_of_all_types(): void
    {
        $active = $this->createTypeWithTables('recipe');
        $deleted = $this->createTypeWithTables('shop', CustomPageBaseType::SinglePage);
        $deleted->delete();
        $migration = require database_path('migrations/2026_09_28_000004_create_custom_page_types_table.php');

        $migration->down();

        // 論理削除済みの種類のテーブルも含めて削除し、どの種類にも紐づかないテーブルを残さない
        foreach ([...$active->tableNames(), ...$deleted->tableNames(), 'custom_page_types'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} が残っています。");
        }

        $migration->up();
    }

    private function createTypeWithTables(string $name, CustomPageBaseType $baseType = CustomPageBaseType::Article): CustomPageType
    {
        $type = CustomPageType::factory()->create(['name' => $name, 'base_type' => $baseType]);
        (new CustomPageSchema)->create($type);

        return $type;
    }
}
