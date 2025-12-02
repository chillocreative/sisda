<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BangsaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bangsaList = [
            ['nama' => 'Melayu'],
            ['nama' => 'Cina'],
            ['nama' => 'India'],
            ['nama' => 'Bumiputera Sabah'],
            ['nama' => 'Bumiputera Sarawak'],
            ['nama' => 'Lain-lain'],
        ];

        foreach ($bangsaList as $bangsa) {
            \App\Models\Bangsa::create($bangsa);
        }
    }
}
