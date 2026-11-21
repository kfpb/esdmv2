<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EchelonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $echelons = [
            ['name' => 'I-a', 'description' => 'General Manager'],
            ['name' => 'I-b', 'description' => 'General Manager'],
            ['name' => 'II-a', 'description' => 'Manager'],
            ['name' => 'II-b', 'description' => 'Manager'],
            ['name' => 'II-c', 'description' => 'Manager'],
            ['name' => 'III-a', 'description' => 'Asisten Manager'],
            ['name' => 'III-b', 'description' => 'Asisten Manager'],
            ['name' => 'IV-a', 'description' => 'Supervisor'],
            ['name' => 'IV-b', 'description' => 'Supervisor'],
        ];

        foreach ($echelons as $echelon) {
            \App\Models\Echelon::create($echelon);
        }
    }
}
