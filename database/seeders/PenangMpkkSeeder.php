<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Kadun;
use App\Models\Mpkk;

class PenangMpkkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Pinang Tunggal' => [
                'Bertam Indah',
                'Bumbung Lima',
                'Kampung Baharu',
                'Kampung Selamat Selatan',
                'Kampung Selamat Utara',
                'Kampung Tok Bedu',
                'Kubang Menerong',
                'Ladang Malakoff',
                'Paya Keladi',
                'Permatang Langsat',
                'Pinang Tunggal',
                'Permatang Tinggi B',
            ],
            'Bertam' => [
                'Jalan Kedah',
                'Kampung Datok',
                'Kepala Batas',
                'Padang Benggali',
                'Permatang Bertam',
                'Permatang Rambai',
                'Permatang Serdang',
                'Permatang Sintok',
                'Pongsu Seribu',
            ],
            'Penaga' => [
                'Bakar Kapur',
                'Bakau Tua',
                'Guar Kepah',
                'Kuala Muda',
                'Kota Aur',
                'Lahar Kepar',
                'Pasir Gebu',
                'Penaga',
                'Permatang Janggus',
                'Permatang Kerian',
                'Permatang Tiga Ringgit',
                'Pulau Mertajam',
            ],
        ];

        foreach ($data as $kadunName => $mpkkList) {
            $kadun = Kadun::where('nama', $kadunName)->first();

            if (!$kadun) {
                $this->command->error("KADUN {$kadunName} not found!");
                continue;
            }

            foreach ($mpkkList as $mpkkName) {
                Mpkk::updateOrCreate(
                    [
                        'nama' => $mpkkName,
                        'kadun_id' => $kadun->id
                    ]
                );
            }
        }

        $this->command->info('Penang MPKKs seeded successfully!');
    }
}
