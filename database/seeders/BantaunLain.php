<?php

namespace Database\Seeders;

use App\Models\BantuanLain;
use Illuminate\Database\Seeder;

class BantaunLain extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            [
                'name' => 'ZAKAT PULAU PINANG (ZPP)',
            ],
            [
                'name' => 'JABATAN KEBAJIKAN MASYARAKAT (JKM)',
            ],
            [
                'name' => 'WARGA EMAS (i-Sejahtera)',
            ],
            [
                'name' => 'IBU EMAS (i-Sejahtera)',
            ],
            [
                'name' => 'LAIN-LAIN',
            ],
            [
                'name' => 'TIADA',
            ],
        ];

        foreach($data as $d){
            BantuanLain::create($d);
        }
    }
}
