<?php

namespace Tests\Feature\API;

use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PageBuilderPublicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function content(string $heading, ?string $image = null): array
    {
        return BuilderContent::withDefaultLayout([
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [
                BuilderContent::node('section', children: [
                    BuilderContent::node('heading', ['text' => $heading]),
                    BuilderContent::node('image', ['src' => $image]),
                ]),
            ],
        ]);
    }

    private function publishedSinglePage(bool $useBuilder): SinglePage
    {
        $singlePage = SinglePage::factory()->create(['slug' => 'company', 'use_builder' => $useBuilder]);
        $builder = PageBuilder::factory()->create(['single_page_id' => $singlePage->id, 'draft_content' => $this->content('公開中の見出し', 'image/builder/a.png')]);
        $builder->publish();
        $builder->save();
        $builder->update(['draft_content' => $this->content('編集中の見出し')]);

        return $singlePage;
    }

    public function test_resolve_returns_the_published_builder_of_a_single_page_using_the_builder(): void
    {
        Storage::fake('public');
        $this->publishedSinglePage(useBuilder: true);

        $this->getJson(route('api.resolve', ['path' => '/company']))
            ->assertOk()
            ->assertJsonPath('type', 'single_page')
            ->assertJsonPath('data.builder.version', SchemaMigrator::CURRENT_VERSION)
            ->assertJsonPath('data.builder.children.0.children.0.props.text', '公開中の見出し')
            ->assertJsonPath('data.builder.children.0.children.1.props.src', Storage::disk('public')->url('image/builder/a.png'));
    }

    public function test_resolve_returns_null_builder_when_the_single_page_does_not_use_it_or_it_is_unpublished(): void
    {
        $this->publishedSinglePage(useBuilder: false);
        $unpublished = SinglePage::factory()->create(['slug' => 'draft-only', 'use_builder' => true]);
        PageBuilder::factory()->create(['single_page_id' => $unpublished->id]);

        $this->getJson(route('api.resolve', ['path' => '/company']))->assertOk()->assertJsonPath('data.builder', null);
        $this->getJson(route('api.resolve', ['path' => '/draft-only']))->assertOk()->assertJsonPath('data.builder', null);
    }

    public function test_resolve_returns_the_top_builder_only_when_the_site_setting_uses_it(): void
    {
        SiteSetting::factory()->create(['top_use_builder' => false]);
        PageBuilder::factory()->top()->published()->create(['draft_content' => $this->content('トップの見出し')]);

        $this->getJson(route('api.resolve', ['path' => '/']))->assertOk()->assertJsonPath('builder', null);

        SiteSetting::current()->update(['top_use_builder' => true]);

        $this->getJson(route('api.resolve', ['path' => '/']))
            ->assertOk()
            ->assertJsonPath('type', 'top')
            ->assertJsonPath('builder.children.0.children.0.props.text', 'トップの見出し');
    }

    public function test_preview_returns_the_draft_with_a_valid_signature(): void
    {
        $singlePage = $this->publishedSinglePage(useBuilder: false);
        $singlePage->update(['publication_start_datetime' => now()->addWeek()]);
        $url = URL::temporarySignedRoute('api.builder-previews.show', now()->addMinutes(30), ['pageBuilder' => $singlePage->builder->id], absolute: false);

        $this->getJson($url)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertJsonPath('type', 'single_page')
            ->assertJsonPath('data.title', $singlePage->title)
            ->assertJsonPath('data.builder.children.0.children.0.props.text', '編集中の見出し');
    }

    public function test_preview_of_the_top_returns_the_draft(): void
    {
        $builder = PageBuilder::factory()->top()->create(['draft_content' => $this->content('トップの下書き')]);
        $url = URL::temporarySignedRoute('api.builder-previews.show', now()->addMinutes(30), ['pageBuilder' => $builder->id], absolute: false);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('type', 'top')
            ->assertJsonPath('builder.children.0.children.0.props.text', 'トップの下書き');
    }

    public function test_preview_rejects_missing_tampered_or_expired_signatures(): void
    {
        $builder = PageBuilder::factory()->create();
        $other = PageBuilder::factory()->create();
        $url = URL::temporarySignedRoute('api.builder-previews.show', now()->addMinutes(30), ['pageBuilder' => $builder->id], absolute: false);

        $this->getJson('/api/builder-previews/'.$builder->id)->assertForbidden();
        $this->getJson(str_replace('/builder-previews/'.$builder->id.'?', '/builder-previews/'.$other->id.'?', $url))->assertForbidden();

        $this->travel(31)->minutes();
        $this->getJson($url)->assertForbidden();
    }

    public function test_preview_of_a_deleted_single_page_is_not_found(): void
    {
        $builder = PageBuilder::factory()->create();
        $builder->singlePage->delete();
        $url = URL::temporarySignedRoute('api.builder-previews.show', now()->addMinutes(30), ['pageBuilder' => $builder->id], absolute: false);

        $this->getJson($url)->assertNotFound();
    }
}
