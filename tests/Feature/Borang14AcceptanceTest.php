<?php

namespace Tests\Feature;

use App\Services\Pilihanraya\ScoresheetExtractor;
use Tests\TestCase;

class Borang14AcceptanceTest extends TestCase
{
    public function test_juasseh_figures_match_the_printed_sheet(): void
    {
        $d = json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true);

        // Angka disahkan manual dari 'Score Sheet Juasseh - PRN N9 - 2023.pdf'
        $this->assertSame(13408, $d['jumlah_pemilih']);
        $this->assertSame(9122, $d['jumlah']['a']);
        $this->assertSame([4471, 4549], $d['jumlah']['undi']);
        $this->assertSame(9020, $d['jumlah']['jumlah_undian']);
        $this->assertSame(87, $d['jumlah']['ditolak']);
        $this->assertSame(15, $d['jumlah']['tidak_dimasukkan']);

        // Silang-semak: (A) == undi calon + (C) + (D)
        $this->assertSame(
            $d['jumlah']['a'],
            array_sum($d['jumlah']['undi']) + $d['jumlah']['ditolak'] + $d['jumlah']['tidak_dimasukkan'],
        );

        // Sheet tiada rujukan pengundi berdaftar — column (A) ialah kertas undi
        // dalam peti, bukan bilangan pendaftaran; extractor tidak boleh reka field ini.
        $this->assertArrayNotHasKey('berdaftar', $d);

        // Sahkan struktur baris subset (4 daripada 40) — Undi Pos tiada saluran/DM,
        // dan setiap baris mesti seimbang sendiri walaupun jumlah penuh ada di 'jumlah'.
        $undiPos = collect($d['rows'])->firstWhere('saluran', 'UNDI POS');
        $this->assertNotNull($undiPos);
        $this->assertSame(203, $undiPos['a']);
        $this->assertSame([98, 73], $undiPos['undi']);
        $this->assertSame(171, $undiPos['jumlah_undian']);
        $this->assertSame(18, $undiPos['ditolak']);
        $this->assertSame(14, $undiPos['tidak_dimasukkan']);
        $this->assertNull($undiPos['dm_kod']);

        $dmCodes = collect($d['rows'])->pluck('dm_kod')->filter()->unique()->values()->all();
        $this->assertNotEmpty($dmCodes);

        $this->assertSame([], ScoresheetExtractor::validateBalance($d));
    }
}
