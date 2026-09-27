<?php

namespace Tests\Feature;

use App\Models\SinglePage;
use Database\Seeders\SinglePageSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SinglePageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_single_page_with_path_even_without_model_events(): void
    {
        // DatabaseSeeder(WithoutModelEvents)から呼ばれる場合と同じく、モデルイベントを止めて実行する
        Model::withoutEvents(fn () => $this->seed(SinglePageSeeder::class));

        $singlePage = SinglePage::query()->with('details')->sole();

        $this->assertSame('/information/about', $singlePage->path);
        $this->assertCount(1, $singlePage->details);
    }

    public function test_seeding_twice_does_not_duplicate_single_page_or_details(): void
    {
        $this->seed(SinglePageSeeder::class);
        $this->seed(SinglePageSeeder::class);

        $this->assertDatabaseCount('single_pages', 1);
        $this->assertDatabaseCount('single_page_details', 1);
    }
}
