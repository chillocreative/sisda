<?php

namespace Database\Seeders;

use App\Models\MPKK as AppMPKK;
use Illuminate\Database\Seeder;

class MPKK extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $mpkk = [
            [
                'name' => 'Kuala Muda',
                'kadun_id' => 1,
            ],
            [
                'name' => 'Penaga',
                'kadun_id' => 1,
            ],
            [
                'name' => 'Pasir Gebu',
                'kadun_id' => 1,
            ],
            [
                'name' => 'Bakau Tua',
                'kadun_id' => 1,
            ],
            [
                'name' => 'Pulau Mertajam',
                'kadun_id' => 1,
            ],
            [
                'name' => 'Lahar Kepar',
                'kadun_id' => 1,
            ],
            [
                'name' => 'Permatang Bendahari',
                'kadun_id' => 1,
            ],
            [
                'name' => 'Permatang Keriang',
                'kadun_id' => 1,
            ],
            [
                'name' => 'Permatang Tiga Ringgir',
                'kadun_id' => 1,
            ],  

            [
                'name' => 'Permatang Sintok',
                'kadun_id' => 2,
            ],
            [
                'name' => 'Padang Benggali',
                'kadun_id' => 2,
            ],
            [
                'name' => 'Jalan Kedah',
                'kadun_id' => 2,
            ],
            [
                'name' => 'Kampung Datuk',
                'kadun_id' => 2,
            ],
            [
                'name' => 'Pongsu Seribu',
                'kadun_id' => 2,
            ],
            [
                'name' => 'Kepala Batas',
                'kadun_id' => 2,
            ],
            [
                'name' => 'Permatang Rambai',
                'kadun_id' => 2,
            ],
            [
                'name' => 'Permatang Bertam',
                'kadun_id' => 2,
            ],
            [
                'name' => 'Permatang Serdang',
                'kadun_id' => 2,
            ],

            [
                'name' => 'Ladang Malakoff (Bertam Perdana)',
                'kadun_id' => 3,
            ],
            [
                'name' => 'Permatang Langsat',
                'kadun_id' => 3,
            ],
            [
                'name' => 'Paya Keladi',
                'kadun_id' => 3,
            ],
            [
                'name' => 'Permatang Tinggi B',
                'kadun_id' => 3,
            ],
            [
                'name' => 'Bertam Indah',
                'kadun_id' => 3,
            ],
            [
                'name' => 'Kampung Baharu',
                'kadun_id' => 3,
            ],
            [
                'name' => 'Bumbung Lima',
                'kadun_id' => 3,
            ],
            [
                'name' => 'Kubang Menerong',
                'kadun_id' => 3,
            ],
            [
                'name' => 'Kampung Selamat Utara',
                'kadun_id' => 3,
            ],
        ];
        
        foreach($mpkk as $data){
            AppMPKK::create($data);
        };
    }
}
