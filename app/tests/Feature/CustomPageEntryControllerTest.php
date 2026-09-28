<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CustomFormType;
use App\Enums\CustomPageBaseType;
use App\Models\CustomPages\CustomForm;
use App\Models\CustomPages\CustomFormValue;
use App\Models\CustomPages\CustomPageDetail;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Support\CustomPages\CustomPageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPageEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createType(string $name, CustomPageBaseType $baseType): CustomPageType
    {
        $type = CustomPageType::factory()->create(['name' => $name, 'label' => 'レシピ', 'base_type' => $baseType]);
        (new CustomPageSchema)->create($type);

        return $type;
    }

    /**
     * @param  list<string>|null  $options
     */
    private function createForm(CustomPageType $type, string $name, CustomFormType $formType, ?array $options = null, int $sortOrder = 0): CustomForm
    {
        return CustomForm::queryFor($type)->create([
            'parts_name' => $name,
            'customs_form_type' => $formType,
            'customs_form_options' => $options,
            'sort_order' => $sortOrder,
        ]);
    }

    public function test_store_saves_article_type_entry_with_custom_field_values(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $text = $this->createForm($type, '材料', CustomFormType::Text);
        $date = $this->createForm($type, '作った日', CustomFormType::Date);
        $radio = $this->createForm($type, '難易度', CustomFormType::Radio, ['簡単', '難しい']);
        $checkbox = $this->createForm($type, 'タグ', CustomFormType::Checkbox, ['和食', '洋食', '中華']);
        $empty = $this->createForm($type, 'メモ', CustomFormType::Textarea);

        $response = $this->post(route('admin.custom-pages.entries.store', $type), [
            'title' => '肉じゃが',
            'content' => '<p>作り方</p>',
            'approval' => ArticleApprovalStatus::Published->value,
            'publication_start_datetime' => '2026-10-01 10:00',
            'custom_fields' => [
                $text->id => 'じゃがいも',
                $date->id => '2026-09-30',
                $radio->id => '簡単',
                $checkbox->id => ['和食', '中華'],
            ],
        ]);

        $response->assertRedirect(route('admin.custom-pages.entries.index', $type));
        $entry = CustomPageEntry::queryFor($type)->where('title', '肉じゃが')->firstOrFail();
        $this->assertSame(ArticleApprovalStatus::Published, $entry->approval);
        $this->assertSame('2026-10-01 10:00', $entry->publication_start_datetime->format('Y-m-d H:i'));

        $values = CustomFormValue::queryFor($type)->where('user_make_recipe_id', $entry->id)->pluck('value', 'customs_recipe_form_id');
        $this->assertSame('じゃがいも', $values[$text->id]);
        $this->assertSame('2026-09-30', $values[$date->id]);
        $this->assertSame('簡単', $values[$radio->id]);
        $this->assertSame(['和食', '中華'], $values[$checkbox->id]);
        $this->assertNull($values[$empty->id]);
    }

    public function test_store_validates_custom_field_values_by_type(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $email = $this->createForm($type, '連絡先', CustomFormType::Email);
        $date = $this->createForm($type, '作った日', CustomFormType::Date);
        $select = $this->createForm($type, '難易度', CustomFormType::Select, ['簡単', '難しい']);
        $checkbox = $this->createForm($type, 'タグ', CustomFormType::Checkbox, ['和食']);

        $response = $this->post(route('admin.custom-pages.entries.store', $type), [
            'title' => '肉じゃが',
            'content' => '<p>作り方</p>',
            'approval' => ArticleApprovalStatus::Draft->value,
            'publication_start_datetime' => '2026-10-01 10:00',
            'custom_fields' => [
                $email->id => 'not-an-email',
                $date->id => '2026/09/30',
                $select->id => '普通',
                $checkbox->id => ['洋食'],
            ],
        ]);

        $response->assertSessionHasErrors([
            "custom_fields.{$email->id}",
            "custom_fields.{$date->id}",
            "custom_fields.{$select->id}",
            "custom_fields.{$checkbox->id}.0",
        ]);
        $this->assertSame(0, CustomPageEntry::queryFor($type)->count());
    }

    public function test_store_saves_single_page_type_entry_with_details(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('shop', CustomPageBaseType::SinglePage);
        CustomPageEntry::queryFor($type)->create(['title' => '既存', 'short_sentences' => '概要', 'sort_order' => 4]);

        $this->post(route('admin.custom-pages.entries.store', $type), [
            'title' => '本店',
            'short_sentences' => '駅前のお店です。',
            'publication_start_datetime' => '2026-10-01 10:00',
            'details' => [
                ['sub_title' => 'アクセス', 'contents' => '<p>駅から徒歩1分</p>', 'sort_order' => 0],
                ['sub_title' => '営業時間', 'contents' => '<p>10時〜</p>', 'sort_order' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $entry = CustomPageEntry::queryFor($type)->where('title', '本店')->firstOrFail();
        $this->assertSame(5, $entry->sort_order);
        $details = CustomPageDetail::queryFor($type)->where('user_make_shop_id', $entry->id)->ordered()->get();
        $this->assertSame(['アクセス', '営業時間'], $details->pluck('sub_title')->all());
    }

    public function test_update_changes_entry_values_and_syncs_details(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('shop', CustomPageBaseType::SinglePage);
        $form = $this->createForm($type, '定休日', CustomFormType::Text);
        $entry = CustomPageEntry::queryFor($type)->create(['title' => '本店', 'short_sentences' => '概要']);
        $kept = CustomPageDetail::queryFor($type)->create(['user_make_shop_id' => $entry->id, 'sub_title' => 'アクセス', 'sort_order' => 0]);
        $removed = CustomPageDetail::queryFor($type)->create(['user_make_shop_id' => $entry->id, 'sub_title' => '削除', 'sort_order' => 1]);
        CustomFormValue::queryFor($type)->create(['customs_shop_form_id' => $form->id, 'user_make_shop_id' => $entry->id, 'value' => '月曜']);

        $response = $this->put(route('admin.custom-pages.entries.update', [$type, $entry->id]), [
            'title' => '本店(改装)',
            'short_sentences' => '概要',
            'publication_start_datetime' => '2026-10-01 10:00',
            'details' => [['id' => $kept->id, 'sub_title' => '行き方', 'sort_order' => 0]],
            'custom_fields' => [$form->id => '火曜'],
        ]);

        $response->assertRedirect(route('admin.custom-pages.entries.index', $type));
        $this->assertSame('本店(改装)', $entry->fresh()->title);
        $this->assertSame('行き方', CustomPageDetail::queryFor($type)->find($kept->id)->sub_title);
        $this->assertSoftDeleted($type->detailsTableName(), ['id' => $removed->id]);
        $values = CustomFormValue::queryFor($type)->where('user_make_shop_id', $entry->id)->get();
        $this->assertCount(1, $values);
        $this->assertSame('火曜', $values->first()->value);
    }

    public function test_update_rejects_details_of_another_entry(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('shop', CustomPageBaseType::SinglePage);
        $entry = CustomPageEntry::queryFor($type)->create(['title' => '本店', 'short_sentences' => '概要']);
        $other = CustomPageEntry::queryFor($type)->create(['title' => '支店', 'short_sentences' => '概要']);
        $othersDetail = CustomPageDetail::queryFor($type)->create(['user_make_shop_id' => $other->id, 'sub_title' => '支店の詳細']);

        $response = $this->put(route('admin.custom-pages.entries.update', [$type, $entry->id]), [
            'title' => '本店',
            'short_sentences' => '概要',
            'publication_start_datetime' => '2026-10-01 10:00',
            'details' => [['id' => $othersDetail->id, 'sub_title' => '書き換え']],
        ]);

        $response->assertSessionHasErrors('details.0.id');
        $this->assertSame('支店の詳細', CustomPageDetail::queryFor($type)->find($othersDetail->id)->sub_title);
    }

    public function test_edit_screen_shows_saved_values(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $form = $this->createForm($type, '材料', CustomFormType::Text);
        $checkbox = $this->createForm($type, 'タグ', CustomFormType::Checkbox, ['和食', '洋食'], 1);
        $entry = CustomPageEntry::queryFor($type)->create(['title' => '肉じゃが', 'content' => '<p>本文</p>', 'approval' => ArticleApprovalStatus::Draft]);
        CustomFormValue::queryFor($type)->create(['customs_recipe_form_id' => $form->id, 'user_make_recipe_id' => $entry->id, 'value' => 'じゃがいも']);
        CustomFormValue::queryFor($type)->create(['customs_recipe_form_id' => $checkbox->id, 'user_make_recipe_id' => $entry->id, 'value' => ['洋食']]);

        $response = $this->get(route('admin.custom-pages.entries.edit', [$type, $entry->id]));

        $response->assertOk();
        $response->assertSee('肉じゃが');
        $response->assertSee('value="じゃがいも"', false);
        $response->assertSeeInOrder(['value="洋食"', 'checked'], false);
    }

    public function test_index_lists_entries_and_destroy_soft_deletes_related_rows(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $form = $this->createForm($type, '材料', CustomFormType::Text);
        $entry = CustomPageEntry::queryFor($type)->create(['title' => '肉じゃが', 'content' => '<p>本文</p>', 'approval' => ArticleApprovalStatus::Published]);
        $value = CustomFormValue::queryFor($type)->create(['customs_recipe_form_id' => $form->id, 'user_make_recipe_id' => $entry->id, 'value' => 'じゃがいも']);

        $this->get(route('admin.custom-pages.entries.index', $type))->assertOk()->assertSee('肉じゃが')->assertSee('レシピ一覧');

        $this->delete(route('admin.custom-pages.entries.destroy', [$type, $entry->id]))
            ->assertRedirect(route('admin.custom-pages.entries.index', $type));

        $this->assertSoftDeleted($type->tableName(), ['id' => $entry->id]);
        $this->assertSoftDeleted($type->formValuesTableName(), ['id' => $value->id]);
    }

    public function test_deleted_type_pages_are_not_found(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('recipe', CustomPageBaseType::Article);
        $type->delete();

        $this->get(route('admin.custom-pages.entries.index', $type))->assertNotFound();
    }

    public function test_create_screen_uses_date_picker_and_shows_one_empty_detail_row(): void
    {
        $this->actingAsAdmin();
        $type = $this->createType('shop', CustomPageBaseType::SinglePage);
        $date = $this->createForm($type, '開店日', CustomFormType::Date);

        $response = $this->get(route('admin.custom-pages.entries.create', $type));

        $response->assertOk();
        // 日付はブラウザ標準の入力ではなく、ほかの管理画面と同じ日付ピッカーにする
        $response->assertSee('name="custom_fields['.$date->id.']" value="" class="form-control" data-role="date-picker"', false);
        $response->assertDontSee('type="date"', false);
        // 固定ページと同じく、空の詳細ブロックを1つ表示する
        $response->assertSee('name="details[0][sub_title]"', false);
    }
}
