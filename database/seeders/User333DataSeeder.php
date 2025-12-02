<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HasilCulaan;
use App\Models\DataPengundi;
use App\Models\User;
use Carbon\Carbon;

class User333DataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get User 333 (ID 5)
        $user = User::find(5);
        
        if (!$user) {
            $this->command->error('User 333 (ID 5) not found!');
            return;
        }

        $this->command->info('Seeding data for testing User 333 access control...');

        // 1. Visible Data (In Pinang Tunggal)
        $this->createHasilCulaan($user->id, 'Pinang Tunggal', 3);
        $this->createDataPengundi($user->id, 'Pinang Tunggal', 3);

        // 2. Invisible Data (Same Bandar, Different KADUN - Penaga)
        // We'll assign these to Admin (ID 1) or just create them with User 333 ID but different KADUN 
        // (technically a user shouldn't be able to submit for other KADUNs, but for testing visibility logic it works)
        // Better to assign to Admin (ID 1) to simulate other users' data
        $adminId = 1;
        $this->createHasilCulaan($adminId, 'Penaga', 2);
        $this->createDataPengundi($adminId, 'Penaga', 2);

        // 3. Invisible Data (Same Bandar, Different KADUN - Bertam)
        $this->createHasilCulaan($adminId, 'Bertam', 2);
        $this->createDataPengundi($adminId, 'Bertam', 2);

        // 4. Invisible Data (Different Bandar/Negeri - e.g. Pantai Jerejak)
        $this->createHasilCulaan($adminId, 'Pantai Jerejak', 2, 'Bayan Baru', 'Pulau Pinang');
        $this->createDataPengundi($adminId, 'Pantai Jerejak', 2, 'Bayan Baru', 'Pulau Pinang');

        $this->command->info('Seeding completed!');
    }

    private function createHasilCulaan($userId, $kadun, $count, $bandar = 'Kepala Batas', $negeri = 'Pulau Pinang')
    {
        for ($i = 0; $i < $count; $i++) {
            HasilCulaan::create([
                'nama' => fake()->name(),
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
                'nama' => fake()->name(),
                'no_ic' => fake()->numerify('############'),
                'umur' => rand(21, 90),
                'no_tel' => fake()->numerify('01########'),
                'bangsa' => 'Melayu',
                'alamat' => fake()->address(),
                'poskod' => fake()->postcode(),
                'negeri' => $negeri,
                'bandar' => $bandar,
                'parlimen' => $bandar, // Usually same as bandar in this system
                'kadun' => $kadun,
                'keahlian_parti' => 'Ahli Biasa',
                'kecenderungan_politik' => 'Atas Pagar',
                'submitted_by' => $userId,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
            ]);
        }
    }
}
