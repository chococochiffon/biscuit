<?php

namespace Tests\Feature;

use App\Support\PageBuilderJsTranslations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PageBuilderJsTranslationsTest extends TestCase
{
    public function test_every_text_used_by_the_editor_is_registered_and_has_an_english_translation(): void
    {
        // ページビルダーのエディタ(resources/js/builder の .ts・.vue。テストは除く)を対象にする
        $source = collect(File::allFiles(resource_path('js/builder')))
            ->filter(fn ($file) => in_array($file->getExtension(), ['ts', 'vue'], true) && ! str_ends_with($file->getFilename(), '.test.ts'))
            ->map(fn ($file) => $file->getContents())
            ->implode("\n");
        preg_match_all("/\\bt\\('((?:[^'\\\\]|\\\\.)*)'/u", $source, $matches);
        $usedKeys = array_unique(array_map('stripslashes', $matches[1]));
        $english = json_decode(file_get_contents(lang_path('en.json')), true);

        $this->assertNotEmpty($usedKeys);
        $this->assertSame([], array_values(array_diff($usedKeys, PageBuilderJsTranslations::KEYS)));
        $this->assertSame([], array_values(array_diff(PageBuilderJsTranslations::KEYS, array_keys($english))));
    }

    public function test_translated_texts_follow_the_current_language(): void
    {
        $this->assertSame('下書き保存', PageBuilderJsTranslations::translated()['下書き保存']);

        app()->setLocale('en');

        $this->assertSame('Save draft', PageBuilderJsTranslations::translated()['下書き保存']);
    }
}
