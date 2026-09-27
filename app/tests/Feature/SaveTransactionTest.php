<?php

namespace Tests\Feature;

use App\Enums\SocialService;
use App\Enums\UserDetailNameSetting;
use App\Models\Article;
use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\Tag;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * 複数のテーブルに書き込む保存処理が、途中で失敗したときに全体を取り消すこと(トランザクション)を確認する。
 * 後半で書き込むモデルの作成時にわざと例外を起こし、先に書き込んだ本体も保存されていないことを確かめる。
 */
class SaveTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_site_setting_update_is_rolled_back_when_syncing_rows_fails(): void
    {
        $siteSetting = SiteSetting::factory()->create(['site_title' => '変更前']);
        SocialLink::creating(fn () => throw new RuntimeException('保存に失敗'));

        $this->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => '変更後',
            'social_links' => [['service' => SocialService::GitHub->value, 'name' => 'GitHub', 'url' => 'https://github.com/example']],
        ])->assertServerError();

        $this->assertSame('変更前', $siteSetting->fresh()->site_title);
    }

    public function test_user_store_is_rolled_back_when_saving_skills_fails(): void
    {
        UserSkill::creating(fn () => throw new RuntimeException('保存に失敗'));

        $this->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => 'rollback@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => [
                'first_name' => '太郎',
                'family_name' => '検証',
                'nick_name' => 'たろう',
                'birthday' => '2000-01-01',
                'name_settings' => UserDetailNameSetting::FullName->value,
                'skills' => [['name' => 'PHP', 'level' => 50]],
            ],
        ])->assertServerError();

        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
        $this->assertDatabaseCount('user_details', 0);
    }

    public function test_single_page_store_is_rolled_back_when_saving_details_fails(): void
    {
        SinglePageDetail::creating(fn () => throw new RuntimeException('保存に失敗'));

        $this->post(route('admin.single-pages.store'), [
            'title' => '会社概要',
            'short_sentences' => '会社の概要ページです',
            'slug' => 'about',
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
            'details' => [['sub_title' => '沿革', 'contents' => '<p>本文</p>']],
        ])->assertServerError();

        $this->assertSame(0, SinglePage::count());
    }

    public function test_article_store_is_rolled_back_when_saving_tags_fails(): void
    {
        Tag::creating(fn () => throw new RuntimeException('保存に失敗'));

        $this->post(route('admin.articles.store'), [
            'title' => 'ロールバック確認',
            'content' => '<p>本文</p>',
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
            'tags' => ['新しいタグ'],
        ])->assertServerError();

        $this->assertSame(0, Article::count());
    }
}
