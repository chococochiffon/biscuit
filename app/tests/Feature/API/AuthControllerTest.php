<?php

namespace Tests\Feature\API;

use App\Enums\AuditAction;
use App\Http\Controllers\API\AuthController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_login_issues_a_token_that_can_access_me(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $user = $this->createUser();

        $response = $this->postJson(route('api.auth.login'), ['email' => 'user@example.com', 'password' => 'password']);

        $response->assertOk();
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('expires_at', now()->addMinutes((int) config('sanctum.expiration'))->toIso8601String());
        $this->assertSame(AuthController::TOKEN_NAME, PersonalAccessToken::query()->sole()->name);

        $this->withToken($response->json('token'))->getJson(route('api.me.show'))
            ->assertOk()
            ->assertJsonPath('data.email', 'user@example.com');

        $log = AuditLog::query()->sole();
        $this->assertSame([AuditAction::Login, 'user', $user->id], [$log->action, $log->actor_type, $log->actor_id]);
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
