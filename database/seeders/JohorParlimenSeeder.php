<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bandar;
use App\Models\Negeri;

class JohorParlimenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Self-sufficient: create Negeri Johor if it's missing so the whole
        // Johor seed chain (Parlimen -> DUN -> DM -> Lokaliti) can never
        // silently no-op on a fresh/partial production DB. Idempotent.
        $johor = Negeri::firstOrCreate(['nama' => 'Johor']);

        $parlimens = [
            ['code' => 'P140', 'name' => 'Segamat'],
            ['code' => 'P141', 'name' => 'Sekijang'],
            ['code' => 'P142', 'name' => 'Labis'],
            ['code' => 'P143', 'name' => 'Pagoh'],
            ['code' => 'P144', 'name' => 'Ledang'],
            ['code' => 'P145', 'name' => 'Bakri'],
            ['code' => 'P146', 'name' => 'Muar'],
            ['code' => 'P147', 'name' => 'Parit Sulong'],
            ['code' => 'P148', 'name' => 'Ayer Hitam'],
            ['code' => 'P149', 'name' => 'Sri Gading'],
            ['code' => 'P150', 'name' => 'Batu Pahat'],
            ['code' => 'P151', 'name' => 'Simpang Renggam'],
            ['code' => 'P152', 'name' => 'Kluang'],
            ['code' => 'P153', 'name' => 'Sembrong'],
            ['code' => 'P154', 'name' => 'Mersing'],
            ['code' => 'P155', 'name' => 'Tenggara'],
            ['code' => 'P156', 'name' => 'Kota Tinggi'],
            ['code' => 'P157', 'name' => 'Pengerang'],
            ['code' => 'P158', 'name' => 'Tebrau'],
            ['code' => 'P159', 'name' => 'Pasir Gudang'],
            ['code' => 'P160', 'name' => 'Johor Bahru'],
            ['code' => 'P161', 'name' => 'Pulai'],
            ['code' => 'P162', 'name' => 'Iskandar Puteri'],
            ['code' => 'P163', 'name' => 'Kulai'],
            ['code' => 'P164', 'name' => 'Pontian'],
            ['code' => 'P165', 'name' => 'Tanjung Piai'],
        ];

        foreach ($parlimens as $parlimen) {
            Bandar::updateOrCreate(
                [
                    'kod_parlimen' => $parlimen['code'],
                    'negeri_id' => $johor->id
                ],
                [
                    'nama' => $parlimen['name']
                ]
            );
        }

        $this->command->info('Johor Parliaments seeded successfully!');
    }
}
