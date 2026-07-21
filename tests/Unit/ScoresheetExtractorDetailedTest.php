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

    /**
     * THE regression test for the production defect: an extraction that returned
     * only the sheet's UNDI POS line (PN 98 / BN 73) while the printed JUMLAH row
     * says 4,471 / 4,549. Every row balanced internally, so the old per-row checks
     * passed it — publishing a ~98% undercount. Reconciling the rows against the
     * sheet's own printed total is what catches it.
     */
    public function test_rows_that_do_not_add_up_to_the_printed_jumlah_are_flagged(): void
    {
        $data = $this->fixture();
        $pos = collect($data['rows'])->firstWhere('saluran', 'UNDI POS');
        $data['rows'] = [$pos];                  // exactly what production published
        $data['saluran_count'] = 40;

        $bad = collect(ScoresheetExtractor::reconcileTotals($data));

        $this->assertSame([4471, 4549], $bad->where('rule', 'jumlah_undi')->pluck('jangka')->all());
        $this->assertSame([98, 73], $bad->where('rule', 'jumlah_undi')->pluck('dapat')->all());
        $this->assertContains('saluran_count', $bad->pluck('rule')->all());
    }

    /** A shifted candidate column still totals correctly overall — per column it does not. */
    public function test_a_shifted_candidate_column_is_flagged(): void
    {
        $data = \App\Support\Pilihanraya\Spr760Parser::detailed(
            base_path('tests/Fixtures/Pilihanraya/spr760-juasseh-2023.pdf'),
        );
        $data['jumlah']['undi'] = array_reverse($data['jumlah']['undi']);

        $bad = collect(ScoresheetExtractor::reconcileTotals($data))->where('rule', 'jumlah_undi');

        $this->assertCount(2, $bad, 'kedua-dua lajur calon terpesong apabila ditukar ganti');
    }

    /** No printed JUMLAH row = nothing to reconcile against; do not invent a mismatch. */
    public function test_reconciliation_is_skipped_when_no_total_is_printed(): void
    {
        $data = $this->fixture();
        unset($data['jumlah']);

        $this->assertSame([], ScoresheetExtractor::reconcileTotals($data));
    }

    /** The full sheet, read deterministically, must reconcile against its own JUMLAH. */
    public function test_the_real_sheet_reconciles(): void
    {
        $data = \App\Support\Pilihanraya\Spr760Parser::detailed(
            base_path('tests/Fixtures/Pilihanraya/spr760-juasseh-2023.pdf'),
        );

        $this->assertSame([], ScoresheetExtractor::reconcileTotals($data));
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
        $this->assertSame('balance', $bad[0]['rule']);
    }

    public function test_row_with_undi_count_mismatched_to_calon_is_reported(): void
    {
        $data = $this->fixture();
        // Sheet has 2 calon; drop one entry from row 1's positional undi array —
        // this is exactly the column-misalignment bug the feature exists to catch.
        $data['rows'][1]['undi'] = [48];
        $bad = ScoresheetExtractor::validateBalance($data);

        $mismatch = collect($bad)->firstWhere('rule', 'calon_count');
        $this->assertNotNull($mismatch, 'undi count mismatched to calon count mesti dilaporkan');
        $this->assertSame(1, $mismatch['index']);
        $this->assertSame(2, $mismatch['expected']);
        $this->assertSame(1, $mismatch['actual']);
    }

    public function test_row_with_jumlah_undian_not_matching_sum_of_undi_is_reported(): void
    {
        $data = $this->fixture();
        $data['rows'][1]['jumlah_undian'] = 999;   // sepatutnya 48+76=124
        $bad = ScoresheetExtractor::validateBalance($data);

        $mismatch = collect($bad)->firstWhere('rule', 'jumlah_undian');
        $this->assertNotNull($mismatch, 'jumlah_undian != sum(undi) mesti dilaporkan');
        $this->assertSame(1, $mismatch['index']);
        $this->assertSame(124, $mismatch['jangka']);
        $this->assertSame(999, $mismatch['dapat']);
    }

    public function test_jumlah_block_calon_count_mismatch_is_reported(): void
    {
        $data = $this->fixture();
        $data['jumlah']['undi'] = [4471];   // patut 2 nilai selaras dengan calon
        $bad = ScoresheetExtractor::validateBalance($data);

        $mismatch = collect($bad)->firstWhere('rule', 'calon_count');
        $this->assertNotNull($mismatch);
        $this->assertSame('jumlah', $mismatch['index']);
    }

    public function test_jumlah_block_jumlah_undian_mismatch_is_reported(): void
    {
        $data = $this->fixture();
        $data['jumlah']['jumlah_undian'] = 1;
        $bad = ScoresheetExtractor::validateBalance($data);

        $mismatch = collect($bad)->firstWhere('rule', 'jumlah_undian');
        $this->assertNotNull($mismatch);
        $this->assertSame('jumlah', $mismatch['index']);
        $this->assertSame(9020, $mismatch['jangka']);
        $this->assertSame(1, $mismatch['dapat']);
    }
}
