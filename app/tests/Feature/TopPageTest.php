<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_page_redirects_guests_to_the_login_screen_through_the_admin_page(): void
    {
        $this->get('/')->assertRedirect('/admin');

        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertSee('name="password"', false);
    }

    public function test_top_page_redirects_logged_in_administrators_to_the_admin_page(): void
    {
        $this->actingAsAdmin();

        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
    }
}
