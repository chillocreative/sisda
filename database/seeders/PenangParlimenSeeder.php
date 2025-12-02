<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bandar;
use App\Models\Negeri;

class PenangParlimenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $penang = Negeri::where('nama', 'Pulau Pinang')->first();

        if (!$penang) {
            $this->command->error('Negeri Pulau Pinang not found!');
            return;
        }

        $parlimens = [
            ['code' => 'P41', 'name' => 'Kepala Batas'],
            ['code' => 'P42', 'name' => 'Tasek Gelugor'],
            ['code' => 'P43', 'name' => 'Bagan'],
            ['code' => 'P44', 'name' => 'Permatang Pauh'],
            ['code' => 'P45', 'name' => 'Bukit Mertajam'],
            ['code' => 'P46', 'name' => 'Batu Kawan'],
            ['code' => 'P47', 'name' => 'Nibong Tebal'],
            ['code' => 'P48', 'name' => 'Bukit Bendera'],
            ['code' => 'P49', 'name' => 'Tanjong'],
            ['code' => 'P50', 'name' => 'Jelutong'],
            ['code' => 'P51', 'name' => 'Bukit Gelugor'],
            ['code' => 'P52', 'name' => 'Bayan Baru'],
            ['code' => 'P53', 'name' => 'Balik Pulau'],
        ];

        foreach ($parlimens as $parlimen) {
            Bandar::updateOrCreate(
                [
                    'kod_parlimen' => $parlimen['code'],
                    'negeri_id' => $penang->id
                ],
                [
                    'nama' => $parlimen['name']
                ]
            );
        }

        $this->command->info('Penang Parliaments seeded successfully!');
    }
}
