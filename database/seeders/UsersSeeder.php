<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = [
            [
                'name' => 'Master',
                'no_kad' => 111,
                'email' => 'master@mail.com',
                'password' => Hash::make('123'),
                'phone' => '0821',
                'approved' => 1,
                'role_id' => 1,
            ],
            [
                'name' => 'Admin',
                'no_kad' => 222,
                'email' => 'admin@mail.com',
                'password' => Hash::make('123'),
                'phone' => '0821',
                'approved' => 1,
                'role_id' => 2,
            ],
            [
                'name' => 'User',
                'no_kad' => 333,
                'email' => 'user@mail.com',
                'password' => Hash::make('123'),
                'phone' => '0821',
                'approved' => 1,
                'role_id' => 3,
            ]
        ];
        
        foreach($users as $user){
            User::create($user);
        }
    }
}
