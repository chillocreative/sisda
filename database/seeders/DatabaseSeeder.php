<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Super Admin
        User::updateOrCreate(
            ['telephone' => '0123456789'],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'role' => 'super_admin',
                'status' => 'approved',
                'password' => 'password',
            ]
        );

        // General User
        User::updateOrCreate(
            ['telephone' => '333'],
            [
                'name' => 'General User',
                'role' => 'user',
                'status' => 'approved',
                'password' => '123',
            ]
        );

        $this->call(LokalitSeeder::class);

        // Example Admin (ensure territory tables are seeded first)
        // User::create([
        //     'name' => 'Admin Johor',
        //     'telephone' => '0111222333',
        //     'role' => 'admin',
        //     'status' => 'approved',
        //     'negeri_id' => 1, // Johor
        //     'bandar_id' => 1, // Segamat
        //     'kadun_id' => 1, // Buloh Kasap
        //     'password' => 'password',
        // ]);
    }
}
