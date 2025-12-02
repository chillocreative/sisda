<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bandar;
use App\Models\Kadun;

class PenangDunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $duns = [
            'P41' => [
                ['code' => 'N01', 'name' => 'Penaga'],
                ['code' => 'N02', 'name' => 'Bertam'],
                ['code' => 'N03', 'name' => 'Pinang Tunggal'],
            ],
            'P42' => [
                ['code' => 'N04', 'name' => 'Permatang Berangan'],
                ['code' => 'N05', 'name' => 'Sungai Dua'],
                ['code' => 'N06', 'name' => 'Telok Ayer Tawar'],
            ],
            'P43' => [
                ['code' => 'N07', 'name' => 'Sungai Puyu'],
                ['code' => 'N08', 'name' => 'Bagan Jermal'],
                ['code' => 'N09', 'name' => 'Bagan Dalam'],
            ],
            'P44' => [
                ['code' => 'N10', 'name' => 'Seberang Jaya'],
                ['code' => 'N11', 'name' => 'Permatang Pasir'],
                ['code' => 'N12', 'name' => 'Penanti'],
            ],
            'P45' => [
                ['code' => 'N13', 'name' => 'Berapit'],
                ['code' => 'N14', 'name' => 'Machang Bubok'],
                ['code' => 'N15', 'name' => 'Padang Lalang'],
            ],
            'P46' => [
                ['code' => 'N16', 'name' => 'Perai'],
                ['code' => 'N17', 'name' => 'Bukit Tengah'],
                ['code' => 'N18', 'name' => 'Bukit Tambun'],
            ],
            'P47' => [
                ['code' => 'N19', 'name' => 'Jawi'],
                ['code' => 'N20', 'name' => 'Sungai Bakap'],
                ['code' => 'N21', 'name' => 'Sungai Acheh'],
            ],
            'P48' => [
                ['code' => 'N22', 'name' => 'Tanjong Bunga'],
                ['code' => 'N23', 'name' => 'Air Putih'],
                ['code' => 'N24', 'name' => 'Kebun Bunga'],
                ['code' => 'N25', 'name' => 'Pulau Tikus'],
            ],
            'P49' => [
                ['code' => 'N26', 'name' => 'Padang Kota'],
                ['code' => 'N27', 'name' => 'Pengkalan Kota'],
                ['code' => 'N28', 'name' => 'Komtar'],
            ],
            'P50' => [
                ['code' => 'N29', 'name' => 'Datok Keramat'],
                ['code' => 'N30', 'name' => 'Sungai Pinang'],
                ['code' => 'N31', 'name' => 'Batu Lancang'],
            ],
            'P51' => [
                ['code' => 'N32', 'name' => 'Seri Delima'],
                ['code' => 'N33', 'name' => 'Air Itam'],
                ['code' => 'N34', 'name' => 'Paya Terubong'],
            ],
            'P52' => [
                ['code' => 'N35', 'name' => 'Batu Uban'],
                ['code' => 'N36', 'name' => 'Pantai Jerejak'],
                ['code' => 'N37', 'name' => 'Batu Maung'],
            ],
            'P53' => [
                ['code' => 'N38', 'name' => 'Bayan Lepas'],
                ['code' => 'N39', 'name' => 'Pulau Betong'],
                ['code' => 'N40', 'name' => 'Telok Bahang'],
            ],
        ];

        foreach ($duns as $parlimenCode => $dunList) {
            $bandar = Bandar::where('kod_parlimen', $parlimenCode)->first();

            if (!$bandar) {
                $this->command->error("Parlimen code {$parlimenCode} not found!");
                continue;
            }

            foreach ($dunList as $dun) {
                Kadun::updateOrCreate(
                    [
                        'kod_dun' => $dun['code'],
                        'bandar_id' => $bandar->id
                    ],
                    [
                        'nama' => $dun['name']
                    ]
                );
            }
        }

        $this->command->info('Penang DUNs seeded successfully!');
    }
}
