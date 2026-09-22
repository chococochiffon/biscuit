<?php

namespace Tests\Feature;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use App\Models\UserDetail;
use App\Support\CallContentResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CallContentResolverTest extends TestCase
{
    use RefreshDatabase;

    private function relation(string $modelName): ContentModelRelation
    {
        return ContentModelRelation::factory()->create(['model_name' => $modelName]);
    }

    // --- Article ---

    public function test_article_original_text_resolves_the_latest_published_article(): void
    {
        Article::factory()->published()->create(['created_at' => now()->subDay()]);
        $latest = Article::factory()->published()->create(['created_at' => now()]);
        Article::factory()->create(['created_at' => now()->addDay()]); // 下書きは対象外
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Top,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertTrue($result->is($latest));
    }

    public function test_article_link_list_resolves_the_latest_published_articles_up_to_view_count(): void
    {
        $oldest = Article::factory()->published()->create(['created_at' => now()->subDays(3)]);
        $middle = Article::factory()->published()->create(['created_at' => now()->subDays(2)]);
        $newest = Article::factory()->published()->create(['created_at' => now()->subDay()]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertSame([$newest->id, $middle->id], $result->pluck('id')->all());
        $this->assertFalse($result->contains($oldest));
    }

    public function test_article_link_resolves_the_latest_published_article(): void
    {
        $latest = Article::factory()->published()->create();
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Link,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Top,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertTrue($result->is($latest));
    }

    public function test_article_archive_resolves_the_latest_published_articles_up_to_view_count(): void
    {
        Article::factory()->published()->count(3)->create();
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Archive,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(2, $result);
    }

    // --- SinglePage ---

    public function test_single_page_short_sentence_resolves_up_to_five_top_page_view_pages_in_sort_order(): void
    {
        $excluded = SinglePage::factory()->create(['top_page_view' => false, 'sort_order' => 0]);
        $second = SinglePage::factory()->create(['top_page_view' => true, 'sort_order' => 2]);
        $first = SinglePage::factory()->create(['top_page_view' => true, 'sort_order' => 1]);
        SinglePage::factory()->count(4)->sequence(fn ($sequence) => ['sort_order' => 10 + $sequence->index])->create(['top_page_view' => true]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::ShortSentence,
            'content_model_relation_id' => $this->relation('SinglePage')->id,
            'place' => CallContentPlace::Top,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(5, $result);
        $this->assertFalse($result->contains($excluded));
        $this->assertSame($first->id, $result->first()->id);
        $this->assertSame($second->id, $result->get(1)->id);
    }

    public function test_single_page_original_text_resolves_the_first_page_in_sort_order_with_details(): void
    {
        $second = SinglePage::factory()->create(['sort_order' => 1]);
        $first = SinglePage::factory()->create(['sort_order' => 0]);
        $detail = SinglePageDetail::factory()->create(['single_page_id' => $first->id]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $this->relation('SinglePage')->id,
            'place' => CallContentPlace::Top,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertTrue($result->is($first));
        $this->assertFalse($result->is($second));
        $this->assertTrue($result->relationLoaded('details'));
        $this->assertTrue($result->details->contains($detail));
    }

    public function test_single_page_link_list_resolves_up_to_ten_top_page_view_pages_in_sort_order(): void
    {
        SinglePage::factory()->count(11)->create(['top_page_view' => true]);
        SinglePage::factory()->create(['top_page_view' => false]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'content_model_relation_id' => $this->relation('SinglePage')->id,
            'place' => CallContentPlace::Top,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(10, $result);
        $this->assertTrue($result->every(fn (SinglePage $page) => $page->top_page_view));
    }

    public function test_single_page_link_resolves_the_first_page_in_sort_order(): void
    {
        $second = SinglePage::factory()->create(['sort_order' => 1]);
        $first = SinglePage::factory()->create(['sort_order' => 0]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Link,
            'content_model_relation_id' => $this->relation('SinglePage')->id,
            'place' => CallContentPlace::Top,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertTrue($result->is($first));
        $this->assertFalse($result->is($second));
    }

    // --- UserDetail ---

    public function test_user_detail_link_list_resolves_viewable_details_up_to_view_count(): void
    {
        UserDetail::factory()->count(3)->create(['view_flag' => true]);
        UserDetail::factory()->create(['view_flag' => false]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'content_model_relation_id' => $this->relation('UserDetail')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn (UserDetail $detail) => $detail->view_flag));
    }

    public function test_user_detail_archive_resolves_viewable_details_up_to_view_count(): void
    {
        UserDetail::factory()->count(3)->create(['view_flag' => true]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Archive,
            'content_model_relation_id' => $this->relation('UserDetail')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(2, $result);
    }

    public function test_user_detail_skill_list_resolves_viewable_details_up_to_view_count(): void
    {
        UserDetail::factory()->count(3)->create(['view_flag' => true]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::SkillList,
            'content_model_relation_id' => $this->relation('UserDetail')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(2, $result);
    }

    // --- エラーケース ---

    public function test_resolve_throws_for_an_unknown_model_name(): void
    {
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'content_model_relation_id' => $this->relation('Recipe')->id,
            'place' => CallContentPlace::Top,
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CallContentResolver)->resolve($callContent);
    }

    public function test_resolve_throws_for_a_call_type_not_supported_by_the_model(): void
    {
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::ShortSentence,
            'content_model_relation_id' => $this->relation('UserDetail')->id,
            'place' => CallContentPlace::Top,
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CallContentResolver)->resolve($callContent);
    }

    public function test_resolve_throws_for_an_unsupported_place(): void
    {
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Inside,
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CallContentResolver)->resolve($callContent);
    }

    // --- resolveMany ---

    public function test_resolve_many_resolves_each_call_content_by_its_own_id(): void
    {
        $article = Article::factory()->published()->create();
        $singlePage = SinglePage::factory()->create();
        $articleCallContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Top,
        ]);
        $singlePageCallContent = CallContent::factory()->create([
            'call_type' => CallType::Link,
            'content_model_relation_id' => $this->relation('SinglePage')->id,
            'place' => CallContentPlace::Top,
        ]);

        $results = (new CallContentResolver)->resolveMany([$articleCallContent, $singlePageCallContent]);

        $this->assertTrue($results->get($articleCallContent->id)->is($article));
        $this->assertTrue($results->get($singlePageCallContent->id)->is($singlePage));
    }
}
