<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\Staff;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Staff::factory()->create([
            'shop_id' => Shop::query()->get()->first()->id,
            'name' => 'Test staff',
            'first_name' => 'Test',
            'last_name' => 'staff',
            'nickname' => 'テスト',
            'email' => 'test@example.com',
            'password' => Hash::make('test')
        ]);
    }
}
