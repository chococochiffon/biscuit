<?php

namespace Tests\Feature\API;

use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinglePageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_single_pages(): void
    {
        $singlePage = SinglePage::factory()->create(['title' => '会社概要']);

        $response = $this->getJson(route('single-pages.index'));

        $response->assertOk();
        $response->assertJsonFragment(['title' => $singlePage->title]);
    }

    public function test_show_returns_single_page_with_details_ordered_by_sort_order(): void
    {
        $singlePage = SinglePage::factory()->create();
        $second = SinglePageDetail::factory()->create([
            'single_page_id' => $singlePage->id,
            'sub_title' => '事業内容',
            'sort_order' => 1,
        ]);
        $first = SinglePageDetail::factory()->create([
            'single_page_id' => $singlePage->id,
            'sub_title' => '沿革',
            'sort_order' => 0,
        ]);

        $response = $this->getJson(route('single-pages.show', $singlePage));

        $response->assertOk();
        $response->assertJsonPath('data.details.0.id', $first->id);
        $response->assertJsonPath('data.details.1.id', $second->id);
    }

    public function test_show_returns_404_for_soft_deleted_single_page(): void
    {
        $singlePage = SinglePage::factory()->create();
        $singlePageId = $singlePage->id;
        $singlePage->delete();

        $response = $this->getJson("/api/single-pages/{$singlePageId}");

        $response->assertNotFound();
    }
}
