<?php

namespace Tests\Feature\Auth;

use App\Models\Administrator;
use App\Notifications\LoginCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdministratorSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertViewIs('admin.auth.login');
    }

    public function test_authenticated_administrators_are_redirected_from_login_screen(): void
    {
        $administrator = Administrator::factory()->create();

        $response = $this->actingAs($administrator, 'admin')->get(route('admin.login'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    /**
     * メールアドレスとパスワードでログインし(1 段目)、メールで送られた確認コードを返す。
     */
    private function loginWithPassword(Administrator $administrator): string
    {
        Notification::fake();

        $this->post(route('admin.login.store'), [
            'email' => $administrator->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.login.verify'));

        return $this->sentCode($administrator);
    }

    private function sentCode(Administrator $administrator): string
    {
        $code = '';
        Notification::assertSentTo($administrator, LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return $code;
    }

    public function test_administrators_can_authenticate_with_password_and_login_code(): void
    {
        $administrator = Administrator::factory()->create([
            'password' => Hash::make('password'),
            'last_login_at' => null,
        ]);

        $code = $this->loginWithPassword($administrator);

        // パスワードだけではまだログインしない
        $this->assertGuest('admin');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->get(route('admin.login.verify'))->assertOk()->assertSee($administrator->email);

        $this->post(route('admin.login.verify.store'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($administrator, 'admin');
        $this->assertNotNull($administrator->fresh()->last_login_at);
    }

    public function test_verify_screen_requires_password_first(): void
    {
        $this->get(route('admin.login.verify'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.verify.store'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_wrong_code_does_not_log_in_and_code_is_locked_after_too_many_attempts(): void
    {
        $administrator = Administrator::factory()->create(['password' => Hash::make('password')]);
        $code = $this->loginWithPassword($administrator);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < (int) config('auth.login_codes.max_attempts'); $i++) {
            $this->post(route('admin.login.verify.store'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // 間違えすぎたコードは、正しくても使えない
        $this->post(route('admin.login.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_expired_code_cannot_be_used(): void
    {
        $administrator = Administrator::factory()->create(['password' => Hash::make('password')]);
        $code = $this->loginWithPassword($administrator);

        $this->travel((int) config('auth.login_codes.expire') + 1)->minutes();

        $this->post(route('admin.login.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_resend_sends_a_new_code_and_the_old_code_stops_working(): void
    {
        $administrator = Administrator::factory()->create(['password' => Hash::make('password')]);
        $oldCode = $this->loginWithPassword($administrator);
        Notification::fake();

        $this->post(route('admin.login.resend'))
            ->assertRedirect(route('admin.login.verify'))
            ->assertSessionHas('status');
        $newCode = $this->sentCode($administrator);

        if ($oldCode !== $newCode) {
            $this->post(route('admin.login.verify.store'), ['code' => $oldCode])->assertSessionHasErrors('code');
        }

        $this->post(route('admin.login.verify.store'), ['code' => $newCode])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($administrator, 'admin');
    }

    public function test_administrators_can_not_authenticate_with_invalid_password(): void
    {
        $administrator = Administrator::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $administrator->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('admin');
        $response->assertSessionHasErrors('email');
    }

    public function test_login_screen_shows_error_after_failed_login(): void
    {
        $administrator = Administrator::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->from(route('admin.login'))
            ->followingRedirects()
            ->post(route('admin.login.store'), [
                'email' => $administrator->email,
                'password' => 'wrong-password',
            ]);

        $response->assertOk()
            ->assertSee('<div class="alert alert-danger">', false)
            ->assertSeeText(__('auth.failed'));
    }

    public function test_guests_are_redirected_to_login_when_accessing_protected_admin_routes(): void
    {
        $response = $this->get(route('admin.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_administrators_can_logout(): void
    {
        $administrator = Administrator::factory()->create();

        $response = $this->actingAs($administrator, 'admin')->post(route('admin.logout'));

        $this->assertGuest('admin');
        $response->assertRedirect(route('admin.login'));
    }

    public function test_too_many_login_attempts_are_throttled(): void
    {
        $administrator = Administrator::factory()->create([
            'password' => Hash::make('password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.store'), [
                'email' => $administrator->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('admin.login.store'), [
            'email' => $administrator->email,
            'password' => 'password',
        ]);

        $response->assertStatus(429);
        $this->assertGuest('admin');
    }

    public function test_code_entry_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('admin.login.verify.store'), ['code' => '000000']);
        }

        $this->post(route('admin.login.verify.store'), ['code' => '000000'])->assertStatus(429);
    }
}
