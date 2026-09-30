<?php

namespace Tests\Feature\API;

use App\Enums\AuditAction;
use App\Http\Controllers\API\AuthController;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'user@example.com', 'password' => Hash::make('old-password')]);
    }

    public function test_forgot_sends_reset_mail_with_link_to_front_site(): void
    {
        Notification::fake();
        SiteSetting::factory()->create(['site_title' => 'ビスケット', 'front_url' => 'https://front.example.com/']);

        $this->postJson(route('api.auth.forgot-password'), ['email' => 'user@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($this->user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) {
            $url = $notification->resetUrl($this->user);
            $mail = $notification->toMail($this->user);

            return str_starts_with($url, 'https://front.example.com/reset-password?token=')
                && str_contains($url, 'email=user%40example.com')
                && $mail->subject === '【ビスケット】パスワード再設定のご案内'
                && str_contains((string) $mail->render(), 'パスワードを再設定する');
        });
        $this->assertTrue(AuditLog::query()->where('action', AuditAction::PasswordResetRequested)->where('metadata->email', 'user@example.com')->exists());
    }

    public function test_reset_link_falls_back_to_front_url_config_without_site_setting(): void
    {
        Notification::fake();
        config(['app.front_url' => 'http://front.test']);

        $this->postJson(route('api.auth.forgot-password'), ['email' => 'user@example.com'])->assertOk();

        Notification::assertSentTo($this->user, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification) => str_starts_with($notification->resetUrl($this->user), 'http://front.test/reset-password?'));
    }

    public function test_forgot_returns_same_response_for_unknown_or_deleted_users(): void
    {
        Notification::fake();
        $deleted = User::factory()->create(['email' => 'deleted@example.com']);
        $deleted->delete();
        // 招待されてまだ登録していないユーザーにも送らない
        $invited = User::factory()->invited()->create(['email' => 'invited@example.com']);

        $known = $this->postJson(route('api.auth.forgot-password'), ['email' => 'user@example.com'])->assertOk()->json('message');
        $this->postJson(route('api.auth.forgot-password'), ['email' => 'nobody@example.com'])->assertOk()->assertJsonPath('message', $known);
        $this->postJson(route('api.auth.forgot-password'), ['email' => 'deleted@example.com'])->assertOk()->assertJsonPath('message', $known);
        $this->postJson(route('api.auth.forgot-password'), ['email' => 'invited@example.com'])->assertOk()->assertJsonPath('message', $known);
        // 直前に送ったばかり(再送の間隔内)でも同じ応答
        $this->postJson(route('api.auth.forgot-password'), ['email' => 'user@example.com'])->assertOk()->assertJsonPath('message', $known);

        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
        Notification::assertNothingSentTo($deleted);
        Notification::assertNothingSentTo($invited);
    }

    public function test_forgot_is_rate_limited(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $ignored) {
            $this->postJson(route('api.auth.forgot-password'), ['email' => 'user@example.com'])->assertOk();
        }

        $this->postJson(route('api.auth.forgot-password'), ['email' => 'user@example.com'])->assertTooManyRequests();
    }

    public function test_reset_changes_password_expires_tokens_and_records_audit_log(): void
    {
        $this->user->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay());
        $token = Password::broker('users')->createToken($this->user);

        $this->postJson(route('api.auth.reset-password'), [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertNoContent();

        $this->user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $this->user->password));
        $this->assertSame(0, $this->user->tokens()->where('expires_at', '>', now())->count());
        $log = AuditLog::query()->where('action', AuditAction::PasswordReset)->firstOrFail();
        $this->assertSame('user', $log->actor_type);
        $this->assertSame($this->user->id, $log->subject_id);
        $this->assertNull($log->changes);

        // 使ったリンクはもう使えない
        $this->postJson(route('api.auth.reset-password'), [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'another-password-1',
            'password_confirmation' => 'another-password-1',
        ])->assertJsonValidationErrors('email');
    }

    public function test_reset_rejects_invalid_token_or_password(): void
    {
        $token = Password::broker('users')->createToken($this->user);

        $this->postJson(route('api.auth.reset-password'), [
            'token' => 'wrong-token',
            'email' => 'user@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertJsonValidationErrors('email');

        $this->postJson(route('api.auth.reset-password'), [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'different',
        ])->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('old-password', $this->user->fresh()->password));
    }

    public function test_reset_rejects_expired_link(): void
    {
        $token = Password::broker('users')->createToken($this->user);
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->postJson(route('api.auth.reset-password'), [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertJsonValidationErrors('email');
    }
}
