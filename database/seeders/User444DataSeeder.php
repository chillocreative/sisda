<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HasilCulaan;
use App\Models\DataPengundi;
use App\Models\User;
use Carbon\Carbon;

class User444DataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get User 444 (ID 6)
        $user = User::find(6);
        
        if (!$user) {
            $this->command->error('User 444 (ID 6) not found!');
            return;
        }

        $this->command->info('Seeding data for User 444 (ID 6)...');

        // Create Data for User 444 in Pinang Tunggal (Same as User 333)
        $this->createHasilCulaan($user->id, 'Pinang Tunggal', 2);
        $this->createDataPengundi($user->id, 'Pinang Tunggal', 2);

        $this->command->info('Seeding completed!');
    }

    private function createHasilCulaan($userId, $kadun, $count, $bandar = 'Kepala Batas', $negeri = 'Pulau Pinang')
    {
        for ($i = 0; $i < $count; $i++) {
            HasilCulaan::create([
                'nama' => fake()->name() . ' (User 444)',
                'no_ic' => fake()->numerify('############'),
                'umur' => rand(20, 80),
                'no_tel' => fake()->numerify('01########'),
                'bangsa' => 'Melayu',
                'alamat' => fake()->address(),
                'poskod' => fake()->postcode(),
                'negeri' => $negeri,
                'bandar' => $bandar,
                'kadun' => $kadun,
                'bil_isi_rumah' => rand(1, 10),
                'pendapatan_isi_rumah' => rand(1000, 10000),
                'pekerjaan' => 'Swasta',
                'pemilik_rumah' => 'Sendiri',
                'jenis_sumbangan' => 'Wang Tunai',
                'tujuan_sumbangan' => 'Bantuan Sara Hidup',
                'submitted_by' => $userId,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
            ]);
        }
    }

    private function createDataPengundi($userId, $kadun, $count, $bandar = 'Kepala Batas', $negeri = 'Pulau Pinang')
    {
        for ($i = 0; $i < $count; $i++) {
            DataPengundi::create([
                'nama' => fake()->name() . ' (User 444)',
                'no_ic' => fake()->numerify('############'),
                'umur' => rand(21, 90),
                'no_tel' => fake()->numerify('01########'),
                'bangsa' => 'Melayu',
                'alamat' => fake()->address(),
                'poskod' => fake()->postcode(),
                'negeri' => $negeri,
                'bandar' => $bandar,
                'parlimen' => $bandar,
                'kadun' => $kadun,
                'keahlian_parti' => 'Ahli Biasa',
                'kecenderungan_politik' => 'Atas Pagar',
                'submitted_by' => $userId,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
            ]);
        }
    }
}
