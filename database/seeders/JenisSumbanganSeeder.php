<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JenisSumbangan;

class JenisSumbanganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Hamper Barangan Keperluan Dapur',
            'Wang Tunai',
            'Hamper Perayaan',
            'Tiada',
            'Lain-lain',
        ];

        foreach ($data as $nama) {
            JenisSumbangan::firstOrCreate(['nama' => $nama]);
        }

        $this->command->info('Jenis Sumbangan seeded successfully!');
    }
}
