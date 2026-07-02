<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JohorSeeder extends Seeder
{
    /**
     * Seed the full Johor electoral hierarchy: Parlimen -> DUN -> Daerah
     * Mengundi -> Lokaliti. All sub-seeders are idempotent (updateOrCreate),
     * so this is safe to run on every deploy.
     */
    public function run(): void
    {
        $this->call([
            JohorParlimenSeeder::class,
            JohorDunSeeder::class,
            JohorDmLokalitiSeeder::class,
        ]);
    }
}
