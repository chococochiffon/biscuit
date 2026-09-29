<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Enums\AuditAction;
use App\Enums\CustomFormType;
use App\Enums\CustomPageBaseType;
use App\Enums\LayoutPageType;
use App\Enums\SidebarPosition;
use App\Enums\SocialService;
use App\Models\Administrator;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\CustomPages\CustomForm;
use App\Models\CustomPageType;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\Tag;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CustomPages\CustomPageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * 管理画面の操作が、監査ログ(誰が・いつ・何に・何をしたか)に残ることを確認する。
 */
class AuditLogRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function latestLog(): AuditLog
    {
        return AuditLog::query()->newest()->firstOrFail();
    }

    public function test_article_create_update_and_delete_are_recorded_with_changes(): void
    {
        $administrator = $this->actingAsAdmin(['name' => '管理者A']);

        $this->withHeader('User-Agent', 'TestBrowser/1.0')->post(route('admin.articles.store'), [
            'title' => '最初の記事',
            'content' => '<p>本文</p>',
            'publication_start_datetime' => '2026-10-01 10:00',
            'tags' => ['お知らせ'],
        ])->assertSessionHasNoErrors();

        $article = Article::query()->sole();
        $created = $this->latestLog();
        $this->assertSame(AuditAction::Created, $created->action);
        $this->assertSame(['administrator', $administrator->id, '管理者A'], [$created->actor_type, $created->actor_id, $created->actor_name]);
        $this->assertSame(['article', $article->id, '最初の記事'], [$created->subject_type, $created->subject_id, $created->subject_label]);
        $this->assertSame([null, '最初の記事'], $created->changes['title']);
        $this->assertSame([null, 'お知らせ'], $created->changes['tags']);
        $this->assertSame('admin.articles.store', $created->route_name);
        $this->assertSame('TestBrowser/1.0', $created->user_agent);
        $this->assertSame('127.0.0.1', $created->ip_address);

        $this->put(route('admin.articles.update', $article), [
            'title' => '変更した記事',
            'content' => '<p>本文</p>',
            'approval' => ArticleApprovalStatus::Published->value,
            'publication_start_datetime' => '2026-10-01 10:00',
            'tags' => ['お知らせ', 'リリース'],
        ])->assertSessionHasNoErrors();

        $updated = $this->latestLog();
        $this->assertSame(AuditAction::Updated, $updated->action);
        // 変わった項目だけを残す
        $this->assertSame(['title', 'tags'], array_keys($updated->changes));
        $this->assertSame(['最初の記事', '変更した記事'], $updated->changes['title']);
        $this->assertSame(['お知らせ', 'お知らせ, リリース'], $updated->changes['tags']);

        $this->delete(route('admin.articles.destroy', $article));

        $deleted = $this->latestLog();
        $this->assertSame(AuditAction::Deleted, $deleted->action);
        $this->assertSame(['変更した記事', null], $deleted->changes['title']);
    }

    public function test_long_values_are_truncated_and_secrets_are_not_recorded(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->post(route('admin.articles.store'), [
            'title' => '長い記事',
            'content' => str_repeat('あ', AuditLogger::MAX_VALUE_LENGTH + 500),
            'publication_start_datetime' => '2026-10-01 10:00',
        ]);
        $this->assertSame(AuditLogger::MAX_VALUE_LENGTH + 3, mb_strlen($this->latestLog()->changes['content'][1]));

        $before = AuditLogger::snapshot($user);
        $this->assertArrayNotHasKey('password', $before);
        $this->assertArrayNotHasKey('remember_token', $before);
        $this->assertArrayNotHasKey('unique_email', $before);
    }

    public function test_administrator_password_change_is_recorded_without_the_password(): void
    {
        $this->actingAsSuperAdmin();
        $target = Administrator::factory()->create(['name' => '変更前']);

        $this->put(route('admin.update', $target), [
            'name' => '変更後',
            'email' => $target->email,
            'role' => $target->role->value,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();

        $log = $this->latestLog();
        $this->assertSame(['name'], array_keys($log->changes));
        $this->assertTrue($log->metadata['password_changed']);
        $this->assertStringNotContainsString('new-password-123', json_encode($log->toArray()));
    }

    public function test_approval_changes_are_recorded_for_each_article_in_bulk(): void
    {
        $this->actingAsAdmin();
        $articles = Article::factory()->count(2)->create(['approval' => ArticleApprovalStatus::Draft]);

        $this->patch(route('admin.articles.bulk-approval'), [
            'article_ids' => $articles->pluck('id')->all(),
            'approval' => ArticleApprovalStatus::Published->value,
        ]);

        $logs = AuditLog::query()->where('action', AuditAction::StatusChanged)->orderBy('subject_id')->get();
        $this->assertSame($articles->pluck('id')->all(), $logs->pluck('subject_id')->all());
        $this->assertSame(
            [(string) ArticleApprovalStatus::Draft->value, (string) ArticleApprovalStatus::Published->value],
            $logs->first()->changes['approval']
        );
        $this->assertSame(2, $logs->first()->metadata['bulk_count']);
    }

    public function test_reorder_is_recorded_once_with_the_order(): void
    {
        $this->actingAsAdmin();
        $pages = SinglePage::factory()->count(2)->create();

        $this->patch(route('admin.single-pages.reorder'), ['order' => [$pages[1]->id, $pages[0]->id]]);

        $log = $this->latestLog();
        $this->assertSame(AuditAction::Reordered, $log->action);
        $this->assertSame('single_page', $log->subject_type);
        $this->assertSame([$pages[1]->id, $pages[0]->id], $log->metadata['order']);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_nested_rows_are_summarized_in_metadata(): void
    {
        $this->actingAsAdmin();
        $siteSetting = SiteSetting::factory()->create();
        $kept = SocialLink::factory()->create();
        SocialLink::factory()->create();

        $this->put(route('admin.site-settings.update', $siteSetting), [
            'site_title' => $siteSetting->site_title,
            'social_links' => [
                ['id' => $kept->id, 'service' => SocialService::GitHub->value, 'name' => 'GitHub', 'url' => 'https://github.com/example'],
                ['service' => SocialService::X->value, 'name' => 'X', 'url' => 'https://x.com/example'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['created' => 1, 'updated' => 1, 'deleted' => 1], $this->latestLog()->metadata['social_links']);
    }

    public function test_layout_update_records_page_setting_changes(): void
    {
        $this->actingAsAdmin();
        $layouts = collect(LayoutPageType::cases())
            ->mapWithKeys(fn (LayoutPageType $pageType) => [$pageType->value => ['sidebar_position' => SidebarPosition::None->value, 'show_breadcrumbs' => '1']])
            ->all();
        $layouts[LayoutPageType::Article->value]['sidebar_position'] = SidebarPosition::Right->value;

        $this->put(route('admin.layouts.update'), ['layouts' => $layouts])->assertSessionHasNoErrors();

        $log = $this->latestLog();
        $this->assertSame('layout', $log->subject_type);
        $this->assertSame(['none', 'right'], $log->changes['article.sidebar_position']);
        // トップのパンくずは初期値(表示しない)から表示するに変えた
        $this->assertSame(['0', '1'], $log->changes['top.show_breadcrumbs']);
    }

    public function test_custom_page_entry_records_custom_field_values(): void
    {
        $this->actingAsSuperAdmin();
        $type = CustomPageType::factory()->create(['name' => 'recipe', 'base_type' => CustomPageBaseType::Article]);
        (new CustomPageSchema)->create($type);
        $form = CustomForm::queryFor($type)->create(['parts_name' => '材料', 'customs_form_type' => CustomFormType::Text, 'sort_order' => 0]);

        $this->post(route('admin.custom-pages.entries.store', $type), [
            'title' => '肉じゃが',
            'content' => '<p>作り方</p>',
            'approval' => ArticleApprovalStatus::Published->value,
            'publication_start_datetime' => '2026-10-01 10:00',
            'custom_fields' => [$form->id => 'じゃがいも'],
        ])->assertSessionHasNoErrors();

        $log = $this->latestLog();
        $this->assertSame('custom_page:recipe', $log->subject_type);
        $this->assertSame([null, 'じゃがいも'], $log->changes['field.材料']);
    }

    public function test_deleting_a_gallery_category_records_uncategorized_images(): void
    {
        $this->actingAsAdmin();
        $category = GalleryCategory::factory()->create(['name' => 'イラスト']);
        GalleryImage::factory()->count(2)->create(['gallery_category_id' => $category->id]);

        $this->deleteJson(route('admin.gallery-categories.destroy', $category))->assertNoContent();

        $log = $this->latestLog();
        $this->assertSame(['gallery_category', 'イラスト'], [$log->subject_type, $log->subject_label]);
        $this->assertSame(2, $log->metadata['uncategorized_images']);
    }

    public function test_content_image_upload_is_recorded(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('admin.articles.content-images'), ['image' => UploadedFile::fake()->image('photo.png')])->assertOk();

        $log = $this->latestLog();
        $this->assertSame([AuditAction::Uploaded, 'article_content_image'], [$log->action, $log->subject_type]);
        $this->assertStringStartsWith('image/content/', $log->subject_label);
    }

    public function test_login_logout_and_failed_login_are_recorded(): void
    {
        $administrator = Administrator::factory()->create(['name' => '管理者B', 'password' => Hash::make('password')]);

        $this->post(route('admin.login.store'), ['email' => $administrator->email, 'password' => 'wrong-password']);
        $failed = $this->latestLog();
        $this->assertSame(AuditAction::LoginFailed, $failed->action);
        $this->assertNull($failed->actor_id);
        $this->assertSame(['email' => $administrator->email], $failed->metadata);
        $this->assertStringNotContainsString('wrong-password', json_encode($failed->toArray()));

        $this->post(route('admin.login.store'), ['email' => $administrator->email, 'password' => 'password']);
        $login = $this->latestLog();
        $this->assertSame([AuditAction::Login, $administrator->id, '管理者B'], [$login->action, $login->actor_id, $login->actor_name]);

        $this->post(route('admin.logout'));
        $this->assertSame(AuditAction::Logout, $this->latestLog()->action);
    }

    public function test_failed_operations_are_not_recorded(): void
    {
        $this->actingAsAdmin();
        Tag::creating(fn () => throw new RuntimeException('保存に失敗'));

        $this->post(route('admin.tags.store'), ['tag_name' => '失敗するタグ'])->assertServerError();

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_audit_logs_cannot_be_changed_or_deleted(): void
    {
        $log = AuditLogger::record(AuditAction::Created, 'tag', label: '記録');

        $this->assertThrows(fn () => $log->update(['subject_label' => '改ざん']), LogicException::class);
        $this->assertThrows(fn () => $log->delete(), LogicException::class);
        $this->assertSame('記録', $log->fresh()->subject_label);
    }
}
