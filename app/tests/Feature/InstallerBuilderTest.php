<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\BuilderPageType;
use App\Http\Controllers\PageBuilderJsonController;
use App\Http\Middleware\Installer\RedirectIfNotInstalled;
use App\Installer\InstallationState;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Models\Administrator;
use App\Models\AuditLog;
use App\Models\LayoutBlock;
use App\Models\PageBuilder;
use App\Models\SiteSetting;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Database\Seeders\InstallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * インストーラーのデザインの段のビルダー(最初の管理者を作ったセッションだけが使え、インストールを終えたら入れない)。
 */
class InstallerBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Kq7-zR2_mW9!xP4';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config(['installer.assume_installed' => false]);
        $this->seed(InstallSeeder::class);
        SiteSetting::query()->create(['site_title' => 'ビスケット商店', 'front_url' => 'https://example.com', 'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH, 'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH]);

        $state = app(InstallationState::class);
        foreach (InstallerStep::Administrator->previous() as $step) {
            $state->markCompleted($step);
        }
    }

    /**
     * 管理者の段で最初の管理者を作る(このセッションはビルダーを使える)。
     */
    private function createAdministrator(): Administrator
    {
        $this->post(route('installer.administrator.store'), [
            'name' => 'オーナー', 'email' => 'owner@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('installer.design'));

        return Administrator::query()->sole();
    }

    /**
     * @return array<string, mixed>
     */
    private function content(string $heading = 'ようこそ'): array
    {
        return BuilderContent::withDefaultLayout([
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [BuilderContent::node('section', children: [BuilderContent::node('heading', ['text' => $heading])])],
        ]);
    }

    public function test_the_session_that_created_the_administrator_can_build_and_publish_the_top_page(): void
    {
        $administrator = $this->createAdministrator();

        $this->get(route('installer.design'))->assertOk()->assertSee('data-open-builder', false)->assertDontSee('data-design-unlock', false);

        // 管理画面と同じエディタを、インストーラーの JSON の URL で開く(公開・版の履歴・書き出しなどは出さない)
        $config = json_decode(html_entity_decode((string) str($this->get(route('installer.design.builder'))->assertOk()->getContent())->match('/data-config="([^"]+)"/')), true);
        $this->assertSame(route('installer.design.builder.json.update'), $config['endpoints']['update']);
        $this->assertNull($config['endpoints']['publish']);
        $this->assertNull($config['endpoints']['versions']);
        $this->assertNull($config['endpoints']['export']);
        $this->assertFalse($config['canSaveTemplates']);

        $this->getJson(route('installer.design.builder.json.show'))->assertOk()->assertJsonPath('page.type', 'top')->assertJsonPath('can_edit_css', false);
        $this->getJson(route('installer.design.builder.json.templates'))->assertOk()->assertJsonCount(3);
        $this->putJson(route('installer.design.builder.json.update'), ['content' => $this->content()])->assertOk();
        $this->postJson(route('installer.design.builder.json.images'), ['image' => UploadedFile::fake()->image('hero.png', 800, 400)])->assertCreated();
        $this->getJson(route('installer.design.builder.json.preview-url'))->assertOk()->assertJsonPath('url', fn (string $url) => str_starts_with($url, 'https://example.com/builder-preview?'));

        // 下書きがあると、デザインの段にプレビューを出す
        $this->get(route('installer.design'))->assertOk()->assertSee('data-design-preview', false);

        $this->post(route('installer.design.store'), ['design' => 'builder'])->assertRedirect(route('installer.finalize'));

        $builder = PageBuilder::top();
        $this->assertTrue($builder->isPublished());
        $this->assertSame('ようこそ', $builder->published_content['children'][0]['children'][0]['props']['text']);
        $this->assertSame($administrator->id, $builder->versions()->sole()->administrator_id);
        $this->assertTrue(SiteSetting::current()->top_use_builder);
        $this->assertTrue(LayoutBlock::query()->exists());
        // 操作ログは、作った管理者の操作として残す
        $this->assertSame($administrator->id, AuditLog::query()->where('action', AuditAction::Published)->sole()->actor_id);
        $this->assertSame($administrator->id, AuditLog::query()->where('action', AuditAction::Uploaded)->sole()->actor_id);
    }

    public function test_custom_css_cannot_be_written_in_the_installer(): void
    {
        $this->createAdministrator();
        $content = $this->content();
        $content['css'] = '.page-builder h1 { color: red; }';

        $this->putJson(route('installer.design.builder.json.update'), ['content' => $content])->assertOk();

        $this->assertArrayNotHasKey('css', PageBuilder::top()->draft_content);
    }

    public function test_other_sessions_cannot_use_the_builder_until_unlocked(): void
    {
        $this->createAdministrator();
        $this->flushSession();

        $this->get(route('installer.design'))->assertOk()->assertSee('data-design-unlock', false)->assertDontSee('data-open-builder', false);
        $this->get(route('installer.design.builder'))->assertRedirect(route('installer.design'));
        $this->putJson(route('installer.design.builder.json.update'), ['content' => $this->content()])->assertForbidden();
        $this->postJson(route('installer.design.builder.json.images'), ['image' => UploadedFile::fake()->image('a.png')])->assertForbidden();
        $this->post(route('installer.design.store'), ['design' => 'builder'])->assertRedirect(route('installer.design', ['design' => 'builder']));
        $this->assertNull(PageBuilder::top());

        // 違うパスワードでは解除できず、作った管理者のメールアドレスとパスワードなら解除できる
        $this->post(route('installer.design.unlock'), ['email' => 'owner@example.com', 'password' => 'wrong-password'])->assertSessionHas('error');
        $this->get(route('installer.design.builder'))->assertRedirect(route('installer.design'));

        $this->post(route('installer.design.unlock'), ['email' => 'OWNER@example.com', 'password' => self::PASSWORD])->assertRedirect(route('installer.design', ['design' => 'builder']));
        $this->get(route('installer.design.builder'))->assertOk();
    }

    public function test_unlocking_is_rate_limited(): void
    {
        $this->createAdministrator();
        $this->flushSession();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('installer.design.unlock'), ['email' => 'owner@example.com', 'password' => 'wrong-password']);
        }

        $this->post(route('installer.design.unlock'), ['email' => 'owner@example.com', 'password' => self::PASSWORD])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, '試行の回数が多すぎます'));
        $this->get(route('installer.design.builder'))->assertRedirect(route('installer.design'));
    }

    public function test_the_builder_cannot_be_published_while_empty(): void
    {
        $this->createAdministrator();

        $this->post(route('installer.design.store'), ['design' => 'builder'])
            ->assertRedirect(route('installer.design', ['design' => 'builder']))
            ->assertSessionHas('error');
        $this->assertFalse(app(InstallationState::class)->isCompleted(InstallerStep::Design));
    }

    public function test_choosing_later_uses_the_default_design(): void
    {
        $this->createAdministrator();

        $this->post(route('installer.design.store'), ['design' => 'default'])->assertRedirect(route('installer.finalize'));

        $this->assertTrue(PageBuilder::top()->isPublished());
        $this->assertTrue(app(InstallationState::class)->isCompleted(InstallerStep::Design));
    }

    public function test_the_builder_is_closed_after_the_installation(): void
    {
        $this->createAdministrator();
        $this->putJson(route('installer.design.builder.json.update'), ['content' => $this->content()])->assertOk();
        app(InstallerManager::class)->lock();

        $this->get(route('installer.design.builder'))->assertRedirect(route('admin.login'));
        $this->putJson(route('installer.design.builder.json.update'), ['content' => $this->content('書き換え')])->assertNotFound();
        $this->post(route('installer.design.unlock'), ['email' => 'owner@example.com', 'password' => self::PASSWORD])->assertRedirect(route('admin.login'));
        $this->assertSame('ようこそ', PageBuilder::top()->draft_content['children'][0]['children'][0]['props']['text']);
    }

    public function test_only_the_preview_can_read_the_api_during_the_installation(): void
    {
        $builder = PageBuilder::newEmpty(BuilderPageType::Top);
        $builder->draft_content = $this->content();
        $builder->save();
        $query = [];
        parse_str((string) parse_url(PageBuilderJsonController::signedPreviewUrl($builder)['url'], PHP_URL_QUERY), $query);
        $headers = [
            RedirectIfNotInstalled::PREVIEW_ID_HEADER => $query['id'],
            RedirectIfNotInstalled::PREVIEW_EXPIRES_HEADER => $query['expires'],
            RedirectIfNotInstalled::PREVIEW_SIGNATURE_HEADER => $query['signature'],
        ];

        // 署名のない公開側の表示は 503(chococo は「準備中」を出す)
        $this->getJson(route('api.site-setting.show'))->assertStatus(503);
        $this->getJson(route('api.site-setting.show'), [...$headers, RedirectIfNotInstalled::PREVIEW_SIGNATURE_HEADER => 'invalid'])->assertStatus(503);

        // プレビューは、署名付きのプレビューの API と、署名をヘッダーで付けたサイト設定・レイアウトを読める
        $this->getJson(route('api.builder-previews.show', ['pageBuilder' => $builder->id, 'expires' => $query['expires'], 'signature' => $query['signature']]))->assertOk();
        $this->getJson(route('api.site-setting.show'), $headers)->assertOk()->assertJsonPath('data.site_title', 'ビスケット商店');
        $this->getJson(route('api.layout.show'), $headers)->assertOk();

        // 書き込みは署名があっても止める
        $this->postJson(route('api.page-views.store'), ['path' => '/'], $headers)->assertStatus(503);

        // 期限が切れた署名は通さない
        $this->travel(PageBuilderJsonController::PREVIEW_EXPIRE_MINUTES + 1)->minutes();
        $this->getJson(route('api.site-setting.show'), $headers)->assertStatus(503);
        $this->travelBack();

        // アプリケーションの段を終えるまでは、署名があっても止める
        Storage::fake('local');
        app(InstallationState::class)->markCompleted(InstallerStep::Requirements);
        $this->getJson(route('api.site-setting.show'), $headers)->assertStatus(503);
        $this->get(route('admin.login'))->assertRedirect(route('installer.requirements'));
    }
}
