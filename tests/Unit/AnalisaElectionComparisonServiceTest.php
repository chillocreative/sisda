<?php

namespace Tests\Unit;

use App\Models\AnalisaScenario;
use App\Services\Pilihanraya\ElectionComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Locks in ElectionComparisonService's number-crunching (scenarioSummary,
 * deltas, fallbackReport) BEFORE and AFTER the null-vs-zero `pemilih` fix.
 *
 * These three methods are private — invoked here via reflection — because
 * the public entry point buildFactPayload() also calls currentRoll()/
 * currentSaluran(), which use MySQL-only SQL (REGEXP, TIMESTAMPDIFF,
 * STR_TO_DATE) that the sqlite test database cannot execute. That gap is
 * pre-existing and unrelated to this bug; testing the private methods
 * directly is the only way to exercise this logic under the current test
 * database, and it is exactly the logic this fix touches.
 */
class AnalisaElectionComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ElectionComparisonService
    {
        return app(ElectionComparisonService::class);
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(ElectionComparisonService::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->service(), ...$args);
    }

    private function scenario(array $totals, array $rows = [], string $label = 'PRN 2023', string $date = '2023-01-01'): AnalisaScenario
    {
        return new AnalisaScenario([
            'label' => $label,
            'election_date' => $date,
            'parsed_totals' => $totals,
            'parsed_rows' => $rows,
        ]);
    }

    /* ----------------------------------------------------------------
     |  Characterization — KNOWN pemilih must behave exactly as today.
     * ---------------------------------------------------------------- */

    public function test_scenario_summary_with_known_pemilih_computes_expected_figures(): void
    {
        $s = $this->scenario([
            'pemilih' => 13408,
            'ditolak' => 100,
            'undi' => ['PN' => 6000, 'PH' => 5000],
            'parties' => ['PN', 'PH'],
        ], [
            ['kawasan' => 'KAMPONG TENGKEK', 'pemilih' => 2000, 'keluar' => 1500, 'undi' => ['PN' => 700, 'PH' => 790]],
        ]);

        $summary = $this->callPrivate('scenarioSummary', [$s]);

        $this->assertSame(13408, $summary['pemilih_berdaftar']);
        $this->assertSame(11100, $summary['undi_keluar']); // 6000+5000+100
        $this->assertSame(82.8, $summary['peratus_keluar']); // round(11100/13408*100,1)
        $this->assertSame('PN', $summary['pemenang']);
        $this->assertSame(1000, $summary['majoriti']); // 6000-5000
        $this->assertSame(2000, $summary['kawasan'][0]['pemilih']);
    }

    public function test_deltas_with_known_pemilih_on_both_sides_computes_expected_change(): void
    {
        $a = $this->scenario([
            'pemilih' => 13408, 'undi' => ['PN' => 6000, 'PH' => 5000], 'parties' => ['PN', 'PH'],
        ], [], 'PRN 2018', '2018-05-09');
        $b = $this->scenario([
            'pemilih' => 14000, 'undi' => ['PN' => 6500, 'PH' => 5200], 'parties' => ['PN', 'PH'],
        ], [], 'PRN 2023', '2023-01-01');

        $summaries = [
            $this->callPrivate('scenarioSummary', [$a]),
            $this->callPrivate('scenarioSummary', [$b]),
        ];

        $deltas = $this->callPrivate('deltas', [$summaries]);

        $this->assertCount(1, $deltas);
        $this->assertSame(592, $deltas[0]['perubahan_pemilih']); // 14000-13408
        $this->assertSame(4.4, $deltas[0]['perubahan_pemilih_pct']); // round(592/13408*100,1)
        $this->assertNotNull($deltas[0]['perubahan_peratus_keluar']);
    }

    public function test_fallback_report_with_known_pemilih_renders_expected_wording(): void
    {
        $facts = [
            'kawasan' => ['nama' => 'JUASSEH'],
            'roll_semasa' => ['tersedia' => false],
            'saluran_semasa' => ['tersedia' => false],
            'senario' => [
                ['label' => 'PRN 2018', 'tahun' => '2018', 'pemenang' => 'PN', 'majoriti' => 1000, 'peratus_keluar' => 82.8],
            ],
            'perubahan' => [
                ['dari' => 'PRN 2018', 'ke' => 'PRN 2023', 'perubahan_pemilih' => 592, 'perubahan_pemilih_pct' => 4.4],
            ],
        ];

        $report = $this->callPrivate('fallbackReport', [$facts]);

        $this->assertStringContainsString('pertambahan 592 pengundi berdaftar (4.4%)', $report['pengundi_baru_lama']['bullet_points'][0]);
        $this->assertStringContainsString('majoriti 1,000 undi; peratus keluar 82.8%', $report['perbandingan_senario'][0]['sorotan']);
    }

    /* ----------------------------------------------------------------
     |  The fix — unknown pemilih must stay null, never become 0/-100%.
     * ---------------------------------------------------------------- */

    public function test_scenario_summary_with_unknown_pemilih_stays_null(): void
    {
        $s = $this->scenario([
            // no 'pemilih' key at all — mirrors Borang14ScenarioMapper::totalPemilih()
            // returning null and the upload extractor's `pemilih: null` contract.
            'undi' => ['PN' => 6000, 'PH' => 5000],
            'ditolak' => 100,
            'parties' => ['PN', 'PH'],
        ], [
            ['kawasan' => 'KAMPONG TENGKEK', 'keluar' => 1500, 'undi' => ['PN' => 700, 'PH' => 790]],
        ]);

        $summary = $this->callPrivate('scenarioSummary', [$s]);

        $this->assertNull($summary['pemilih_berdaftar'], 'Unknown pemilih must stay null, not become 0.');
        $this->assertNull($summary['peratus_keluar'], 'Cannot compute a percentage of an unknown denominator.');
        $this->assertNull($summary['kawasan'][0]['pemilih'], 'Row-level pemilih must also stay null when unknown, not 0.');
        // Everything NOT dependent on pemilih must still compute normally.
        $this->assertSame(11100, $summary['undi_keluar']);
        $this->assertSame('PN', $summary['pemenang']);
        $this->assertSame(1000, $summary['majoriti']);
    }

    public function test_deltas_emit_no_registered_voter_change_when_either_side_unknown(): void
    {
        $known = $this->scenario([
            'pemilih' => 13408, 'undi' => ['PN' => 6000, 'PH' => 5000], 'parties' => ['PN', 'PH'],
        ], [], 'PRN 2018', '2018-05-09');
        $unknown = $this->scenario([
            'undi' => ['PN' => 6500, 'PH' => 5200], 'parties' => ['PN', 'PH'],
        ], [], 'PRN 2023', '2023-01-01');

        // Direction 1: known -> unknown (this is exactly the reported bug:
        // pemilih_berdaftar used to be coerced to 0, producing a fabricated
        // "pengurangan 13,408 pengundi berdaftar (-100%)").
        $summaries = [
            $this->callPrivate('scenarioSummary', [$known]),
            $this->callPrivate('scenarioSummary', [$unknown]),
        ];
        $deltas = $this->callPrivate('deltas', [$summaries]);
        $this->assertNull($deltas[0]['perubahan_pemilih']);
        $this->assertNull($deltas[0]['perubahan_pemilih_pct']);

        // Direction 2: unknown -> known.
        $summaries2 = [
            $this->callPrivate('scenarioSummary', [$unknown]),
            $this->callPrivate('scenarioSummary', [$known]),
        ];
        $deltas2 = $this->callPrivate('deltas', [$summaries2]);
        $this->assertNull($deltas2[0]['perubahan_pemilih']);
        $this->assertNull($deltas2[0]['perubahan_pemilih_pct']);
    }

    public function test_fallback_report_handles_null_pemilih_without_printing_zero_or_crashing(): void
    {
        $facts = [
            'kawasan' => ['nama' => 'JUASSEH'],
            'roll_semasa' => ['tersedia' => false],
            'saluran_semasa' => ['tersedia' => false],
            'senario' => [
                ['label' => 'PRN 2023', 'tahun' => '2023', 'pemenang' => 'PN', 'majoriti' => 1000, 'peratus_keluar' => null],
            ],
            'perubahan' => [
                ['dari' => 'PRN 2018', 'ke' => 'PRN 2023', 'perubahan_pemilih' => null, 'perubahan_pemilih_pct' => null],
            ],
        ];

        $report = $this->callPrivate('fallbackReport', [$facts]);

        $bullet = $report['pengundi_baru_lama']['bullet_points'][0];
        $this->assertStringNotContainsString('-100%', $bullet);
        $this->assertStringNotContainsString('pengurangan 0 pengundi', $bullet);

        $sorotan = $report['perbandingan_senario'][0]['sorotan'];
        $this->assertStringNotContainsString('—%.', $sorotan);
        $this->assertStringNotContainsString('0%.', $sorotan);
    }
}
