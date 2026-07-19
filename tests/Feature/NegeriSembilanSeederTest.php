<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use Database\Seeders\NegeriSembilanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegeriSembilanSeederTest extends TestCase
{
    use RefreshDatabase;

    private function ns(): Negeri
    {
        return Negeri::whereRaw('UPPER(TRIM(nama)) = ?', ['NEGERI SEMBILAN'])->firstOrFail();
    }

    public function test_seeds_8_parlimen_and_36_dun_with_juasseh_under_kuala_pilah(): void
    {
        $this->seed(NegeriSembilanSeeder::class);

        $ns = $this->ns();
        $bandarIds = Bandar::where('negeri_id', $ns->id)->pluck('id');

        $this->assertSame(8, $bandarIds->count());
        $this->assertSame(36, Kadun::whereIn('bandar_id', $bandarIds)->count());

        $kualaPilah = Bandar::where('negeri_id', $ns->id)->whereRaw('UPPER(nama) = ?', ['KUALA PILAH'])->first();
        $this->assertSame('P129', $kualaPilah->kod_parlimen);

        $juasseh = Kadun::whereRaw('UPPER(nama) = ?', ['JUASSEH'])->first();
        $this->assertSame($kualaPilah->id, $juasseh->bandar_id);   // correct parliament
        $this->assertSame('N15', $juasseh->kod_dun);
    }

    public function test_reconciles_existing_upload_rows_and_is_idempotent(): void
    {
        // Simulate what syncMasterData leaves behind: a Bandar + DUN with names
        // but no kod_*, and the DUN linked to that Bandar.
        $ns = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $existingBandar = Bandar::create(['nama' => 'KUALA PILAH', 'negeri_id' => $ns->id]);
        Kadun::create(['nama' => 'JUASSEH', 'bandar_id' => $existingBandar->id]);

        $this->seed(NegeriSembilanSeeder::class);
        $this->seed(NegeriSembilanSeeder::class); // twice — must not duplicate

        $bandarIds = Bandar::where('negeri_id', $ns->id)->pluck('id');
        $this->assertSame(8, $bandarIds->count());                              // no duplicate KUALA PILAH
        $this->assertSame(36, Kadun::whereIn('bandar_id', $bandarIds)->count()); // one JUASSEH, not two

        $juasseh = Kadun::whereRaw('UPPER(nama) = ?', ['JUASSEH'])->first();
        $this->assertSame('N15', $juasseh->kod_dun);                            // kod backfilled onto the existing row
    }
}
