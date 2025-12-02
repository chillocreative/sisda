<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KecenderunganPolitik;

class KecenderunganPolitikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'PAKATAN HARAPAN (PH/BN)',
            'BARISAN NASIONAL (BN/PN)',
            'TIDAK PASTI',
        ];

        foreach ($data as $nama) {
            KecenderunganPolitik::firstOrCreate(['nama' => $nama]);
        }

        $this->command->info('Kecenderungan Politik seeded successfully!');
    }
}
