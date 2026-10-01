<?php

namespace Tests\Feature;

use App\Models\PageBuilderTemplate;
use App\Support\Builder\BuilderValidator;
use Database\Seeders\PageBuilderTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBuilderTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_valid_templates_only_once(): void
    {
        $this->seed(PageBuilderTemplateSeeder::class);
        $this->seed(PageBuilderTemplateSeeder::class);

        $templates = PageBuilderTemplate::query()->orderBy('id')->get();
        $this->assertSame(['ランディングページ', '会社概要', 'お問い合わせ'], $templates->pluck('name')->all());

        foreach ($templates as $template) {
            $this->assertSame([], (new BuilderValidator)->errors($template->content), $template->name);
        }
    }

    public function test_does_not_bring_back_templates_deleted_by_administrators(): void
    {
        $this->seed(PageBuilderTemplateSeeder::class);
        PageBuilderTemplate::query()->get()->each->delete();

        $this->seed(PageBuilderTemplateSeeder::class);

        $this->assertSame(0, PageBuilderTemplate::query()->count());
    }
}
