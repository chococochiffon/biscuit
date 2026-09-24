<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SinglePageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_single_page_pages(): void
    {
        $response = $this->get(route('admin.single-pages.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_index_displays_single_pages(): void
    {
        $actor = Administrator::factory()->create();
        $singlePage = SinglePage::factory()->create(['title' => '会社概要']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index'));

        $response->assertOk();
        $response->assertSee($singlePage->title);
    }

    public function test_index_displays_publication_datetimes(): void
    {
        $actor = Administrator::factory()->create();
        SinglePage::factory()->create([
            'publication_start_datetime' => '2026-10-01 09:00:00',
            'publication_end_datetime' => '2026-10-31 23:59:30',
        ]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index'));

        $response->assertOk();
        $response->assertSee('公開開始');
        $response->assertSee('公開終了');
        $response->assertSee('2026/10/01 09:00');
        $response->assertSee('2026/10/31 23:59');
        $response->assertDontSee('23:59:30');
    }

    public function test_index_displays_not_set_when_publication_end_datetime_is_null(): void
    {
        $actor = Administrator::factory()->create();
        SinglePage::factory()->create([
            'publication_start_datetime' => '2026-10-01 09:00:00',
            'publication_end_datetime' => null,
        ]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index'));

        $response->assertOk();
        $response->assertSee('未設定');
    }

    public function test_index_displays_columns_in_expected_order(): void
    {
        $actor = Administrator::factory()->create();
        SinglePage::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['<table', 'タイトル', '概要', '公開開始', '公開終了', 'Topページへ表示する', 'リンクリストへ表示する'], false);
    }

    public function test_index_displays_search_fields_in_expected_order(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['id="search-title"', 'id="search-publication_start-from"', 'id="search-publication_end-from"'], false);
        $response->assertDontSee('id="search-approval"', false);
    }

    public function test_index_shows_link_to_sort_by_display_order_when_reorder_is_disabled(): void
    {
        $actor = Administrator::factory()->create();
        SinglePage::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index'));

        $response->assertOk();
        $response->assertSee('表示順で並び替え');
        $response->assertSee(e(route('admin.single-pages.index', ['sort' => 'sort_order'])), false);
    }

    public function test_index_orders_single_pages_by_updated_at_desc_by_default(): void
    {
        $actor = Administrator::factory()->create();
        $older = SinglePage::factory()->create(['sort_order' => 0, 'updated_at' => now()->subDay()]);
        $newer = SinglePage::factory()->create(['sort_order' => 1, 'updated_at' => now()]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index'));

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], $response->viewData('singlePages')->pluck('id')->all());
        $response->assertDontSee('single-page-reorder-form');
    }

    public function test_index_orders_single_pages_by_sort_order_and_enables_reorder(): void
    {
        $actor = Administrator::factory()->create();
        $second = SinglePage::factory()->create(['title' => '2番目', 'sort_order' => 1]);
        $first = SinglePage::factory()->create(['title' => '1番目', 'sort_order' => 0]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index', ['sort' => 'sort_order']));

        $response->assertOk();
        $this->assertSame(
            [$first->id, $second->id],
            $response->viewData('singlePages')->pluck('id')->all()
        );
        $response->assertSee('single-page-reorder-form');
    }

    public function test_index_disables_reorder_while_searching_even_if_sorted_by_sort_order(): void
    {
        $actor = Administrator::factory()->create();
        SinglePage::factory()->create(['title' => '会社概要']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index', ['sort' => 'sort_order', 'title' => '会社']));

        $response->assertOk();
        $response->assertDontSee('single-page-reorder-form');
    }

    public function test_index_can_sort_by_title(): void
    {
        $actor = Administrator::factory()->create();
        $b = SinglePage::factory()->create(['title' => 'B']);
        $a = SinglePage::factory()->create(['title' => 'A']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index', ['sort' => 'title_asc']));

        $this->assertSame([$a->id, $b->id], $response->viewData('singlePages')->pluck('id')->all());
    }

    public function test_index_ignores_unknown_sort_and_falls_back_to_default(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index', ['sort' => 'unknown', 'publication_start_from' => 'invalid']));

        $response->assertOk();
        $this->assertSame('updated_at_desc', $response->viewData('sort'));
    }

    public function test_index_searches_by_title(): void
    {
        $actor = Administrator::factory()->create();
        $hit = SinglePage::factory()->create(['title' => '会社概要']);
        SinglePage::factory()->create(['title' => 'お問い合わせ']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index', ['title' => '会社']));

        $this->assertSame([$hit->id], $response->viewData('singlePages')->pluck('id')->all());
    }

    public function test_index_searches_by_publication_period(): void
    {
        $actor = Administrator::factory()->create();
        $hit = SinglePage::factory()->create([
            'publication_start_datetime' => '2026-10-05 10:00:00',
            'publication_end_datetime' => '2026-12-31 23:59:00',
        ]);
        SinglePage::factory()->create([
            'publication_start_datetime' => '2026-09-01 10:00:00',
            'publication_end_datetime' => '2026-12-31 23:59:00',
        ]);
        SinglePage::factory()->create([
            'publication_start_datetime' => '2026-10-05 10:00:00',
            'publication_end_datetime' => null,
        ]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.index', [
            'publication_start_from' => '2026-10-01',
            'publication_start_to' => '2026-10-31',
            'publication_end_from' => '2026-12-01',
            'publication_end_to' => '2026-12-31',
        ]));

        $this->assertSame([$hit->id], $response->viewData('singlePages')->pluck('id')->all());
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.create'));

        $response->assertOk();
    }

    public function test_create_screen_displays_one_empty_detail_row(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.create'));

        // テンプレート(__INDEX__)とは別に、初期表示の空の詳細ブロックが1つだけ存在する
        $response->assertSee('name="details[0][sub_title]"', false);
        $response->assertDontSee('name="details[1][sub_title]"', false);
        $response->assertSee('data-next-index="1"', false);
    }

    public function test_create_screen_displays_title_short_sentences_and_details_in_expected_order(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.create'));

        $response->assertSeeInOrder(['id="title"', 'id="short_sentences"', 'id="single-page-detail-rows"', 'id="taxonomy"'], false);
    }

    public function test_store_creates_single_page_with_details(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.single-pages.store'), [
            'title' => '会社概要',
            'short_sentences' => '会社の概要ページです',
            'taxonomy' => 'company',
            'uri' => 'about',
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
            'details' => [
                ['sub_title' => '沿革', 'contents' => '<p>沿革本文</p>', 'sort_order' => 0],
                ['sub_title' => '事業内容', 'contents' => '<p>事業内容本文</p>', 'sort_order' => 1],
            ],
        ]);

        $response->assertRedirect(route('admin.single-pages.index'));

        $singlePage = SinglePage::where('title', '会社概要')->firstOrFail();
        $this->assertSame('company', $singlePage->taxonomy);
        $this->assertSame('about', $singlePage->uri);
        $this->assertCount(2, $singlePage->details);
        $this->assertSame('沿革', $singlePage->details->first()->sub_title);
        $this->assertSame(0, $singlePage->details->first()->sort_order);
    }

    public function test_store_uploads_header_image_with_expected_filename(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $actor = Administrator::factory()->create();
        $file = UploadedFile::fake()->image('header.jpg');

        $response = $this->actingAs($actor, 'admin')->post(route('admin.single-pages.store'), [
            'title' => 'ヘッダー画像記事',
            'short_sentences' => '概要',
            'header_image' => $file,
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.single-pages.index'));

        $singlePage = SinglePage::where('title', 'ヘッダー画像記事')->firstOrFail();
        $expectedPath = 'image/header_image/'.now()->format('YmdHis').'_single_pages_'.$singlePage->id.'.jpg';
        $this->assertSame($expectedPath, $singlePage->header_image);
        Storage::disk('public')->assertExists($expectedPath);
    }

    public function test_store_persists_top_page_view_link_list_view_and_assigns_next_sort_order(): void
    {
        $actor = Administrator::factory()->create();
        SinglePage::factory()->create(['sort_order' => 3]);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.single-pages.store'), [
            'title' => '新着情報',
            'short_sentences' => '新着情報ページです',
            'top_page_view' => '1',
            'link_list_view' => '0',
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.single-pages.index'));

        $singlePage = SinglePage::where('title', '新着情報')->firstOrFail();
        $this->assertTrue($singlePage->top_page_view);
        $this->assertFalse($singlePage->link_list_view);
        $this->assertSame(4, $singlePage->sort_order);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.single-pages.store'), []);

        $response->assertSessionHasErrors(['title', 'short_sentences', 'publication_start_datetime']);
    }

    public function test_store_persists_publication_start_and_end_datetimes(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.single-pages.store'), [
            'title' => '公開期間付きページ',
            'short_sentences' => '概要',
            'publication_start_datetime' => '2026-10-01 09:00',
            'publication_end_datetime' => '2026-10-31 23:59',
        ]);

        $response->assertRedirect(route('admin.single-pages.index'));

        $singlePage = SinglePage::where('title', '公開期間付きページ')->firstOrFail();
        $this->assertSame('2026-10-01 09:00', $singlePage->publication_start_datetime->format('Y-m-d H:i'));
        $this->assertSame('2026-10-31 23:59', $singlePage->publication_end_datetime->format('Y-m-d H:i'));
    }

    public function test_store_fails_validation_when_publication_end_datetime_is_before_start(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.single-pages.store'), [
            'title' => '公開期間逆転ページ',
            'short_sentences' => '概要',
            'publication_start_datetime' => '2026-10-10 00:00',
            'publication_end_datetime' => '2026-10-01 00:00',
        ]);

        $response->assertSessionHasErrors(['publication_end_datetime']);
    }

    public function test_show_displays_single_page(): void
    {
        $actor = Administrator::factory()->create();
        $target = SinglePage::factory()->create();
        SinglePageDetail::factory()->create(['single_page_id' => $target->id, 'sub_title' => '沿革']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.show', $target));

        $response->assertOk();
        $response->assertSee($target->title);
        $response->assertSee('沿革');
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();
        $target = SinglePage::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.edit', $target));

        $response->assertOk();
    }

    public function test_edit_screen_does_not_add_empty_detail_row_when_single_page_has_no_details(): void
    {
        $actor = Administrator::factory()->create();
        $target = SinglePage::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.single-pages.edit', $target));

        $response->assertDontSee('name="details[0][sub_title]"', false);
        $response->assertSee('data-next-index="0"', false);
    }

    public function test_update_modifies_single_page(): void
    {
        $actor = Administrator::factory()->create();
        $target = SinglePage::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.single-pages.update', $target), [
            'title' => '更新後タイトル',
            'short_sentences' => '更新後概要',
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.single-pages.index'));
        $this->assertSame('更新後タイトル', $target->fresh()->title);
    }

    public function test_update_modifies_top_page_view_and_link_list_view(): void
    {
        $actor = Administrator::factory()->create();
        $target = SinglePage::factory()->create(['top_page_view' => false, 'link_list_view' => false]);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.single-pages.update', $target), [
            'title' => $target->title,
            'short_sentences' => $target->short_sentences,
            'top_page_view' => '1',
            'link_list_view' => '1',
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.single-pages.index'));
        $target->refresh();
        $this->assertTrue($target->top_page_view);
        $this->assertTrue($target->link_list_view);
    }

    public function test_update_syncs_details_creating_updating_and_deleting(): void
    {
        $actor = Administrator::factory()->create();
        $target = SinglePage::factory()->create();
        $kept = SinglePageDetail::factory()->create([
            'single_page_id' => $target->id,
            'sub_title' => '既存(更新前)',
            'sort_order' => 0,
        ]);
        $removed = SinglePageDetail::factory()->create([
            'single_page_id' => $target->id,
            'sub_title' => '削除される',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.single-pages.update', $target), [
            'title' => $target->title,
            'short_sentences' => $target->short_sentences,
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
            'details' => [
                ['id' => $kept->id, 'sub_title' => '既存(更新後)', 'contents' => '<p>更新</p>', 'sort_order' => 0],
                ['sub_title' => '新規追加', 'contents' => '<p>新規</p>', 'sort_order' => 1],
            ],
        ]);

        $response->assertRedirect(route('admin.single-pages.index'));

        $target->refresh();
        $this->assertCount(2, $target->details);
        $this->assertSame('既存(更新後)', $kept->fresh()->sub_title);
        $this->assertSoftDeleted('single_page_details', ['id' => $removed->id]);
        $this->assertDatabaseHas('single_page_details', ['single_page_id' => $target->id, 'sub_title' => '新規追加']);
    }

    public function test_destroy_deletes_single_page(): void
    {
        $actor = Administrator::factory()->create();
        $target = SinglePage::factory()->create();

        $response = $this->actingAs($actor, 'admin')->delete(route('admin.single-pages.destroy', $target));

        $response->assertRedirect(route('admin.single-pages.index'));
        $this->assertSoftDeleted('single_pages', ['id' => $target->id]);
    }

    public function test_guests_are_redirected_from_reorder(): void
    {
        $singlePage = SinglePage::factory()->create();

        $response = $this->patch(route('admin.single-pages.reorder'), [
            'order' => [$singlePage->id],
        ]);

        $response->assertRedirect(route('admin.login'));
    }

    public function test_reorder_persists_the_submitted_order_as_sort_order(): void
    {
        $actor = Administrator::factory()->create();
        $a = SinglePage::factory()->create(['sort_order' => 0]);
        $b = SinglePage::factory()->create(['sort_order' => 1]);
        $c = SinglePage::factory()->create(['sort_order' => 2]);

        $response = $this->actingAs($actor, 'admin')->patch(route('admin.single-pages.reorder'), [
            'order' => [$c->id, $a->id, $b->id],
        ]);

        $response->assertRedirect(route('admin.single-pages.index', ['sort' => 'sort_order']));
        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    public function test_reorder_applies_offset_for_the_current_page(): void
    {
        $actor = Administrator::factory()->create();
        $a = SinglePage::factory()->create(['sort_order' => 20]);
        $b = SinglePage::factory()->create(['sort_order' => 21]);

        $response = $this->actingAs($actor, 'admin')->patch(route('admin.single-pages.reorder'), [
            'order' => [$b->id, $a->id],
            'offset' => 20,
        ]);

        $response->assertRedirect(route('admin.single-pages.index', ['sort' => 'sort_order']));
        $this->assertSame(20, $b->fresh()->sort_order);
        $this->assertSame(21, $a->fresh()->sort_order);
    }

    public function test_reorder_fails_validation_for_an_unknown_id(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->patch(route('admin.single-pages.reorder'), [
            'order' => [999999],
        ]);

        $response->assertSessionHasErrors(['order.0']);
    }
}
