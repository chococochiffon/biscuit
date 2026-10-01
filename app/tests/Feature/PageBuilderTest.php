<?php

namespace Tests\Feature;

use App\Enums\BuilderPageType;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_active_builder_per_single_page(): void
    {
        $builder = PageBuilder::factory()->create();

        $this->expectException(QueryException::class);

        PageBuilder::factory()->create(['single_page_id' => $builder->single_page_id]);
    }

    public function test_a_new_builder_can_be_created_after_the_old_one_is_soft_deleted(): void
    {
        $builder = PageBuilder::factory()->create();
        $builder->delete();

        $newBuilder = PageBuilder::factory()->create(['single_page_id' => $builder->single_page_id]);

        $this->assertTrue($newBuilder->exists);
        $this->assertTrue($newBuilder->singlePage->builder->is($newBuilder));
    }

    public function test_only_one_active_builder_for_the_top(): void
    {
        $top = PageBuilder::factory()->top()->create();

        $this->assertTrue(PageBuilder::top()->is($top));
        $this->assertNull($top->singlePage);

        $this->expectException(QueryException::class);

        PageBuilder::factory()->top()->create();
    }

    public function test_builders_of_different_single_pages_can_coexist_with_the_top(): void
    {
        PageBuilder::factory()->top()->create();
        PageBuilder::factory()->count(2)->create();

        $this->assertSame(3, PageBuilder::query()->count());
    }

    public function test_new_empty_builder_has_an_empty_draft(): void
    {
        $singlePage = SinglePage::factory()->create();

        $builder = PageBuilder::newEmpty(BuilderPageType::SinglePage, $singlePage);
        $builder->save();

        $this->assertSame(BuilderContent::empty(), $builder->fresh()->draft_content);
        $this->assertSame(SchemaMigrator::CURRENT_VERSION, $builder->schema_version);
        $this->assertFalse($builder->isPublished());
        $this->assertFalse($builder->hasUnpublishedChanges());
    }

    public function test_publish_copies_the_draft_and_tracks_unpublished_changes(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(9, 0));
        $builder = PageBuilder::factory()->create();

        $this->assertTrue($builder->hasUnpublishedChanges());

        $builder->publish();
        $builder->save();
        $builder->refresh();

        $this->assertSame($builder->draft_content, $builder->published_content);
        $this->assertSame('2026-10-06 09:00:00', $builder->published_at->format('Y-m-d H:i:s'));
        $this->assertFalse($builder->hasUnpublishedChanges());

        $draft = $builder->draft_content;
        $draft['children'][0]['children'][0]['props']['text'] = '変更した見出し';
        $builder->update(['draft_content' => $draft]);

        $this->assertTrue($builder->fresh()->hasUnpublishedChanges());
        $this->assertNotSame('変更した見出し', $builder->fresh()->published_content['children'][0]['children'][0]['props']['text']);
    }

    public function test_content_is_stored_as_readable_json(): void
    {
        $builder = PageBuilder::factory()->create([
            'draft_content' => [
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [BuilderContent::node('section', children: [BuilderContent::node('image', ['src' => 'image/builder/a.png', 'alt' => '画像'])])],
            ],
        ]);

        $raw = DB::table('page_builders')->where('id', $builder->id)->value('draft_content');

        // メディア状況が「image/…」を拾えるよう、/ と日本語をエスケープしない
        $this->assertStringContainsString('"src":"image/builder/a.png"', $raw);
        $this->assertStringContainsString('"alt":"画像"', $raw);
        $this->assertSame(['image/builder/a.png'], BuilderContent::imagePaths($builder->draft_content));
    }

    public function test_store_image_shrinks_large_images_with_a_random_name(): void
    {
        Storage::fake('public');

        $path = PageBuilder::storeImage(UploadedFile::fake()->image('large.png', 3840, 1080));

        $this->assertMatchesRegularExpression('#\Aimage/builder/[A-Za-z0-9]{40}\.png\z#', $path);
        $this->assertMatchesRegularExpression(BuilderContent::IMAGE_PATH_PATTERN, $path);
        $this->assertSame([1920, 540], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
    }
}
