<?php

namespace Tests\Feature;

use App\Support\AdminJsTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminJsTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_text_used_by_admin_js_is_registered_and_has_an_english_translation(): void
    {
        preg_match_all("/\\bt\\('((?:[^'\\\\]|\\\\.)*)'/u", file_get_contents(resource_path('js/admin.js')), $matches);
        $usedKeys = array_unique($matches[1]);
        $english = json_decode(file_get_contents(lang_path('en.json')), true);

        $this->assertNotEmpty($usedKeys);
        // admin.js で t() に渡した文言は、すべて AdminJsTranslations::KEYS に登録されている
        $this->assertSame([], array_values(array_diff($usedKeys, AdminJsTranslations::KEYS)));
        // 登録した文言は、すべて英訳がある
        $this->assertSame([], array_values(array_diff(AdminJsTranslations::KEYS, array_keys($english))));
    }

    public function test_translated_texts_follow_the_current_language(): void
    {
        $this->assertSame('タグを削除', AdminJsTranslations::translated()['タグを削除']);

        app()->setLocale('en');

        $this->assertSame('Delete tag', AdminJsTranslations::translated()['タグを削除']);
        $this->assertSame('Are you sure you want to delete ":name"?', AdminJsTranslations::translated()['「:name」を削除してよろしいですか?']);
    }

    public function test_admin_layout_passes_translated_texts_before_loading_admin_js(): void
    {
        $this->actingAsAdmin();

        $this->withSession(['locale' => 'en'])
            ->get(route('admin.tags.index'))
            ->assertOk()
            ->assertSeeInOrder(['window.adminTranslations = ', 'Delete tag', 'assets/admin-'], false);
    }
}
