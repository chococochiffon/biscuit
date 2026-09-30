<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_page_is_not_found_for_guests(): void
    {
        $this->get('/')->assertNotFound();
        $this->get('/index')->assertNotFound();
    }

    public function test_top_page_is_not_found_for_logged_in_administrators(): void
    {
        $this->actingAsAdmin();

        $this->get('/')->assertNotFound();
    }
}
