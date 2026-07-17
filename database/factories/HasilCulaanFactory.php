<?php

namespace Database\Factories;

use App\Models\HasilCulaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HasilCulaanFactory extends Factory
{
    protected $model = HasilCulaan::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'no_ic' => fake()->unique()->numerify('############'),
            'umur' => fake()->numberBetween(18, 90),
            'no_tel' => '01'.fake()->numerify('########'),
            'bangsa' => 'Melayu',
            'alamat' => fake()->address(),
            'poskod' => fake()->numerify('#####'),
            'negeri' => 'JOHOR',
            'bandar' => 'SEGAMAT',
            'parlimen' => 'SEGAMAT',
            'kadun' => 'BULOH KASAP',
            // hasil_culaan.submitted_by is a NOT NULL FK (users.id, cascade on
            // delete) — see 2025_11_20_054707_create_hasil_culaan_table.php.
            // UserFactory's own telephone column is NOT NULL with no default
            // (2025_11_19_125729_add_telephone_to_users_table.php), so it must
            // be supplied here too.
            'submitted_by' => User::factory()->state(fn () => [
                'telephone' => '01'.fake()->unique()->numerify('########'),
            ]),
        ];
    }
}
