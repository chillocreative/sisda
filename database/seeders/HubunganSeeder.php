<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Hubungan;

class HubunganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Suami',
            'Isteri',
            'Anak',
            'Menantu',
            'Adik',
            'Abang',
        ];

        foreach ($data as $nama) {
            Hubungan::firstOrCreate(['nama' => $nama]);
        }

        $this->command->info('Hubungan seeded successfully!');
    }
}
