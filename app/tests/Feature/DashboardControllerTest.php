<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_dashboard_is_displayed(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('ダッシュボード');
    }
}
