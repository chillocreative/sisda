<?php

namespace Database\Seeders;

use App\Models\KeahlianPartai as AppKeahlianPartai;
use Illuminate\Database\Seeder;

class KeahlianPartai extends Seeder
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
                'name' => 'PKR',
            ],
            [
                'name' => 'PPBM',
            ],
            [
                'name' => 'UMNO',
            ],
            [
                'name' => 'DAP',
            ],
            [
                'name' => 'MIC',
            ],
            [
                'name' => 'MCA',
            ],
            [
                'name' => 'GERAKAN',
            ],
            [
                'name' => 'PUTRA',
            ],
            [
                'name' => 'TIADAK PASTI',
            ],
        ];

        foreach($data as $d){
            AppKeahlianPartai::create($d);
        }
    }
}
