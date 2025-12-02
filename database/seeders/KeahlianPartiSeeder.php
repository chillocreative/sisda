<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KeahlianParti;

class KeahlianPartiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'KEADILAN',
            'PPBM',
            'UMNO',
            'DAP',
            'MIC',
            'MCA',
            'GERAKAN',
            'PUTRA',
            'PBM',
            'MUDA',
            'PEJUANG',
            'TIDAK PASTI',
            'TIDAK BERPARTI',
        ];

        foreach ($data as $nama) {
            KeahlianParti::firstOrCreate(['nama' => $nama]);
        }

        $this->command->info('Keahlian Parti seeded successfully!');
    }
}
