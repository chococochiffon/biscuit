<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Shop;
use App\Models\Staff;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => '花魁の舞',
                'quantity' => 1,
                'price' => 400000,
                'discount' => 0,
                'is_call_option' => 0,
                'is_option_item' => 0
            ],
            [
                'name' => '華手水',
                'quantity' => 1,
                'price' => 500000,
                'discount' => 0,
                'is_call_option' => 0,
                'is_option_item' => 0
            ],
            [
                'name' => '紫竜の牙',
                'quantity' => 1,
                'price' => 800000,
                'discount' => 0,
                'is_call_option' => 1,
                'is_option_item' => 0
            ],
            [
                'name' => '天使の宝',
                'quantity' => 1,
                'price' => 1000000,
                'discount' => 0,
                'is_call_option' => 1,
                'is_option_item' => 0
            ],
            [
                'name' => '夜の女王',
                'quantity' => 1,
                'price' => 3000000,
                'discount' => 0,
                'is_call_option' => 1,
                'is_option_item' => 0
            ],
            [
                'name' => '月の雫',
                'quantity' => 1,
                'price' => 5000000,
                'discount' => 0,
                'is_call_option' => 1,
                'is_option_item' => 0
            ],
            [
                'name' => '竜の涙',
                'quantity' => 1,
                'price' => 10000000,
                'discount' => 0,
                'is_call_option' => 1,
                'is_option_item' => 1
            ],
        ];
        foreach ($data as $key => $value) {
            Menu::factory()->create([
                'shop_id' => Shop::query()->get()->first()->id,
                'name' => $value['name'],
                'quantity' => $value['quantity'],
                'price' => $value['price'],
                'discount' => $value['discount'],
                'is_call_option' => $value['is_call_option'],
                'is_option_item' => $value['is_option_item'],
                'order' => $key + 1
            ]);
        }
    }
}
