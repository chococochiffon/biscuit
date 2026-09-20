<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_site_setting_pages(): void
    {
        $response = $this->get(route('admin.site-settings.create'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.site-settings.create'));

        $response->assertOk();
    }

    public function test_store_creates_site_setting(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'description' => 'サイトの説明文',
        ]);

        $siteSetting = SiteSetting::where('site_title', 'テストサイト')->firstOrFail();
        $response->assertRedirect(route('admin.site-settings.show', $siteSetting));
        $this->assertSame('サイトの説明文', $siteSetting->description);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), []);

        $response->assertSessionHasErrors(['site_title']);
    }

    public function test_store_uploads_site_icon_and_site_image_with_expected_filenames(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $actor = Administrator::factory()->create();
        $icon = UploadedFile::fake()->image('icon.png');
        $image = UploadedFile::fake()->image('image.jpg');

        $response = $this->actingAs($actor, 'admin')->post(route('admin.site-settings.store'), [
            'site_title' => '画像付きサイト',
            'site_icon' => $icon,
            'site_image' => $image,
        ]);

        $siteSetting = SiteSetting::where('site_title', '画像付きサイト')->firstOrFail();
        $response->assertRedirect(route('admin.site-settings.show', $siteSetting));

        $expectedIconPath = 'image/site_icon/'.now()->format('YmdHis').'_site_settings_'.$siteSetting->id.'.png';
        $expectedImagePath = 'image/site_image/'.now()->format('YmdHis').'_site_settings_'.$siteSetting->id.'.jpg';
        $this->assertSame($expectedIconPath, $siteSetting->site_icon);
        $this->assertSame($expectedImagePath, $siteSetting->site_image);
        Storage::disk('public')->assertExists($expectedIconPath);
        Storage::disk('public')->assertExists($expectedImagePath);
    }

    public function test_show_displays_site_setting(): void
    {
        $actor = Administrator::factory()->create();
        $siteSetting = SiteSetting::factory()->create(['site_title' => '表示確認サイト']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.site-settings.show', $siteSetting));

        $response->assertOk();
        $response->assertSee('表示確認サイト');
    }
}
