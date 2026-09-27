<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use App\Models\SiteSetting;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_use_the_configured_number_of_items_per_page(): void
    {
        config(['limits.admin_per_page' => 2]);
        $this->actingAsAdmin();
        Tag::factory()->count(3)->create();

        $tags = $this->get(route('admin.tags.index'))->assertOk()->viewData('tags');

        $this->assertSame(2, $tags->perPage());
        $this->assertCount(2, $tags->items());
        $this->assertSame(2, $tags->lastPage());
    }

    public function test_article_api_uses_the_configured_number_of_items_per_page(): void
    {
        config(['limits.api_per_page' => 2]);
        Article::factory()->count(3)->create([
            'approval' => ArticleApprovalStatus::Published,
            'publication_start_datetime' => now()->subDay(),
        ]);

        $this->getJson(route('articles.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_current_site_setting_is_the_registered_one_or_null(): void
    {
        $this->assertNull(SiteSetting::current());

        $siteSetting = SiteSetting::factory()->create();

        $this->assertTrue($siteSetting->is(SiteSetting::current()));
    }
}
