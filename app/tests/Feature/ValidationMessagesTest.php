<?php

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_are_shown_in_japanese_with_form_labels(): void
    {
        $this->actingAsAdmin();
        Tag::factory()->create(['tag_name' => '重複']);

        $this->post(route('admin.tags.store'), [])
            ->assertSessionHasErrors(['tag_name' => 'タグ名を入力してください。']);

        $this->post(route('admin.tags.store'), ['tag_name' => '重複'])
            ->assertSessionHasErrors(['tag_name' => 'そのタグ名はすでに使われています。']);
    }

    public function test_rows_of_repeated_inputs_get_labels_regardless_of_the_row_number(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.site-settings.store'), [
            'site_title' => 'テストサイト',
            'social_links' => [3 => ['service' => 1, 'name' => 'GitHub', 'url' => 'not-a-url']],
        ])->assertSessionHasErrors(['social_links.3.url' => 'SNSリンクのURLには有効なURLを入力してください。']);
    }

    public function test_validation_errors_stay_in_english_when_the_language_is_english(): void
    {
        $this->actingAsAdmin();

        $this->withSession(['locale' => 'en'])
            ->post(route('admin.tags.store'), [])
            ->assertSessionHasErrors(['tag_name' => 'The tag name field is required.']);
    }

    public function test_failed_login_and_pagination_are_shown_in_japanese(): void
    {
        $this->assertSame('メールアドレスまたはパスワードが正しくありません。', __('auth.failed'));
        $this->assertSame('&laquo; 前へ', __('pagination.previous'));
        $this->assertSame('次へ &raquo;', __('pagination.next'));
    }
}
