<?php

namespace Tests\Feature;

use App\Models\PageView;
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

    public function test_dashboard_shows_page_view_summary(): void
    {
        $this->actingAsAdmin();
        $this->travelTo('2026-10-15 12:00:00');
        PageView::factory()->count(3)->create(['viewed_at' => now()]);
        PageView::factory()->create(['viewed_at' => now()->subDay()]);
        PageView::factory()->create(['viewed_at' => now()->subMonth()]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary) => $summary['today']['views'] === 3
            && $summary['yesterday']['views'] === 1
            && $summary['this_month']['views'] === 4
            && $summary['total']['views'] === 5);
        $response->assertSee(route('admin.page-views.index'));
    }
}
