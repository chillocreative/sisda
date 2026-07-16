<?php

namespace Tests\Unit;

use App\Services\Pilihanraya\ScoresheetExtractor;
use Tests\TestCase;

class ScoresheetExtractorDetailedTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true);
    }

    public function test_balance_holds_for_every_row(): void
    {
        $bad = ScoresheetExtractor::validateBalance($this->fixture());
        $this->assertSame([], $bad, 'Setiap baris mesti: a == sum(undi) + ditolak + tidak_dimasukkan');
    }

    public function test_grand_total_balances(): void
    {
        $j = $this->fixture()['jumlah'];
        $this->assertSame($j['a'], array_sum($j['undi']) + $j['ditolak'] + $j['tidak_dimasukkan']);
        $this->assertSame(9122, $j['a']);
        $this->assertSame(9020, $j['jumlah_undian']);
    }

    public function test_undi_pos_row_uses_empty_pusat(): void
    {
        $pos = collect($this->fixture()['rows'])->firstWhere('saluran', 'UNDI POS');
        $this->assertNotNull($pos);
        $this->assertSame('', $pos['pusat']);
        $this->assertSame(203, $pos['a']);
        $this->assertSame([98, 73], $pos['undi']);
    }

    public function test_no_undi_awal_row_is_fabricated(): void
    {
        $awal = collect($this->fixture()['rows'])->firstWhere('saluran', 'UNDI AWAL');
        $this->assertNull($awal, 'Sheet Juasseh tiada Undi Awal — jangan reka baris kosong.');
    }

    public function test_berdaftar_is_never_returned(): void
    {
        foreach ($this->fixture()['rows'] as $r) {
            $this->assertArrayNotHasKey('berdaftar', $r, 'Scoresheet tiada berdaftar — (A) bukan berdaftar.');
        }
    }

    public function test_unbalanced_row_is_reported(): void
    {
        $data = $this->fixture();
        $data['rows'][1]['a'] = 999;   // rosakkan dengan sengaja
        $bad = ScoresheetExtractor::validateBalance($data);
        $this->assertCount(1, $bad);
        $this->assertSame(1, $bad[0]['index']);
    }
}
