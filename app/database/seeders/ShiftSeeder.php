<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\Staff;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Shift::factory()->create([
            'staff_id' => Staff::query()->get()->first()->id,
            'work_day' => date('Y-m-d'),
            'status' => 0,
            'start_time' => date('H:i:s', strtotime('21:00:00')),
            'end_time' => date('H:i:s', strtotime('24:00:00')),
        ]);
    }
}
