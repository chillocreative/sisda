<?php

namespace Database\Seeders;

use App\Models\JenisSumbangan as AppJenisSumbangan;
use Illuminate\Database\Seeder;

class JenisSumbangan extends Seeder
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
                'name' => 'Hamper Barangan Keperluan Dapur',
            ],
            [
                'name' => 'Wang Tunai', 
            ],
            [
                'name' => 'Hamper Perayaan', 
            ],
            [
                'name' => 'Lain', 
            ],
        ];

        foreach($data as $d){
            AppJenisSumbangan::create($d);
        }
    }
}
