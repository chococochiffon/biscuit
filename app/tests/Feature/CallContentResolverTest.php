<?php

namespace Tests\Feature;

use App\Enums\CallType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
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
        $relation = ContentModelRelation::factory()->create(['table_name' => 'articles']);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $relation->id,
        ]);

        $results = (new CallContentResolver)->resolveMany([$callContent]);

        $this->assertTrue($results->get($callContent->id)->is($latestArticle));
    }

    public function test_resolves_single_page_content_to_the_latest_single_page(): void
    {
        SinglePage::factory()->create(['created_at' => now()->subDay()]);
        $latestSinglePage = SinglePage::factory()->create(['created_at' => now()]);
        $relation = ContentModelRelation::factory()->create(['table_name' => 'single_pages']);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $relation->id,
        ]);

        $results = (new CallContentResolver)->resolveMany([$callContent]);

        $this->assertTrue($results->get($callContent->id)->is($latestSinglePage));
    }

    public function test_resolves_link_list_content_to_the_latest_n_records(): void
    {
        $oldest = Article::factory()->create(['created_at' => now()->subDays(3)]);
        $middle = Article::factory()->create(['created_at' => now()->subDays(2)]);
        $newest = Article::factory()->create(['created_at' => now()->subDay()]);
        $relation = ContentModelRelation::factory()->create(['table_name' => 'articles']);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'content_model_relation_id' => $relation->id,
            'view_count' => 2,
        ]);

        $results = (new CallContentResolver)->resolveMany([$callContent]);
        $links = $results->get($callContent->id);

        $this->assertInstanceOf(EloquentCollection::class, $links);
        $this->assertTrue($links->pluck('id')->all() === [$newest->id, $middle->id]);
        $this->assertFalse($links->contains($oldest));
    }

    public function test_resolves_many_call_contents_sharing_a_content_model_relation_with_two_queries(): void
    {
        $latestArticle = Article::factory()->create();
        $relation = ContentModelRelation::factory()->create(['table_name' => 'articles']);
        $callContents = CallContent::factory()->count(3)->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $relation->id,
        ]);

        // 1クエリ: contentModelRelationの一括ロード、1クエリ: Articleの一括取得
        $this->expectsDatabaseQueryCount(2);
        $results = (new CallContentResolver)->resolveMany($callContents);

        foreach ($callContents as $callContent) {
            $this->assertTrue($results->get($callContent->id)->is($latestArticle));
        }
    }

    public function test_throws_for_an_unknown_table_name(): void
    {
        $relation = ContentModelRelation::factory()->create(['table_name' => 'unknown']);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $relation->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CallContentResolver)->resolveMany([$callContent]);
    }
}
