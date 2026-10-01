<?php

namespace Tests\Feature;

use App\Models\SinglePage;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBuilderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_editor(): void
    {
        $singlePage = SinglePage::factory()->create();

        $this->get(route('admin.builder.top'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.builder.single-pages', $singlePage))->assertRedirect(route('admin.login'));
    }

    public function test_single_page_editor_passes_the_json_endpoints_to_the_vue_app(): void
    {
        $this->actingAsAdmin();
        $singlePage = SinglePage::factory()->create(['title' => '会社概要']);

        $response = $this->get(route('admin.builder.single-pages', $singlePage))->assertOk();

        $response->assertSee('<div id="page-builder"', false)
            ->assertSee('ページビルダー: 会社概要')
            ->assertSee('window.builderTranslations = ', false);

        $config = $this->configOf($response->getContent());
        $this->assertSame(route('admin.single-pages.edit', $singlePage), $config['backUrl']);
        $this->assertSame(route('admin.json.builder.single-pages.update', $singlePage), $config['endpoints']['update']);
        $this->assertSame(route('admin.json.builder.single-pages.preview-url', $singlePage), $config['endpoints']['previewUrl']);
        $this->assertSame(route('admin.json.builder.images'), $config['endpoints']['images']);
    }

    public function test_top_editor_goes_back_to_the_site_settings(): void
    {
        $this->actingAsAdmin();
        $siteSetting = SiteSetting::factory()->create();

        $config = $this->configOf($this->get(route('admin.builder.top'))->assertOk()->getContent());

        $this->assertSame(route('admin.site-settings.show', $siteSetting), $config['backUrl']);
        $this->assertSame(route('admin.json.builder.top.show'), $config['endpoints']['show']);
        $this->assertSame(route('admin.json.builder.top.publish'), $config['endpoints']['publish']);
    }

    public function test_editor_of_a_deleted_single_page_is_not_found(): void
    {
        $this->actingAsAdmin();
        $singlePage = SinglePage::factory()->create();
        $singlePage->delete();

        $this->get(route('admin.builder.single-pages', $singlePage))->assertNotFound();
    }

    public function test_single_page_list_and_form_link_to_the_editor(): void
    {
        $this->actingAsAdmin();
        $singlePage = SinglePage::factory()->create();
        $editorUrl = route('admin.builder.single-pages', $singlePage);

        $this->get(route('admin.single-pages.index'))->assertSee($editorUrl, false);
        $this->get(route('admin.single-pages.edit', $singlePage))->assertSee($editorUrl, false);
        $this->get(route('admin.dashboard'))->assertSee(route('admin.builder.top'), false);
    }

    /**
     * 画面が Vue のエディタに渡す設定(data-config)。
     *
     * @return array<string, mixed>
     */
    private function configOf(string $html): array
    {
        preg_match('/id="page-builder" data-config="([^"]*)"/', $html, $matches);

        return json_decode(html_entity_decode($matches[1]), true);
    }
}
