<?php

namespace Tests;

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
}
