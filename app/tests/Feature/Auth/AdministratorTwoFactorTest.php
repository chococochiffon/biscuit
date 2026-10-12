<?php

namespace Tests\Feature\Auth;

use App\Enums\AdministratorRole;
use App\Enums\AuditAction;
use App\Enums\TwoFactorMethod;
use App\Models\Administrator;
use App\Models\AuditLog;
use App\Notifications\LoginCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdministratorTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private function totpAdministrator(array $attributes = []): Administrator
    {
        $administrator = Administrator::factory()->create(['password' => Hash::make('password'), ...$attributes]);
        $administrator->enableTotp(self::SECRET, 0);

        return $administrator;
    }

    private function currentCode(string $secret = self::SECRET): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_settings_page_enables_the_authenticator_after_checking_a_code(): void
    {
        $administrator = $this->actingAsAdmin();

        $this->get(route('admin.two-factor.show'))->assertOk()->assertSee('data-two-factor-method="email"', false)->assertSee('data-two-factor-start', false);

        $response = $this->get(route('admin.two-factor.show', ['setup' => 1]))->assertOk()->assertSee('<svg', false)->assertDontSee('<?xml', false);
        $secret = session('admin_two_factor.pending_secret');
        $this->assertIsString($secret);
        // 同じ設定の途中では、読み込み直しても同じ秘密鍵を使う
        $this->get(route('admin.two-factor.show', ['setup' => 1]));
        $this->assertSame($secret, session('admin_two_factor.pending_secret'));
        $response->assertSee(trim(chunk_split($secret, 4, ' ')));

        $this->put(route('admin.two-factor.update'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($administrator->fresh()->usesTotp());

        $this->put(route('admin.two-factor.update'), ['code' => $this->currentCode($secret)])
            ->assertRedirect(route('admin.two-factor.show'))
            ->assertSessionHas('status');

        $administrator->refresh();
        $this->assertTrue($administrator->usesTotp());
        $this->assertSame($secret, $administrator->two_factor_secret);
        // 秘密鍵は暗号化して保存し、操作ログには残さない
        $this->assertNotSame($secret, DB::table('administrators')->where('id', $administrator->id)->value('two_factor_secret'));
        $log = AuditLog::query()->where('subject_type', 'administrator')->where('action', AuditAction::Updated)->latest('id')->first();
        $this->assertSame('totp_enabled', $log->metadata['two_factor']);
        $this->assertStringNotContainsString($secret, json_encode($log->changes));
        $this->assertNull(session('admin_two_factor.pending_secret'));
    }

    public function test_the_authenticator_can_be_disabled_with_the_current_password(): void
    {
        $administrator = $this->totpAdministrator();
        $this->actingAs($administrator, 'admin');

        $this->get(route('admin.two-factor.show'))->assertSee('data-two-factor-disable', false);
        $this->delete(route('admin.two-factor.destroy'), ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertTrue($administrator->fresh()->usesTotp());

        $this->delete(route('admin.two-factor.destroy'), ['current_password' => 'password'])->assertRedirect(route('admin.two-factor.show'));

        $administrator->refresh();
        $this->assertFalse($administrator->usesTotp());
        $this->assertSame(TwoFactorMethod::Email, $administrator->two_factor_method);
        $this->assertNull($administrator->two_factor_secret);
    }

    public function test_login_with_the_authenticator_code_without_sending_an_email(): void
    {
        Notification::fake();
        $administrator = $this->totpAdministrator();

        $this->post(route('admin.login.store'), ['email' => $administrator->email, 'password' => 'password'])
            ->assertRedirect(route('admin.login.verify'));
        Notification::assertNothingSent();

        $this->get(route('admin.login.verify'))->assertOk()->assertSee('data-two-factor-method="totp"', false)->assertSee('data-send-email-code', false);

        $this->post(route('admin.login.verify.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::LoginFailed)->where('metadata->reason', 'totp')->exists());

        $code = $this->currentCode();
        $this->post(route('admin.login.verify.store'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($administrator, 'admin');

        // 同じコードは 2 回使えない
        $this->post(route('admin.logout'));
        $this->post(route('admin.login.store'), ['email' => $administrator->email, 'password' => 'password']);
        $this->post(route('admin.login.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_too_many_wrong_authenticator_codes_restart_the_login(): void
    {
        $administrator = $this->totpAdministrator();
        $this->post(route('admin.login.store'), ['email' => $administrator->email, 'password' => 'password']);

        for ($attempt = 1; $attempt < (int) config('auth.login_codes.max_attempts'); $attempt++) {
            $this->post(route('admin.login.verify.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->post(route('admin.login.verify.store'), ['code' => '000000'])->assertRedirect(route('admin.login'));
        $this->get(route('admin.login.verify'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.verify.store'), ['code' => $this->currentCode()]);
        $this->assertGuest('admin');
    }

    public function test_an_email_code_can_be_used_instead_of_the_authenticator(): void
    {
        Notification::fake();
        $administrator = $this->totpAdministrator();
        $this->post(route('admin.login.store'), ['email' => $administrator->email, 'password' => 'password']);

        $this->post(route('admin.login.email-code'))
            ->assertRedirect(route('admin.login.verify'))
            ->assertSessionHas('status');

        $code = '';
        Notification::assertSentTo($administrator, LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->get(route('admin.login.verify'))->assertSee('data-two-factor-method="email"', false);
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::LoginCodeSent)->where('metadata->instead_of_totp', true)->exists());

        $this->post(route('admin.login.verify.store'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($administrator, 'admin');
        // メールでログインしても、認証アプリの設定は残る(設定し直すかは本人が決める)
        $this->assertTrue($administrator->fresh()->usesTotp());
    }

    public function test_sending_an_email_code_needs_a_pending_authenticator_login(): void
    {
        Notification::fake();

        $this->post(route('admin.login.email-code'))->assertRedirect(route('admin.login'));
        Notification::assertNothingSent();
    }

    public function test_super_administrators_can_reset_another_administrators_authenticator(): void
    {
        $other = $this->totpAdministrator();
        $this->actingAsSuperAdmin();

        $this->get(route('admin.show', $other))->assertSee('data-two-factor-reset', false);
        $this->delete(route('admin.two-factor.reset', $other))->assertRedirect(route('admin.show', $other));

        $this->assertFalse($other->fresh()->usesTotp());
        $this->assertTrue(AuditLog::query()->where('subject_id', $other->id)->where('metadata->two_factor', 'totp_reset')->exists());
    }

    public function test_other_administrators_cannot_reset_the_authenticator(): void
    {
        $other = $this->totpAdministrator();
        $this->actingAsAdmin(['role' => AdministratorRole::Admin]);

        $this->get(route('admin.show', $other))->assertDontSee('data-two-factor-reset', false);
        $this->delete(route('admin.two-factor.reset', $other))->assertForbidden();
        $this->assertTrue($other->fresh()->usesTotp());
    }
}
