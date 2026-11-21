<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EducationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $educations = [
            ['name' => 'SD'],
            ['name' => 'SMP'],
            ['name' => 'SMK/SMA/MA'],
            ['name' => 'D1'],
            ['name' => 'D2'],
            ['name' => 'D3'],
            ['name' => 'D4'],
            ['name' => 'S1'],
            ['name' => 'S2'],
            ['name' => 'S3'],
        ];

        foreach ($educations as $education) {
            \App\Models\Education::create($education);
        }
    }
}
