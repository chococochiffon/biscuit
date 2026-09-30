<?php

namespace Tests\Feature\API;

use App\Enums\AuditAction;
use App\Http\Controllers\API\AuthController;
use App\Models\Administrator;
use App\Models\AuditLog;
use App\Models\LoginCode;
use App\Models\User;
use App\Models\UserDetail;
use App\Notifications\LoginCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $user = User::factory()->create(['email' => 'user@example.com', 'password' => Hash::make('password')]);
        UserDetail::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    /**
     * メールアドレスとパスワードでログインし(1 段目)、チャレンジとメールで送られた確認コードを返す。
     *
     * @return array{0: string, 1: string}
     */
    private function loginWithPassword(User $user): array
    {
        Notification::fake();

        $challenge = $this->postJson(route('api.auth.login'), ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonMissingPath('token')
            ->json('challenge');

        return [$challenge, $this->sentCode($user)];
    }

    private function sentCode(User $user): string
    {
        $code = '';
        Notification::assertSentTo($user, LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return $code;
    }

    public function test_login_sends_code_and_verifying_it_issues_a_token_that_can_access_me(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $user = $this->createUser();

        [$challenge, $code] = $this->loginWithPassword($user);
        $this->assertSame(0, PersonalAccessToken::query()->count());

        $response = $this->postJson(route('api.auth.login.verify'), ['challenge' => $challenge, 'code' => $code]);

        $response->assertOk();
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('expires_at', now()->addMinutes((int) config('sanctum.expiration'))->toIso8601String());
        $this->assertSame(AuthController::TOKEN_NAME, PersonalAccessToken::query()->sole()->name);

        $this->withToken($response->json('token'))->getJson(route('api.me.show'))
            ->assertOk()
            ->assertJsonPath('data.email', 'user@example.com');

        $this->assertSame(
            [[AuditAction::LoginCodeSent, $user->id], [AuditAction::Login, $user->id]],
            AuditLog::query()->orderBy('id')->get()->map(fn (AuditLog $log) => [$log->action, $log->actor_id])->all(),
        );

        // 使ったコードはもう使えない
        $this->postJson(route('api.auth.login.verify'), ['challenge' => $challenge, 'code' => $code])->assertJsonValidationErrors('code');
    }

    public function test_verify_rejects_wrong_expired_or_locked_codes(): void
    {
        $user = $this->createUser();
        [$challenge, $code] = $this->loginWithPassword($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->postJson(route('api.auth.login.verify'), ['challenge' => 'wrong', 'code' => $code])->assertJsonValidationErrors('code');

        for ($i = 0; $i < (int) config('auth.login_codes.max_attempts'); $i++) {
            $this->postJson(route('api.auth.login.verify'), ['challenge' => $challenge, 'code' => $wrong])->assertJsonValidationErrors('code');
        }

        $this->postJson(route('api.auth.login.verify'), ['challenge' => $challenge, 'code' => $code])->assertJsonValidationErrors('code');

        // 送り直すと新しいコードで入れる。期限が切れたコードは使えない
        Notification::fake();
        $newChallenge = $this->postJson(route('api.auth.login.resend'), ['challenge' => $challenge])->assertOk()->json('challenge');
        $newCode = $this->sentCode($user);
        $this->travel((int) config('auth.login_codes.expire') + 1)->minutes();
        $this->postJson(route('api.auth.login.verify'), ['challenge' => $newChallenge, 'code' => $newCode])->assertJsonValidationErrors('code');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_resend_issues_a_new_challenge_and_old_one_stops_working(): void
    {
        $user = $this->createUser();
        [$challenge, $oldCode] = $this->loginWithPassword($user);
        Notification::fake();

        $newChallenge = $this->postJson(route('api.auth.login.resend'), ['challenge' => $challenge])->assertOk()->json('challenge');
        $newCode = $this->sentCode($user);

        $this->postJson(route('api.auth.login.verify'), ['challenge' => $challenge, 'code' => $oldCode])->assertJsonValidationErrors('code');
        $this->postJson(route('api.auth.login.verify'), ['challenge' => $newChallenge, 'code' => $newCode])->assertOk();

        // 使い終わったチャレンジでは送り直せない
        $this->postJson(route('api.auth.login.resend'), ['challenge' => $newChallenge])->assertJsonValidationErrors('challenge');
    }

    public function test_codes_for_administrators_cannot_be_used_to_log_in_as_users(): void
    {
        Notification::fake();
        $administrator = Administrator::factory()->create(['password' => Hash::make('password')]);
        $challenge = LoginCode::issue($administrator);
        $code = '';
        Notification::assertSentTo($administrator, LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->postJson(route('api.auth.login.verify'), ['challenge' => $challenge, 'code' => $code])->assertJsonValidationErrors('code');
    }

    public function test_login_fails_with_wrong_password_and_records_it(): void
    {
        $this->createUser();

        $this->postJson(route('api.auth.login'), ['email' => 'user@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $log = AuditLog::query()->sole();
        $this->assertSame([AuditAction::LoginFailed, 'user', null], [$log->action, $log->subject_type, $log->actor_id]);
        $this->assertSame(['email' => 'user@example.com'], $log->metadata);
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_invited_users_cannot_login_until_they_accept_the_invitation(): void
    {
        User::factory()->invited()->create(['email' => 'invited@example.com', 'password' => Hash::make('password')]);

        $this->postJson(route('api.auth.login'), ['email' => 'invited@example.com', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_deleted_users_cannot_login_or_use_their_tokens(): void
    {
        $user = $this->createUser();
        $token = $user->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken;
        $user->delete();

        $this->postJson(route('api.auth.login'), ['email' => 'user@example.com', 'password' => 'password'])->assertUnprocessable();
        $this->withToken($token)->getJson(route('api.me.show'))->assertUnauthorized();
    }

    public function test_login_is_throttled(): void
    {
        $this->createUser();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.auth.login'), ['email' => 'user@example.com', 'password' => 'wrong']);
        }

        $this->postJson(route('api.auth.login'), ['email' => 'user@example.com', 'password' => 'password'])->assertTooManyRequests();
    }

    public function test_logout_expires_the_token_without_deleting_it(): void
    {
        $user = $this->createUser();
        $token = $user->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken;

        $this->withToken($token)->postJson(route('api.auth.logout'))->assertNoContent();

        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->assertTrue(PersonalAccessToken::query()->sole()->expires_at->lessThanOrEqualTo(now()));
        $this->assertSame(AuditAction::Logout, AuditLog::query()->sole()->action);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(route('api.me.show'))->assertUnauthorized();
    }

    public function test_expired_tokens_and_missing_tokens_are_rejected(): void
    {
        $user = $this->createUser();
        $token = $user->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken;

        $this->getJson(route('api.me.show'))->assertUnauthorized();

        $this->travel(2)->days();
        $this->withToken($token)->getJson(route('api.me.show'))->assertUnauthorized();
    }
}
