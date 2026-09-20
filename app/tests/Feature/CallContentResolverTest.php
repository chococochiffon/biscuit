<?php

namespace Tests\Feature;

use App\Enums\CallContentType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\SinglePage;
use App\Support\CallContentResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CallContentResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_single_article_content_to_the_latest_article(): void
    {
        Article::factory()->create(['created_at' => now()->subDay()]);
        $latestArticle = Article::factory()->create(['created_at' => now()]);
        $callContent = CallContent::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'article',
        ]);

        $results = (new CallContentResolver)->resolveMany([$callContent]);

        $this->assertTrue($results->get($callContent->id)->is($latestArticle));
    }

    public function test_resolves_single_page_content_to_the_latest_single_page(): void
    {
        SinglePage::factory()->create(['created_at' => now()->subDay()]);
        $latestSinglePage = SinglePage::factory()->create(['created_at' => now()]);
        $callContent = CallContent::factory()->create([
            'content_type' => CallContentType::SinglePage,
            'model_name' => 'single_page',
        ]);

        $results = (new CallContentResolver)->resolveMany([$callContent]);

        $this->assertTrue($results->get($callContent->id)->is($latestSinglePage));
    }

    public function test_resolves_link_list_content_to_the_latest_n_records(): void
    {
        $oldest = Article::factory()->create(['created_at' => now()->subDays(3)]);
        $middle = Article::factory()->create(['created_at' => now()->subDays(2)]);
        $newest = Article::factory()->create(['created_at' => now()->subDay()]);
        $callContent = CallContent::factory()->create([
            'content_type' => CallContentType::LinkList,
            'model_name' => 'article',
            'view_count' => 2,
        ]);

        $results = (new CallContentResolver)->resolveMany([$callContent]);
        $links = $results->get($callContent->id);

        $this->assertInstanceOf(EloquentCollection::class, $links);
        $this->assertTrue($links->pluck('id')->all() === [$newest->id, $middle->id]);
        $this->assertFalse($links->contains($oldest));
    }

    public function test_resolves_many_call_contents_sharing_a_model_name_with_a_single_query(): void
    {
        $latestArticle = Article::factory()->create();
        $callContents = CallContent::factory()->count(3)->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'article',
        ]);

        $this->expectsDatabaseQueryCount(1);
        $results = (new CallContentResolver)->resolveMany($callContents);

        foreach ($callContents as $callContent) {
            $this->assertTrue($results->get($callContent->id)->is($latestArticle));
        }
    }

    public function test_throws_for_an_unknown_model_name(): void
    {
        $callContent = CallContent::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'unknown',
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CallContentResolver)->resolveMany([$callContent]);
    }
}
