<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BantuanLain;

class BantuanLainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'ZAKAT PULAU PINANG (ZPP)',
            'JABATAN KEBAJIKAN MASYARAKAT (JKM)',
            'WARGA EMAS (i-Sejahtera)',
            'SURI EMAS (i-Sejahtera)',
            'TIADA',
            'Ibu Tunggal (i-Sejahtera)',
            'Lain-lain',
        ];

        foreach ($data as $nama) {
            BantuanLain::firstOrCreate(['nama' => $nama]);
        }

        $this->command->info('Bantuan Lain seeded successfully!');
    }
}
