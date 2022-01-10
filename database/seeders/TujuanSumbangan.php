<?php

namespace Database\Seeders;

use App\Models\TujuanSumbangan as AppTujuanSumbangan;
use Illuminate\Database\Seeder;

class TujuanSumbangan extends Seeder
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
                'name' => 'Asnaf / Keluarga Miskin',
            ],
            [
                'name' => 'Kematian',
            ],
            [
                'name' => 'Kelahiran Bayi',
            ],
            [
                'name' => 'Rumah Terbakar',
            ],
            [
                'name' => 'Kemalangan Jalan Raya',
            ],
            [
                'name' => 'Kuarantin Covid-19',
            ],
            [
                'name' => 'Ribut',
            ],
            [
                'name' => 'Banjir',
            ],
        ];

        foreach($data as $d){
            AppTujuanSumbangan::create($d);
        }
    }
}
