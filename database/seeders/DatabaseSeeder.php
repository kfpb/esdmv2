<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Akun Administrator Dummy untuk Development / Testing
        User::updateOrCreate(
            ['username' => 'ardi'],
            [
                'name' => 'Ardi',
                'email' => 'ardi@kfpb.kimiafarma.co.id',
                'password' => Hash::make('123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
