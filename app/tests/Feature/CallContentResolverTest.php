<?php

namespace Tests\Feature;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\GalleryImage;
use App\Models\QuestionAnswer;
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
        Article::factory()->published()->create(['publication_start_datetime' => now()->subDays(2)]);
        // 作成日時が古くても、公開開始日時が新しい記事を最新とする
        $latest = Article::factory()->published()->create(['publication_start_datetime' => now()->subDay(), 'created_at' => now()->subDays(3)]);
        Article::factory()->create(['publication_start_datetime' => now()->subHour()]); // 下書きは対象外
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Inside,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertTrue($result->is($latest));
    }

    public function test_article_link_list_resolves_the_latest_published_articles_up_to_view_count(): void
    {
        $newest = Article::factory()->published()->create(['publication_start_datetime' => now()->subDay()]);
        $oldest = Article::factory()->published()->create(['publication_start_datetime' => now()->subDays(3)]);
        $middle = Article::factory()->published()->create(['publication_start_datetime' => now()->subDays(2)]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Others,
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
        $this->freezeSecond();
        // 公開開始日時が同じ記事は、id の大きい順(後から登録した順)に並べる
        [, $second, $third] = Article::factory()->published()->count(3)->create(['publication_start_datetime' => now()->subDay()])->all();
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Archive,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertSame([$third->id, $second->id], $result->pluck('id')->all());
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
            'place' => CallContentPlace::Inside,
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

    public function test_user_detail_archive_is_no_longer_supported(): void
    {
        UserDetail::factory()->count(3)->create(['view_flag' => true]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Archive,
            'content_model_relation_id' => $this->relation('UserDetail')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CallContentResolver)->resolve($callContent);
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

    // --- GalleryImage ---

    public function test_gallery_image_tile_list_resolves_images_in_sort_order_up_to_view_count(): void
    {
        $third = GalleryImage::factory()->create(['sort_order' => 2]);
        $first = GalleryImage::factory()->create(['sort_order' => 0]);
        $second = GalleryImage::factory()->create(['sort_order' => 1]);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::TileList,
            'content_model_relation_id' => $this->relation('GalleryImage')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertSame([$first->id, $second->id], $result->pluck('id')->all());
        $this->assertFalse($result->contains($third));
    }

    // --- QuestionAnswer ---

    public function test_question_answer_accordion_resolves_only_simple_question_answers_up_to_view_count(): void
    {
        $first = QuestionAnswer::factory()->create(['short_question_text' => '質問1', 'short_answer_text' => '回答1']);
        QuestionAnswer::factory()->create(['short_question_text' => null, 'short_answer_text' => null]); // 分岐ありは対象外
        $second = QuestionAnswer::factory()->create(['short_question_text' => '質問2', 'short_answer_text' => '回答2']);
        QuestionAnswer::factory()->create(['short_question_text' => '質問3', 'short_answer_text' => '回答3']);
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Accordion,
            'content_model_relation_id' => $this->relation('QuestionAnswer')->id,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
        ]);

        $result = (new CallContentResolver)->resolve($callContent);

        $this->assertSame([$first->id, $second->id], $result->pluck('id')->all());
    }

    public function test_tile_list_is_not_allowed_outside_top(): void
    {
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::TileList,
            'content_model_relation_id' => $this->relation('GalleryImage')->id,
            'place' => CallContentPlace::Others,
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CallContentResolver)->resolve($callContent);
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

    public function test_resolve_throws_for_a_call_type_not_supported_at_the_given_place(): void
    {
        // LinkはTop/Othersでのみ許可され、Inside(本文内)ではどのモデルでも許可されない。
        $callContent = CallContent::factory()->create([
            'call_type' => CallType::Link,
            'content_model_relation_id' => $this->relation('Article')->id,
            'place' => CallContentPlace::Inside,
        ]);

        $this->expectException(InvalidArgumentException::class);

        (new CallContentResolver)->resolve($callContent);
    }
}
