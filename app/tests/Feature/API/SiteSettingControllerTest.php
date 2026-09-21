<?php

namespace Tests\Feature\API;

use App\Models\SiteSetting;
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

    public function test_show_returns_404_when_site_setting_is_not_registered(): void
    {
        $response = $this->getJson(route('api.site-setting.show'));

        $response->assertNotFound();
    }
}
