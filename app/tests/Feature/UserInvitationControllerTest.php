<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserInvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_invitation_pages(): void
    {
        $this->get(route('admin.users.invite'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.users.invite.store'))->assertRedirect(route('admin.login'));
    }

    public function test_invite_screen_can_be_rendered(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.users.invite'))
            ->assertOk()
            ->assertSee('ユーザーの招待')
            ->assertSee('記事とギャラリーを承認なしで公開する');
    }

    public function test_store_creates_inactive_user_named_after_the_email_and_sends_invitation(): void
    {
        Notification::fake();
        SiteSetting::factory()->create(['front_url' => 'https://front.example.com/']);
        $administrator = $this->actingAsAdmin();

        $this->post(route('admin.users.invite.store'), ['email' => 'hanako.yamada@example.com', 'skip_approval' => '1'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'hanako.yamada@example.com')->sole();
        $this->assertSame('hanako.yamada', $user->name);
        $this->assertFalse($user->active_flag);
        $this->assertTrue($user->skip_approval);
        $this->assertNull($user->detail);

        $invitation = $user->invitations()->sole();
        $this->assertSame($administrator->id, $invitation->administrator_id);
        $this->assertEquals(now()->addMinutes((int) config('auth.invitations.expire'))->startOfSecond(), $invitation->expires_at->startOfSecond());

        Notification::assertSentTo($user, UserInvitationNotification::class, function (UserInvitationNotification $notification) use ($user) {
            $url = $notification->invitationUrl($user);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            // リンクのトークンで招待が見つかる(保存しているのはハッシュ)
            return str_starts_with($url, 'https://front.example.com/invitation?')
                && $query['email'] === 'hanako.yamada@example.com'
                && UserInvitation::findUsable($query['email'], $query['token'])?->is($user->invitations()->sole());
        });

        $log = AuditLog::query()->where('subject_id', $user->id)->sole();
        $this->assertSame([AuditAction::Created, ['invited' => true]], [$log->action, $log->metadata]);
    }

    public function test_store_rejects_email_of_existing_user(): void
    {
        Notification::fake();
        $this->actingAsAdmin();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('admin.users.invite.store'), ['email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_resend_issues_a_new_invitation_and_expires_the_old_link(): void
    {
        Notification::fake();
        $this->actingAsAdmin();
        $user = User::factory()->invited()->create();
        $oldToken = UserInvitation::issue($user, null);

        $this->post(route('admin.users.invitation.resend', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame(2, $user->invitations()->count());
        $this->assertNull(UserInvitation::findUsable($user->email, $oldToken));
        Notification::assertSentTo($user, UserInvitationNotification::class);
        $this->assertSame(AuditAction::Invited, AuditLog::query()->where('subject_id', $user->id)->sole()->action);
    }

    public function test_resend_is_not_available_for_active_users(): void
    {
        Notification::fake();
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->post(route('admin.users.invitation.resend', $user))->assertNotFound();

        Notification::assertNothingSent();
    }

    public function test_user_list_shows_invitation_status_and_resend_button(): void
    {
        $this->actingAsAdmin();
        $invited = User::factory()->invited()->create();
        UserInvitation::issue($invited, null);
        $expired = User::factory()->invited()->create();
        UserInvitation::issue($expired, null);
        $expired->invitations()->update(['expires_at' => now()->subMinute()]);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('招待中')
            ->assertSee('招待の期限切れ')
            ->assertSee(route('admin.users.invitation.resend', $invited));
    }

    public function test_users_registered_by_administrators_are_active(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.users.store'), [
            'name' => '直接登録',
            'email' => 'direct@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => [
                'first_name' => '太郎',
                'family_name' => '検証',
                'nick_name' => 'たろう',
                'birthday' => '1990-01-01',
                'name_settings' => 3,
            ],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue(User::query()->where('email', 'direct@example.com')->sole()->active_flag);
    }
}
