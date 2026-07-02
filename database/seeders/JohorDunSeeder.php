<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bandar;
use App\Models\Kadun;

class JohorDunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $duns = [
            'P140' => [
                ['code' => 'N01', 'name' => 'Buloh Kasap'],
                ['code' => 'N02', 'name' => 'Jementah'],
            ],
            'P141' => [
                ['code' => 'N03', 'name' => 'Pemanis'],
                ['code' => 'N04', 'name' => 'Kemelah'],
            ],
            'P142' => [
                ['code' => 'N05', 'name' => 'Tenang'],
                ['code' => 'N06', 'name' => 'Bekok'],
            ],
            'P143' => [
                ['code' => 'N07', 'name' => 'Bukit Kepong'],
                ['code' => 'N08', 'name' => 'Bukit Pasir'],
            ],
            'P144' => [
                ['code' => 'N09', 'name' => 'Gambir'],
                ['code' => 'N10', 'name' => 'Tangkak'],
                ['code' => 'N11', 'name' => 'Serom'],
            ],
            'P145' => [
                ['code' => 'N12', 'name' => 'Bentayan'],
                ['code' => 'N13', 'name' => 'Simpang Jeram'],
                ['code' => 'N14', 'name' => 'Bukit Naning'],
            ],
            'P146' => [
                ['code' => 'N15', 'name' => 'Maharani'],
                ['code' => 'N16', 'name' => 'Sungai Balang'],
            ],
            'P147' => [
                ['code' => 'N17', 'name' => 'Semerah'],
                ['code' => 'N18', 'name' => 'Sri Medan'],
            ],
            'P148' => [
                ['code' => 'N19', 'name' => 'Yong Peng'],
                ['code' => 'N20', 'name' => 'Semarang'],
            ],
            'P149' => [
                ['code' => 'N21', 'name' => 'Parit Yaani'],
                ['code' => 'N22', 'name' => 'Parit Raja'],
            ],
            'P150' => [
                ['code' => 'N23', 'name' => 'Penggaram'],
                ['code' => 'N24', 'name' => 'Senggarang'],
                ['code' => 'N25', 'name' => 'Rengit'],
            ],
            'P151' => [
                ['code' => 'N26', 'name' => 'Machap'],
                ['code' => 'N27', 'name' => 'Layang-Layang'],
            ],
            'P152' => [
                ['code' => 'N28', 'name' => 'Mengkibol'],
                ['code' => 'N29', 'name' => 'Mahkota'],
            ],
            'P153' => [
                ['code' => 'N30', 'name' => 'Paloh'],
                ['code' => 'N31', 'name' => 'Kahang'],
            ],
            'P154' => [
                ['code' => 'N32', 'name' => 'Endau'],
                ['code' => 'N33', 'name' => 'Tenggaroh'],
            ],
            'P155' => [
                ['code' => 'N34', 'name' => 'Panti'],
                ['code' => 'N35', 'name' => 'Pasir Raja'],
            ],
            'P156' => [
                ['code' => 'N36', 'name' => 'Sedili'],
                ['code' => 'N37', 'name' => 'Johor Lama'],
            ],
            'P157' => [
                ['code' => 'N38', 'name' => 'Penawar'],
                ['code' => 'N39', 'name' => 'Tanjung Surat'],
            ],
            'P158' => [
                ['code' => 'N40', 'name' => 'Tiram'],
                ['code' => 'N41', 'name' => 'Puteri Wangsa'],
            ],
            'P159' => [
                ['code' => 'N42', 'name' => 'Johor Jaya'],
                ['code' => 'N43', 'name' => 'Permas'],
            ],
            'P160' => [
                ['code' => 'N44', 'name' => 'Larkin'],
                ['code' => 'N45', 'name' => 'Stulang'],
            ],
            'P161' => [
                ['code' => 'N46', 'name' => 'Perling'],
                ['code' => 'N47', 'name' => 'Kempas'],
            ],
            'P162' => [
                ['code' => 'N48', 'name' => 'Skudai'],
                ['code' => 'N49', 'name' => 'Kota Iskandar'],
            ],
            'P163' => [
                ['code' => 'N50', 'name' => 'Bukit Permai'],
                ['code' => 'N51', 'name' => 'Bukit Batu'],
                ['code' => 'N52', 'name' => 'Senai'],
            ],
            'P164' => [
                ['code' => 'N53', 'name' => 'Benut'],
                ['code' => 'N54', 'name' => 'Pulai Sebatang'],
            ],
            'P165' => [
                ['code' => 'N55', 'name' => 'Pekan Nanas'],
                ['code' => 'N56', 'name' => 'Kukup'],
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

        $this->command->info('Johor DUNs seeded successfully!');
    }
}
