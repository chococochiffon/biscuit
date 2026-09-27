<?php

namespace Tests\Feature;

use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use App\Models\SocialLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortOrderScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordered_sorts_by_sort_order_and_then_by_id(): void
    {
        $third = SocialLink::factory()->create(['sort_order' => 2]);
        $first = SocialLink::factory()->create(['sort_order' => 1]);
        $second = SocialLink::factory()->create(['sort_order' => 1]);

        $this->assertSame([$first->id, $second->id, $third->id], SocialLink::query()->ordered()->pluck('id')->all());
    }

    public function test_single_page_details_with_the_same_sort_order_are_sorted_by_id(): void
    {
        $singlePage = SinglePage::factory()->create();
        $later = SinglePageDetail::factory()->create(['single_page_id' => $singlePage->id, 'sort_order' => 1]);
        $earlier = SinglePageDetail::factory()->create(['single_page_id' => $singlePage->id, 'sort_order' => 0]);
        $sameAsLater = SinglePageDetail::factory()->create(['single_page_id' => $singlePage->id, 'sort_order' => 1]);

        $this->assertSame([$earlier->id, $later->id, $sameAsLater->id], $singlePage->details()->pluck('id')->all());
    }
}
