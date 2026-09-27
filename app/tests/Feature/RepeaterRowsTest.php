<?php

namespace Tests\Feature;

use App\Models\SocialLink;
use App\Support\RepeaterRows;
use Tests\TestCase;

class RepeaterRowsTest extends TestCase
{
    public function test_builds_rows_from_saved_models_when_there_is_no_old_input(): void
    {
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $models = [
            (new SocialLink)->forceFill(['id' => 7, 'name' => 'GitHub', 'sort_order' => 3]),
            (new SocialLink)->forceFill(['id' => 9, 'name' => 'YouTube', 'sort_order' => 5]),
        ];

        $rows = RepeaterRows::build('social_links', $models, fn (array $row) => ['name' => $row['name']], fn (SocialLink $link) => ['name' => $link->name]);

        $this->assertEquals([
            (object) ['index' => '0', 'id' => 7, 'sortOrder' => 3, 'name' => 'GitHub'],
            (object) ['index' => '1', 'id' => 9, 'sortOrder' => 5, 'name' => 'YouTube'],
        ], $rows->all());
    }

    public function test_builds_rows_from_old_input_after_a_validation_error(): void
    {
        $this->app['request']->setLaravelSession($this->app['session.store']);
        // 行の追加・削除で入力名の番号が連番でなくなった状態の入力値
        session()->flashInput(['social_links' => [
            3 => ['id' => '7', 'name' => '更新後', 'sort_order' => '1'],
            8 => ['name' => '新規'],
        ]]);

        $rows = RepeaterRows::build('social_links', [
            (new SocialLink)->forceFill(['id' => 7, 'name' => '保存済み', 'sort_order' => 0]),
        ], fn (array $row) => ['name' => $row['name']], fn (SocialLink $link) => ['name' => $link->name]);

        $this->assertEquals([
            (object) ['index' => '0', 'id' => '7', 'sortOrder' => '1', 'name' => '更新後'],
            (object) ['index' => '1', 'id' => null, 'sortOrder' => 1, 'name' => '新規'],
        ], $rows->all());
    }

    public function test_int_or_null_converts_selected_values_and_treats_empty_as_null(): void
    {
        $this->assertSame(3, RepeaterRows::intOrNull('3'));
        $this->assertSame(0, RepeaterRows::intOrNull('0'));
        $this->assertNull(RepeaterRows::intOrNull(''));
        $this->assertNull(RepeaterRows::intOrNull(null));
    }
}
