<?php

namespace Database\Seeders;

use App\Models\KecenderunganPolitik as AppKecenderunganPolitik;
use Illuminate\Database\Seeder;

class KecenderunganPolitik extends Seeder
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
                'name' => 'PAKATAN HARAPAN (PH)',
            ],
            [
                'name' => 'PERIKATAN NASIONAL (PN)',
            ],
            [
                'name' => 'TIDAK PASTI',
            ],
        ];

        foreach($data as $d){
            AppKecenderunganPolitik::create($d);
        }
    }
}
