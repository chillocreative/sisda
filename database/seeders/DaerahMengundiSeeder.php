<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DaerahMengundi;
use App\Models\Bandar;

class DaerahMengundiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Data extracted from SPR official gazette for P041 Kepala Batas
     */
    public function run(): void
    {
        // Find Kepala Batas (P41)
        $kepalaBatas = Bandar::where('kod_parlimen', 'P41')->first();
        
        if (!$kepalaBatas) {
            $this->command->warn('Kepala Batas (P41) not found in bandar table. Skipping seeder.');
            return;
        }

        // Clear existing data for this Parlimen
        DaerahMengundi::where('bandar_id', $kepalaBatas->id)->delete();

        // Complete list of 29 Daerah Mengundi for P041 Kepala Batas
        // Source: SPR Federal Gazette (18 July 2023)
        $daerahMengundiList = [
            // N01 Penaga
            ['kod_dm' => '041/01/01', 'nama' => 'KUALA MUDA'],
            ['kod_dm' => '041/01/02', 'nama' => 'PULAU MERTAJAM'],
            ['kod_dm' => '041/01/03', 'nama' => 'PASIR GEBU'],
            ['kod_dm' => '041/01/04', 'nama' => 'PENAGA'],
            ['kod_dm' => '041/01/05', 'nama' => 'KOTA AUR'],
            ['kod_dm' => '041/01/06', 'nama' => 'PERMATANG JANGGUS'],
            ['kod_dm' => '041/01/07', 'nama' => 'GUAR KEPAH'],
            ['kod_dm' => '041/01/08', 'nama' => 'LINTANG MERIAM'],
            ['kod_dm' => '041/01/09', 'nama' => 'BAKAU TUA'],
            ['kod_dm' => '041/01/10', 'nama' => 'PERMATANG RAWAS'],
            ['kod_dm' => '041/01/11', 'nama' => 'TELUK AIR TAWAR'],

            // N02 Permatang Berangan
            ['kod_dm' => '041/02/01', 'nama' => 'PERMATANG BERANGAN'],
            ['kod_dm' => '041/02/02', 'nama' => 'LAHAR KEPAR'],
            ['kod_dm' => '041/02/03', 'nama' => 'PERMATANG CHETAK'],
            ['kod_dm' => '041/02/04', 'nama' => 'ARA RENDANG'],
            ['kod_dm' => '041/02/05', 'nama' => 'PADANG BENGGALI'],
            ['kod_dm' => '041/02/06', 'nama' => 'PERMATANG BERTAM'],
            ['kod_dm' => '041/02/07', 'nama' => 'PERMATANG PAK MAHAT'],
            ['kod_dm' => '041/02/08', 'nama' => 'PERMATANG SAGA'],
            ['kod_dm' => '041/02/09', 'nama' => 'KEPALA BATAS'],
            ['kod_dm' => '041/02/10', 'nama' => 'PAYA KELADI'],

            // N03 Bertam
            ['kod_dm' => '041/03/01', 'nama' => 'PINANG TUNGGAL'],
            ['kod_dm' => '041/03/02', 'nama' => 'BERTAM INDAH'],
            ['kod_dm' => '041/03/03', 'nama' => 'BERTAM RIA'],
            ['kod_dm' => '041/03/04', 'nama' => 'BERTAM PERDANA'],
            ['kod_dm' => '041/03/05', 'nama' => 'PONGSU SERIBU'],
            ['kod_dm' => '041/03/06', 'nama' => 'KUALA BERANGAN'],
            ['kod_dm' => '041/03/07', 'nama' => 'PERMATANG BENUAN'],
            ['kod_dm' => '041/03/08', 'nama' => 'PADANG TEMBUSU'],
        ];

        foreach ($daerahMengundiList as $dm) {
            DaerahMengundi::create([
                'kod_dm' => $dm['kod_dm'],
                'nama' => $dm['nama'],
                'bandar_id' => $kepalaBatas->id,
            ]);
        }

        $this->command->info('Successfully seeded 29 Daerah Mengundi for P041 Kepala Batas!');
    }
}
