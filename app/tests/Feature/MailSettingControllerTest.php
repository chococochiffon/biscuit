<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MailSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function smtpInput(array $overrides = []): array
    {
        return ['driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'starttls', 'username' => 'mailer', 'password' => 'Smtp-Secret-1', 'from_address' => 'no-reply@example.com', 'from_name' => 'ビスケット', 'test_to' => 'owner@example.com', ...$overrides];
    }

    public function test_only_super_administrators_can_manage_the_mail_settings(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.mail-settings.edit'))->assertForbidden();
        $this->put(route('admin.mail-settings.update'), $this->smtpInput())->assertForbidden();
        $this->delete(route('admin.mail-settings.destroy'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertDontSee(route('admin.mail-settings.edit'));
        $this->assertNull(MailSetting::current());
    }

    public function test_edit_shows_the_env_settings_until_saved(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.mail-settings.edit'))
            ->assertOk()
            ->assertSee('data-mail-source="env"', false)
            ->assertDontSee('data-mail-settings-reset', false);
        $this->get(route('admin.dashboard'))->assertSee(route('admin.mail-settings.edit'));
    }

    public function test_update_sends_a_test_email_and_saves_the_settings_encrypted(): void
    {
        Mail::fake();
        $this->actingAsSuperAdmin();

        $this->put(route('admin.mail-settings.update'), $this->smtpInput())
            ->assertRedirect(route('admin.mail-settings.edit'))
            ->assertSessionHas('status');

        $setting = MailSetting::current();
        $this->assertSame('smtp.example.com', $setting->host);
        $this->assertSame('Smtp-Secret-1', $setting->password);
        // パスワードは暗号化して保存し、監査ログには値を残さない
        $this->assertNotSame('Smtp-Secret-1', DB::table('mail_settings')->value('password'));
        $log = AuditLog::query()->where('subject_type', 'mail_setting')->latest('id')->first();
        $this->assertStringNotContainsString('Smtp-Secret-1', json_encode([$log->changes, $log->metadata]));
        $this->assertTrue($log->metadata['password_changed']);

        $this->get(route('admin.mail-settings.edit'))
            ->assertSee('data-mail-source="database"', false)
            ->assertSee('data-mail-settings-reset', false)
            ->assertDontSee('Smtp-Secret-1');
    }

    public function test_saved_settings_take_precedence_over_the_env_when_sending(): void
    {
        MailSetting::query()->create(['driver' => 'resend', 'api_key' => 're_saved_key', 'from_address' => 'saved@example.com', 'from_name' => 'ビスケット']);

        // メールのマネージャーを作り直すと、保存した設定が config に入る
        app()->forgetInstance('mail.manager');
        Mail::clearResolvedInstances();
        app('mail.manager');

        $this->assertSame('resend', config('mail.default'));
        $this->assertSame('re_saved_key', config('services.resend.key'));
        $this->assertSame('saved@example.com', config('mail.from.address'));
        $this->assertSame('ビスケット', config('mail.from.name'));
    }

    public function test_an_empty_password_keeps_the_current_one(): void
    {
        Mail::fake();
        $this->actingAsSuperAdmin();
        $this->put(route('admin.mail-settings.update'), $this->smtpInput());

        $this->put(route('admin.mail-settings.update'), $this->smtpInput(['password' => '', 'host' => 'smtp2.example.com']))
            ->assertSessionHas('status');

        $setting = MailSetting::current();
        $this->assertSame('smtp2.example.com', $setting->host);
        $this->assertSame('Smtp-Secret-1', $setting->password);
        $this->assertSame(1, MailSetting::query()->count());
        $this->assertFalse(AuditLog::query()->where('subject_type', 'mail_setting')->latest('id')->first()->metadata['password_changed']);
    }

    public function test_settings_are_not_saved_when_the_test_email_fails(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->put(route('admin.mail-settings.update'), $this->smtpInput(['host' => '127.0.0.1', 'port' => 1, 'password' => 'Secret-Smtp-Pass']))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(MailSetting::current());
        $this->assertStringNotContainsString('Secret-Smtp-Pass', (string) session('error'));
        $this->assertArrayNotHasKey('password', (array) session('_old_input'));
        $response->assertSessionMissing('status');
    }

    public function test_resend_requires_an_api_key(): void
    {
        Mail::fake();
        $this->actingAsSuperAdmin();

        $this->put(route('admin.mail-settings.update'), ['driver' => 'resend', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com'])
            ->assertSessionHasErrors('api_key');
        $this->put(route('admin.mail-settings.update'), ['driver' => 'resend', 'api_key' => 'not-a-key', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com'])
            ->assertSessionHasErrors('api_key');

        $this->put(route('admin.mail-settings.update'), ['driver' => 'resend', 'api_key' => 're_test_123', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com'])
            ->assertSessionHas('status');
        $this->assertSame('re_test_123', MailSetting::current()->api_key);
        $this->assertNull(MailSetting::current()->host);
    }

    public function test_destroy_goes_back_to_the_env_settings(): void
    {
        Mail::fake();
        $this->actingAsSuperAdmin();
        $this->put(route('admin.mail-settings.update'), $this->smtpInput());

        $this->delete(route('admin.mail-settings.destroy'))->assertRedirect(route('admin.mail-settings.edit'));

        $this->assertNull(MailSetting::current());
        $this->assertSame(1, MailSetting::onlyTrashed()->count());
        $this->assertTrue(AuditLog::query()->where('subject_type', 'mail_setting')->where('action', 'deleted')->exists());
    }
}
