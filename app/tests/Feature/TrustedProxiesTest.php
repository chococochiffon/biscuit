<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

/**
 * HTTPS を割り当てる外側のリバースプロキシ(config/biscuit.php の trusted_proxies)を信じると、URL が https:// になる。
 */
class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    private function bootWithTrustedProxies(array $proxies): void
    {
        config(['biscuit.trusted_proxies' => $proxies]);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_urls_use_https_behind_a_trusted_proxy(): void
    {
        $this->bootWithTrustedProxies(['127.0.0.1']);

        $this->get(route('admin.login'), ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'admin.example.com', 'X-Forwarded-Port' => '443'])
            ->assertOk()
            ->assertSee('action="https://admin.example.com/admin/login"', false);
    }

    public function test_forwarded_headers_are_ignored_without_trusted_proxies(): void
    {
        $this->bootWithTrustedProxies([]);

        $this->get(route('admin.login'), ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.com'])
            ->assertOk()
            ->assertDontSee('evil.example.com')
            ->assertSee('action="http://localhost/admin/login"', false);
    }
}
