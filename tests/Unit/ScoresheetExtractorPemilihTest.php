<?php

namespace Tests\Unit;

use App\Services\Pilihanraya\ScoresheetExtractor;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Finding 1 (pre-deploy review): ScoresheetExtractor coerced an unknown
 * `pemilih` (registered voters) total to 0 instead of leaving it null, both
 * on the AI path (sanitize()) and the deterministic path (fromStandard()).
 * AnalisaComparisonController::storeScenario() stores that straight into
 * `parsed_totals`, so `pemilih_berdaftar: 0` reached ElectionComparisonService
 * and produced a fabricated "pengurangan 13,408 pengundi berdaftar (-100%)"
 * claim — even though the service itself already handled a genuine `null`
 * correctly (see AnalisaElectionComparisonServiceTest).
 *
 * An SPR scoresheet without a "JUMLAH PEMILIH" header is the COMMON case, so
 * this is the dominant real-world path, not an edge case.
 */
class ScoresheetExtractorPemilihTest extends TestCase
{
    private function extractor(): ScoresheetExtractor
    {
        return app(ScoresheetExtractor::class);
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(ScoresheetExtractor::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->extractor(), ...$args);
    }

    /* ----------------------------------------------------------------
     |  AI path — sanitize()
     * ---------------------------------------------------------------- */

    public function test_ai_path_totals_pemilih_stays_null_when_no_row_has_a_figure(): void
    {
        // Mirrors a real SPR Borang 760 sheet with no "JUMLAH PEMILIH" header:
        // Claude returns `pemilih: null` for the totals AND for every row.
        $json = [
            'parties' => ['PERIKATAN NASIONAL', 'PAKATAN HARAPAN'],
            'rows' => [
                ['kawasan' => 'KAMPONG TENGKEK', 'pemilih' => null, 'keluar' => 1500, 'ditolak' => 10,
                    'undi' => ['PERIKATAN NASIONAL' => 700, 'PAKATAN HARAPAN' => 790]],
            ],
            'totals' => ['pemilih' => null, 'keluar' => null, 'ditolak' => null, 'undi' => []],
        ];

        $out = $this->callPrivate('sanitize', [$json]);

        $this->assertNull($out['totals']['pemilih'], 'No row and no printed total carries a pemilih figure — must stay null, not become 0.');
        // Everything else must still compute normally.
        $this->assertSame(1500, $out['totals']['keluar']);
        $this->assertSame(700, $out['totals']['undi']['PERIKATAN NASIONAL']);
    }

    public function test_ai_path_totals_pemilih_sums_when_some_rows_have_a_figure(): void
    {
        $json = [
            'parties' => ['PERIKATAN NASIONAL', 'PAKATAN HARAPAN'],
            'rows' => [
                ['kawasan' => 'KAMPONG TENGKEK', 'pemilih' => 2000, 'keluar' => 1500,
                    'undi' => ['PERIKATAN NASIONAL' => 700, 'PAKATAN HARAPAN' => 790]],
                ['kawasan' => 'BATU HAMPAR', 'pemilih' => null, 'keluar' => 800,
                    'undi' => ['PERIKATAN NASIONAL' => 400, 'PAKATAN HARAPAN' => 390]],
            ],
            'totals' => ['pemilih' => null, 'keluar' => null, 'ditolak' => null, 'undi' => []],
        ];

        $out = $this->callPrivate('sanitize', [$json]);

        // Only the KNOWN row contributes — the unknown row must not be
        // treated as a 0 that silently disappears into a smaller sum either
        // way, but it also must not block the sum from the row that IS known.
        $this->assertSame(2000, $out['totals']['pemilih']);
    }

    public function test_ai_path_totals_pemilih_uses_printed_total_even_when_zero(): void
    {
        // A genuinely printed 0 is a real (if unusual) fact, not "unknown" —
        // it must be kept distinct from the null-coercion bug.
        $json = [
            'parties' => ['PERIKATAN NASIONAL', 'PAKATAN HARAPAN'],
            'rows' => [
                ['kawasan' => 'KAMPONG TENGKEK', 'pemilih' => null, 'keluar' => 1500,
                    'undi' => ['PERIKATAN NASIONAL' => 700, 'PAKATAN HARAPAN' => 790]],
            ],
            'totals' => ['pemilih' => 0, 'keluar' => null, 'ditolak' => null, 'undi' => []],
        ];

        $out = $this->callPrivate('sanitize', [$json]);

        $this->assertSame(0, $out['totals']['pemilih']);
    }

    /* ----------------------------------------------------------------
     |  Deterministic path — fromStandard()
     * ---------------------------------------------------------------- */

    public function test_deterministic_path_totals_pemilih_stays_null_when_no_pemilih_column(): void
    {
        // Mirrors ScoresheetParser::normalize() output when the sheet has no
        // recognised "pemilih" column at all — every row's `pemilih` is null.
        $std = [
            'rows' => [
                ['dm' => 'KAMPONG TENGKEK', 'pemilih' => null, 'keluar' => 1500, 'ph' => 700, 'bn' => 790, 'pn' => 0, 'pejuang' => 0, 'ditolak' => 10],
            ],
            'totals' => ['pemilih' => 0, 'keluar' => 1500, 'ph' => 700, 'bn' => 790, 'pn' => 0, 'pejuang' => 0, 'ditolak' => 10],
        ];

        $out = $this->callPrivate('fromStandard', [$std]);

        $this->assertNull($out['totals']['pemilih'], 'No row carries a pemilih column — deterministic totals must stay null, not 0.');
        $this->assertSame(1500, $out['totals']['keluar']);
    }

    public function test_deterministic_path_totals_pemilih_sums_when_column_present(): void
    {
        $std = [
            'rows' => [
                ['dm' => 'KAMPONG TENGKEK', 'pemilih' => 2000, 'keluar' => 1500, 'ph' => 700, 'bn' => 790, 'pn' => 0, 'pejuang' => 0, 'ditolak' => 10],
                ['dm' => 'BATU HAMPAR', 'pemilih' => 1000, 'keluar' => 800, 'ph' => 400, 'bn' => 390, 'pn' => 0, 'pejuang' => 0, 'ditolak' => 5],
            ],
            'totals' => ['pemilih' => 3000, 'keluar' => 2300, 'ph' => 1100, 'bn' => 1180, 'pn' => 0, 'pejuang' => 0, 'ditolak' => 15],
        ];

        $out = $this->callPrivate('fromStandard', [$std]);

        $this->assertSame(3000, $out['totals']['pemilih']);
    }
}
