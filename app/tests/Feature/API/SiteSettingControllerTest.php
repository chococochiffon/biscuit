<?php

namespace Tests\Feature\API;

use App\Enums\SocialService;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_registered_site_setting(): void
    {
        $siteSetting = SiteSetting::factory()->create(['site_title' => 'テストサイト']);

        $response = $this->getJson(route('api.site-setting.show'));

        $response->assertOk();
        $response->assertJsonPath('data.site_title', $siteSetting->site_title);
    }

    public function test_show_includes_social_links_in_sort_order(): void
    {
        SiteSetting::factory()->create();
        SocialLink::factory()->create(['service' => SocialService::GitHub, 'name' => 'GitHub', 'url' => 'https://github.com/example', 'sort_order' => 1]);
        SocialLink::factory()->create(['service' => SocialService::YouTube, 'name' => 'YouTube', 'url' => 'https://www.youtube.com/@example', 'sort_order' => 0]);
        SocialLink::factory()->create(['sort_order' => 2])->delete();

        $response = $this->getJson(route('api.site-setting.show'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data.social_links');
        $response->assertJsonPath('data.social_links.0.service', 'youtube');
        $response->assertJsonPath('data.social_links.0.name', 'YouTube');
        $response->assertJsonPath('data.social_links.0.url', 'https://www.youtube.com/@example');
        $response->assertJsonPath('data.social_links.1.service', 'github');
    }

    public function test_show_returns_404_when_site_setting_is_not_registered(): void
    {
        $response = $this->getJson(route('api.site-setting.show'));

        $response->assertNotFound();
    }
}
