<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use App\Models\SinglePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_articles_are_published_status_and_within_publication_period(): void
    {
        $visible = Article::factory()->create([
            'approval' => ArticleApprovalStatus::Published,
            'publication_start_datetime' => now()->subDay(),
            'publication_end_datetime' => null,
        ]);
        Article::factory()->create(['approval' => ArticleApprovalStatus::Draft, 'publication_start_datetime' => now()->subDay()]);
        Article::factory()->create(['approval' => ArticleApprovalStatus::Pending, 'publication_start_datetime' => now()->subDay()]);
        Article::factory()->create(['approval' => ArticleApprovalStatus::Published, 'publication_start_datetime' => now()->addDay()]);
        Article::factory()->create([
            'approval' => ArticleApprovalStatus::Published,
            'publication_start_datetime' => now()->subDays(2),
            'publication_end_datetime' => now()->subDay(),
        ]);

        $this->assertSame([$visible->id], Article::query()->published()->pluck('id')->all());
    }

    public function test_published_single_pages_are_within_publication_period(): void
    {
        $visible = SinglePage::factory()->create(['publication_start_datetime' => now()->subDay(), 'publication_end_datetime' => null]);
        SinglePage::factory()->create(['publication_start_datetime' => now()->addDay()]);
        SinglePage::factory()->create(['publication_start_datetime' => now()->subDays(2), 'publication_end_datetime' => now()->subDay()]);

        $this->assertSame([$visible->id], SinglePage::query()->published()->pluck('id')->all());
    }
}
