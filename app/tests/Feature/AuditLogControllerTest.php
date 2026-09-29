<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admins_can_view_audit_logs(): void
    {
        $this->get(route('admin.audit-logs.index'))->assertRedirect(route('admin.login'));

        $this->actingAsAdmin();
        $log = AuditLogger::record(AuditAction::Created, 'tag', label: 'お知らせ');

        $this->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->get(route('admin.audit-logs.show', $log))->assertForbidden();
    }

    public function test_index_lists_logs_newest_first_and_filters_them(): void
    {
        $administrator = $this->actingAsSuperAdmin(['name' => '管理者A']);
        $this->travelTo('2026-09-01 10:00:00');
        AuditLogger::record(AuditAction::Created, 'tag', label: '古いタグ');
        $this->travelTo('2026-09-30 10:00:00');
        AuditLogger::record(AuditAction::Deleted, 'article', label: '新しい記事');
        AuditLogger::record(AuditAction::LoginFailed, metadata: ['email' => 'someone@example.com']);

        $this->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSeeInOrder(['someone@example.com', '新しい記事', '古いタグ']);

        $this->get(route('admin.audit-logs.index', ['from' => '2026-09-15', 'action' => AuditAction::Deleted->value]))
            ->assertSee('新しい記事')
            ->assertDontSee('古いタグ')
            ->assertDontSee('someone@example.com');

        $this->get(route('admin.audit-logs.index', ['actor' => $administrator->id, 'subject_type' => 'tag']))
            ->assertSee('古いタグ')
            ->assertDontSee('新しい記事');

        // 不正な値は無視する
        $this->get(route('admin.audit-logs.index', ['from' => 'invalid', 'action' => 'unknown']))
            ->assertOk()
            ->assertSee('古いタグ');
    }

    public function test_show_displays_changes_and_metadata(): void
    {
        $this->actingAsSuperAdmin();
        $log = AuditLogger::record(
            AuditAction::Updated,
            'article',
            label: '記事',
            changes: ['title' => ['<b>変更前</b>', '変更後']],
            metadata: ['tags' => ['created' => 1, 'updated' => 0, 'deleted' => 0]],
        );

        $this->get(route('admin.audit-logs.show', $log))
            ->assertOk()
            ->assertSee('&lt;b&gt;変更前&lt;/b&gt;', false)
            ->assertSee('変更後')
            ->assertSee('"created": 1');
    }

    public function test_audit_logs_are_not_editable_from_the_admin_screen(): void
    {
        $this->actingAsSuperAdmin();
        $log = AuditLogger::record(AuditAction::Created, 'tag', label: 'お知らせ');

        $this->assertSame(1, AuditLog::query()->count());
        $this->delete('/admin/audit-logs/'.$log->id)->assertStatus(405);
    }
}
