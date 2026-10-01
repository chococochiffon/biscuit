<?php

namespace Tests\Feature;

use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderValidator;
use Database\Seeders\PageBuilderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageBuilderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_a_published_sample_page_and_a_top_draft(): void
    {
        Storage::fake('public');

        $this->seed(PageBuilderSeeder::class);

        $singlePage = SinglePage::query()->where('slug', PageBuilderSeeder::SAMPLE_SLUG)->sole();
        $this->assertSame('/builder-sample', $singlePage->path);
        $this->assertTrue($singlePage->use_builder);

        $builder = $singlePage->builder;
        $this->assertTrue($builder->isPublished());
        $this->assertFalse($builder->hasUnpublishedChanges());
        $this->assertSame([], (new BuilderValidator)->errors($builder->published_content));

        $imagePaths = BuilderContent::imagePaths($builder->published_content);
        $this->assertCount(1, $imagePaths);
        Storage::disk('public')->assertExists($imagePaths);

        $top = PageBuilder::top();
        $this->assertFalse($top->isPublished());
        $this->assertNotSame([], $top->draft_content['children']);
        $this->assertSame([], (new BuilderValidator)->errors($top->draft_content));
    }

    public function test_reseeding_does_not_duplicate_and_removes_unreferenced_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(PageBuilder::IMAGE_DIRECTORY.'/old.png', 'old');

        $this->seed(PageBuilderSeeder::class);
        $draft = PageBuilder::top()->draft_content;
        $this->seed(PageBuilderSeeder::class);

        $this->assertSame(1, SinglePage::query()->where('slug', PageBuilderSeeder::SAMPLE_SLUG)->count());
        $this->assertSame(2, PageBuilder::query()->count());
        $this->assertSame($draft, PageBuilder::top()->draft_content);

        $files = Storage::disk('public')->files(PageBuilder::IMAGE_DIRECTORY);
        $this->assertSame(BuilderContent::imagePaths(SinglePage::query()->where('slug', PageBuilderSeeder::SAMPLE_SLUG)->sole()->builder->published_content), $files);
    }
}
