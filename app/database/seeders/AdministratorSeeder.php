<?php

namespace Database\Seeders;

use App\Enums\AdministratorRole;
use App\Models\Administrator;
use Illuminate\Database\Seeder;

class AdministratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Administrator::factory()->create([
            'name' => 'Test Administrator',
            'email' => 'admin@example.com',
            'role' => AdministratorRole::SuperAdmin,
        ]);
    }
}
