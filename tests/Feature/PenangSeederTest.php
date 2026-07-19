<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use Database\Seeders\PenangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenangSeederTest extends TestCase
{
    use RefreshDatabase;

    private function penang(): Negeri
    {
        return Negeri::whereRaw('UPPER(TRIM(nama)) = ?', ['PULAU PINANG'])->firstOrFail();
    }

    public function test_seeds_13_parlimen_and_40_dun_with_bertam_under_kepala_batas(): void
    {
        $this->seed(PenangSeeder::class);

        $ns = $this->penang();
        $bandarIds = Bandar::where('negeri_id', $ns->id)->pluck('id');

        $this->assertSame(13, $bandarIds->count());
        $this->assertSame(40, Kadun::whereIn('bandar_id', $bandarIds)->count());

        $kepalaBatas = Bandar::where('negeri_id', $ns->id)->whereRaw('UPPER(nama) = ?', ['KEPALA BATAS'])->first();
        $this->assertSame('P041', $kepalaBatas->kod_parlimen);

        $bertam = Kadun::whereRaw('UPPER(nama) = ?', ['BERTAM'])->first();
        $this->assertSame($kepalaBatas->id, $bertam->bandar_id);
        $this->assertSame('N02', $bertam->kod_dun);
    }

    public function test_reconciles_existing_upload_rows_and_is_idempotent(): void
    {
        $ns = Negeri::create(['nama' => 'PULAU PINANG']);
        $existingBandar = Bandar::create(['nama' => 'KEPALA BATAS', 'negeri_id' => $ns->id]); // no kod
        Kadun::create(['nama' => 'BERTAM', 'bandar_id' => $existingBandar->id]);               // no kod

        $this->seed(PenangSeeder::class);
        $this->seed(PenangSeeder::class); // twice — no duplicates

        $bandarIds = Bandar::where('negeri_id', $ns->id)->pluck('id');
        $this->assertSame(13, $bandarIds->count());
        $this->assertSame(40, Kadun::whereIn('bandar_id', $bandarIds)->count());

        $bertam = Kadun::whereRaw('UPPER(nama) = ?', ['BERTAM'])->first();
        $this->assertSame('N02', $bertam->kod_dun);
    }
}
