<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StaffRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        StaffRole::factory()->create([
            'staff_id' => Staff::query()->get()->first()->id,
            'role_id' => Role::query()->get()->first()->id
        ]);
    }
}
