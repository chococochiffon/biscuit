<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Shop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'オーナー',
                'is_admin' => true
            ],
            [
                'name' => '店長',
                'is_admin' => true
            ],
            [
                'name' => 'スタッフ長',
                'is_admin' => true
            ],
            [
                'name' => 'スタッフ',
                'is_admin' => false
            ],
            [
                'name' => 'キャスト',
                'is_admin' => false
            ],
        ];
        foreach ($data as $key => $value) {
            Role::factory()->create([
                'shop_id' => Shop::query()->get()->first()->id,
                'name' => $value['name'],
                'is_admin' => $value['is_admin'],
                'order' => $key + 1
            ]);
        }
    }
}
