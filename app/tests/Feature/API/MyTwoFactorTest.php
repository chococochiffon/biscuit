<?php

namespace Tests\Feature\API;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDetail;
use App\Notifications\LoginCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MyTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private function createUser(bool $totp = false): User
    {
        $user = User::factory()->create(['email' => 'user@example.com', 'password' => Hash::make('password')]);
        UserDetail::factory()->create(['user_id' => $user->id]);

        if ($totp) {
            $user->enableTotp(self::SECRET, 0);
        }

        return $user;
    }

    private function currentCode(string $secret = self::SECRET): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_user_can_set_up_and_disable_the_authenticator(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/me/two-factor')->assertOk()->assertJsonPath('data.method', 'email');
        $this->getJson('/api/me')->assertJsonPath('data.two_factor_method', 'email');

        $setup = $this->getJson('/api/me/two-factor/setup')->assertOk();
        $secret = $setup->json('data.secret');
        $this->assertStringStartsWith('<svg', $setup->json('data.qr_code_svg'));
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('data.otpauth_uri'));
        // 設定の途中では同じ秘密鍵を返す
        $this->assertSame($secret, $this->getJson('/api/me/two-factor/setup')->json('data.secret'));

        $this->putJson('/api/me/two-factor', ['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->putJson('/api/me/two-factor', ['code' => $this->currentCode($secret)])
            ->assertOk()
            ->assertJsonPath('data.method', 'totp');

        $this->assertTrue($user->fresh()->usesTotp());
        $this->assertSame('totp_enabled', AuditLog::query()->where('subject_type', 'user')->latest('id')->first()->metadata['two_factor']);
        $this->getJson('/api/me/two-factor/setup')->assertStatus(409);
        // 秘密鍵は API で返さない
        $this->assertStringNotContainsString($secret, $this->getJson('/api/me')->getContent());

        $this->deleteJson('/api/me/two-factor', ['current_password' => 'wrong'])->assertJsonValidationErrors('current_password');
        $this->deleteJson('/api/me/two-factor', ['current_password' => 'password'])->assertOk()->assertJsonPath('data.method', 'email');
        $this->assertFalse($user->fresh()->usesTotp());
    }

    public function test_user_with_the_authenticator_logs_in_with_its_code(): void
    {
        Notification::fake();
        $user = $this->createUser(totp: true);

        $challenge = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('method', 'totp')
            ->json('challenge');
        Notification::assertNothingSent();

        $this->postJson('/api/auth/login/verify', ['challenge' => $challenge, 'code' => '000000'])->assertJsonValidationErrors('code');
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::LoginFailed)->where('metadata->reason', 'totp')->exists());

        $code = $this->currentCode();
        $this->postJson('/api/auth/login/verify', ['challenge' => $challenge, 'code' => $code])
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'user'])
            ->assertJsonPath('user.two_factor_method', 'totp');

        // チャレンジは 1 回だけ使える。同じコードも 2 回使えない
        $this->postJson('/api/auth/login/verify', ['challenge' => $challenge, 'code' => $code])->assertUnprocessable();
        $challenge = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->json('challenge');
        $this->postJson('/api/auth/login/verify', ['challenge' => $challenge, 'code' => $code])->assertJsonValidationErrors('code');
    }

    public function test_too_many_wrong_codes_restart_the_login(): void
    {
        $user = $this->createUser(totp: true);
        $challenge = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->json('challenge');

        for ($attempt = 1; $attempt < (int) config('auth.login_codes.max_attempts'); $attempt++) {
            $this->postJson('/api/auth/login/verify', ['challenge' => $challenge, 'code' => '000000'])->assertJsonValidationErrors('code');
        }

        $this->postJson('/api/auth/login/verify', ['challenge' => $challenge, 'code' => '000000'])->assertJsonValidationErrors('challenge');
        $this->postJson('/api/auth/login/verify', ['challenge' => $challenge, 'code' => $this->currentCode()])->assertUnprocessable();
    }

    public function test_an_email_code_can_be_used_instead_of_the_authenticator(): void
    {
        Notification::fake();
        $user = $this->createUser(totp: true);
        $challenge = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->json('challenge');

        $emailChallenge = $this->postJson('/api/auth/login/email-code', ['challenge' => $challenge])
            ->assertOk()
            ->assertJsonPath('method', 'email')
            ->json('challenge');

        $code = '';
        Notification::assertSentTo($user, LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::LoginCodeSent)->where('metadata->instead_of_totp', true)->exists());

        // 認証アプリのチャレンジはもう使えない
        $this->postJson('/api/auth/login/email-code', ['challenge' => $challenge])->assertJsonValidationErrors('challenge');

        $this->postJson('/api/auth/login/verify', ['challenge' => $emailChallenge, 'code' => $code])->assertOk()->assertJsonStructure(['token']);
        $this->assertTrue($user->fresh()->usesTotp());
    }

    public function test_email_code_needs_an_authenticator_challenge(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/login/email-code', ['challenge' => 'unknown'])->assertJsonValidationErrors('challenge');
        Notification::assertNothingSent();
    }

    public function test_users_without_the_authenticator_still_get_an_email_code(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertJsonPath('method', 'email');
        Notification::assertSentTo($user, LoginCodeNotification::class);
    }

    public function test_super_administrators_can_reset_a_users_authenticator(): void
    {
        $user = $this->createUser(totp: true);

        $this->actingAsAdmin();
        $this->get(route('admin.users.show', $user))->assertDontSee('data-two-factor-reset', false);
        $this->delete(route('admin.users.two-factor.reset', $user))->assertForbidden();

        $this->actingAsSuperAdmin();
        $this->get(route('admin.users.show', $user))->assertSee('data-two-factor-reset', false);
        $this->delete(route('admin.users.two-factor.reset', $user))->assertRedirect(route('admin.users.show', $user));

        $this->assertFalse($user->fresh()->usesTotp());
        $this->assertTrue(AuditLog::query()->where('subject_type', 'user')->where('metadata->two_factor', 'totp_reset')->exists());
    }
}
