<?php

namespace Database\Seeders;

use App\Models\Kadun;
use Illuminate\Database\Seeder;

class KadunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $kaduns = [
            [
                'code' => '01',
                'name' => 'Penaga',
            ],
            [
                'code' => '02',
                'name' => 'Bertam',
            ],
            [
                'code' => '03',
                'name' => 'Pinang Tunggal',
            ],
        ];

        foreach($kaduns as $kadun){
            Kadun::create($kadun);
        }
    }
}
