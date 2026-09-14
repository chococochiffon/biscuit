<?php

namespace Tests\Feature\Auth;

use App\Models\Administrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

        $response->assertRedirect(route('admin.index'));
    }

    public function test_administrators_can_authenticate_using_the_login_screen(): void
    {
        $administrator = Administrator::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $administrator->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($administrator, 'admin');
        $response->assertRedirect(route('admin.index'));
    }

    public function test_login_updates_last_login_at(): void
    {
        $administrator = Administrator::factory()->create([
            'password' => Hash::make('password'),
            'last_login_at' => null,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $administrator->email,
            'password' => 'password',
        ]);

        $this->assertNotNull($administrator->fresh()->last_login_at);
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
}
