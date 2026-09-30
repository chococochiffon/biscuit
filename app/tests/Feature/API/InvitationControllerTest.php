<?php

namespace Tests\Feature\API;

use App\Enums\AuditAction;
use App\Enums\UserDetailNameSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class InvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->invited()->create(['name' => 'hanako', 'email' => 'hanako@example.com']);
        $this->token = UserInvitation::issue($this->user, null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function acceptInput(array $overrides = []): array
    {
        return array_replace_recursive([
            'token' => $this->token,
            'email' => 'hanako@example.com',
            'name' => 'はなこ',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'user_detail' => [
                'first_name' => '花子',
                'family_name' => '山田',
                'nick_name' => 'はなちゃん',
                'birthday' => '1995-04-01',
                'comment' => 'よろしくお願いします',
                'view_flag' => true,
                'name_settings' => UserDetailNameSetting::NickName->value,
                'skills' => [['name' => '写真', 'level' => 3]],
            ],
        ], $overrides);
    }

    public function test_show_returns_initial_values_for_a_usable_link(): void
    {
        $this->getJson(route('api.auth.invitation.show', ['token' => $this->token, 'email' => 'hanako@example.com']))
            ->assertOk()
            ->assertJsonPath('data.name', 'hanako')
            ->assertJsonPath('data.email', 'hanako@example.com');
    }

    public function test_show_rejects_wrong_or_expired_links(): void
    {
        $this->getJson(route('api.auth.invitation.show', ['token' => 'wrong', 'email' => 'hanako@example.com']))->assertNotFound();
        $this->getJson(route('api.auth.invitation.show', ['token' => $this->token, 'email' => 'other@example.com']))->assertNotFound();

        $this->travel((int) config('auth.invitations.expire') + 1)->minutes();

        $this->getJson(route('api.auth.invitation.show', ['token' => $this->token, 'email' => 'hanako@example.com']))->assertNotFound();
    }

    public function test_accept_registers_profile_activates_user_and_logs_in(): void
    {
        $response = $this->postJson(route('api.auth.invitation.accept'), $this->acceptInput())
            ->assertOk()
            ->assertJsonPath('user.name', 'はなこ')
            ->assertJsonPath('user.detail.nick_name', 'はなちゃん')
            ->assertJsonPath('user.detail.skills.0.name', '写真');

        $this->user->refresh();
        $this->assertTrue($this->user->active_flag);
        $this->assertTrue(Hash::check('new-password-123', $this->user->password));
        $this->assertNotNull($this->user->invitations()->sole()->accepted_at);

        // 発行したトークンでそのままマイページを使える
        $this->withToken($response->json('token'))->getJson(route('api.me.show'))->assertOk();
        $this->assertSame(1, PersonalAccessToken::query()->count());

        $log = AuditLog::query()->where('action', AuditAction::InvitationAccepted)->sole();
        $this->assertSame(['user', $this->user->id], [$log->actor_type, $log->actor_id]);
        $this->assertArrayNotHasKey('password', $log->changes);

        // 受諾したリンクはもう使えない
        $this->postJson(route('api.auth.invitation.accept'), $this->acceptInput())->assertJsonValidationErrors('token');
    }

    public function test_accept_validates_profile_and_password(): void
    {
        $this->postJson(route('api.auth.invitation.accept'), $this->acceptInput([
            'password_confirmation' => 'different',
            'user_detail' => ['first_name' => ''],
        ]))->assertJsonValidationErrors(['password', 'user_detail.first_name']);

        $this->assertFalse($this->user->fresh()->active_flag);
    }

    public function test_accept_rejects_expired_link(): void
    {
        $this->travel((int) config('auth.invitations.expire') + 1)->minutes();

        $this->postJson(route('api.auth.invitation.accept'), $this->acceptInput())
            ->assertJsonValidationErrors(['token' => '招待のリンクが無効か、有効期限が切れています。管理者に招待の再送を依頼してください。']);

        $this->assertFalse($this->user->fresh()->active_flag);
    }

    public function test_accept_cannot_change_email_or_skip_approval(): void
    {
        $this->postJson(route('api.auth.invitation.accept'), $this->acceptInput(['skip_approval' => true]))->assertOk();

        $this->user->refresh();
        $this->assertSame('hanako@example.com', $this->user->email);
        $this->assertFalse($this->user->skip_approval);
    }
}
