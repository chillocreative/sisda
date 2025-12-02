<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Negeri;

class NegeriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $negeriList = [
            'Johor',
            'Kedah',
            'Kelantan',
            'Melaka',
            'Negeri Sembilan',
            'Pahang',
            'Pulau Pinang',
            'Perak',
            'Perlis',
            'Sabah',
            'Sarawak',
            'Selangor',
            'Terengganu',
            'Wilayah Persekutuan Kuala Lumpur',
            'Wilayah Persekutuan Labuan',
            'Wilayah Persekutuan Putrajaya',
        ];

        foreach ($negeriList as $negeri) {
            Negeri::create([
                'nama' => $negeri,
            ]);
        }
    }
}
