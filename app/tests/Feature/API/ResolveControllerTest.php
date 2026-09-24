<?php

namespace Tests\Feature\API;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_single_page_by_path(): void
    {
        $singlePage = SinglePage::factory()->create(['parent_path' => 'company', 'slug' => 'about']);
        SinglePageDetail::factory()->create(['single_page_id' => $singlePage->id, 'sub_title' => '沿革']);

        $response = $this->getJson(route('api.resolve', ['path' => '/company/about']));

        $response->assertOk();
        $response->assertJsonPath('type', 'single_page');
        $response->assertJsonPath('data.id', $singlePage->id);
        $response->assertJsonPath('data.path', '/company/about');
        $response->assertJsonPath('data.details.0.sub_title', '沿革');
    }

    public function test_resolves_single_page_at_site_root_and_ignores_surrounding_slashes(): void
    {
        $singlePage = SinglePage::factory()->create(['parent_path' => null, 'slug' => 'about']);

        $response = $this->getJson(route('api.resolve', ['path' => 'about/']));

        $response->assertOk();
        $response->assertJsonPath('data.id', $singlePage->id);
        $response->assertJsonPath('data.path', '/about');
    }

    public function test_resolves_published_article_by_slug(): void
    {
        $article = Article::factory()->published()->create(['parent_path' => 'news', 'slug' => 'first-post']);

        $response = $this->getJson(route('api.resolve', ['path' => '/news/first-post']));

        $response->assertOk();
        $response->assertJsonPath('type', 'article');
        $response->assertJsonPath('data.id', $article->id);
        $response->assertJsonPath('data.path', '/news/first-post');
    }

    public function test_resolves_published_article_by_id_when_slug_is_empty(): void
    {
        $article = Article::factory()->published()->create(['parent_path' => 'news', 'slug' => null]);

        $response = $this->getJson(route('api.resolve', ['path' => '/news/'.$article->id]));

        $response->assertOk();
        $response->assertJsonPath('type', 'article');
        $response->assertJsonPath('data.id', $article->id);
        $response->assertJsonPath('data.path', '/news/'.$article->id);
    }

    public function test_returns_404_for_unpublished_article(): void
    {
        Article::factory()->create(['parent_path' => 'news', 'slug' => 'draft-post', 'approval' => ArticleApprovalStatus::Draft]);

        $this->getJson(route('api.resolve', ['path' => '/news/draft-post']))->assertNotFound();
    }

    public function test_returns_404_for_unknown_or_soft_deleted_path(): void
    {
        SinglePage::factory()->create(['slug' => 'deleted'])->delete();
        Article::factory()->published()->create(['slug' => 'deleted-post'])->delete();

        $this->getJson(route('api.resolve', ['path' => '/unknown']))->assertNotFound();
        $this->getJson(route('api.resolve', ['path' => '/deleted']))->assertNotFound();
        $this->getJson(route('api.resolve', ['path' => '/deleted-post']))->assertNotFound();
    }

    public function test_requires_path(): void
    {
        $this->getJson(route('api.resolve'))->assertUnprocessable()->assertJsonValidationErrors('path');
    }
}
