<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employee_types = [
            ['name' => 'Kimia Farma'],
            ['name' => 'PT BDP'],
            ['name' => 'Be-One'],
            ['name' => 'Indopsiko'],
            ['name' => 'ISS'],
            ['name' => 'EGS'],
            ['name' => 'lainnya'],
            ['name' => 'UBL'],
            ['name' => 'PT. IDP']
        ];

        foreach ($employee_types as $employee_type) {
            \App\Models\EmployeeType::create($employee_type);
        }
    }
}
