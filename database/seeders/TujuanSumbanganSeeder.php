<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TujuanSumbangan;

class TujuanSumbanganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Asnaf / Keluarga Miskin',
            'Kematian',
            'Kelahiran Bayi',
            'Rumah Terbakar',
            'Kemalangan Jalan Raya',
            'Ribut',
            'Banjir',
        ];

        foreach ($data as $nama) {
            TujuanSumbangan::firstOrCreate(['nama' => $nama]);
        }

        $this->command->info('Tujuan Sumbangan seeded successfully!');
    }
}
