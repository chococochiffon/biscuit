<?php

namespace Tests;

use App\Enums\AdministratorRole;
use App\Models\Administrator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * 管理者を作成して管理画面(admin ガード)にログインした状態にし、作成した管理者を返す。
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsAdmin(array $attributes = []): Administrator
    {
        $administrator = Administrator::factory()->create($attributes);

        $this->actingAs($administrator, 'admin');

        return $administrator;
    }

    /**
     * スーパー管理者を作成して admin ガードでログインし、その管理者を返す(カスタムページ管理などスーパー管理者だけの機能用)。
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsSuperAdmin(array $attributes = []): Administrator
    {
        return $this->actingAsAdmin(['role' => AdministratorRole::SuperAdmin, ...$attributes]);
    }
}
