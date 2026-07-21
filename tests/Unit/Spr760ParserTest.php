<?php

namespace Tests\Unit;

use App\Support\Pilihanraya\Spr760Parser;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the exact production failure this parser exists to fix:
 * uploading the Juasseh PRN 2023 scoresheet published PN 98 / BN 73 — the sheet's
 * UNDI POS line — instead of the real PN 4,471 / BN 4,549. Every number asserted
 * here was read off the printed form.
 */
class Spr760ParserTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../Fixtures/Pilihanraya/spr760-juasseh-2023.pdf';

    private static function parsed(): array
    {
        $out = Spr760Parser::parse(self::FIXTURE);
        self::assertNotNull($out, 'the SPR 760 fixture must be recognised');

        return $out;
    }

    public function test_reads_the_seat_header(): void
    {
        $r = self::parsed();

        $this->assertSame('N.15 JUASSEH', $r['kawasan_nama']);
        $this->assertSame('dun', $r['kawasan_type']);
        $this->assertSame(13408, $r['pemilih']);
        $this->assertSame(2, $r['calon_count']);
    }

    /** 39 saluran across 11 Daerah Mengundi, plus the UNDI POS row. */
    public function test_reads_every_saluran_row(): void
    {
        $r = self::parsed();

        $this->assertCount(40, $r['rows']);
        $this->assertSame([], $r['unresolved'], 'no row may be left unread');
    }

    /**
     * The headline assertion: summing the rows must reproduce the printed JUMLAH.
     * Before this parser, Keyin showed 98/73 here.
     */
    public function test_row_totals_match_the_printed_jumlah(): void
    {
        $r = self::parsed();

        $undi = [0, 0];
        $a = $keluar = $ditolak = $tidak = 0;
        foreach ($r['rows'] as $row) {
            foreach ($row['undi'] as $i => $v) {
                $undi[$i] += $v;
            }
            $a += $row['a'];
            $keluar += $row['keluar'];
            $ditolak += $row['ditolak'];
            $tidak += $row['tidak_dimasukkan'];
        }

        $this->assertSame([4471, 4549], $undi);
        $this->assertSame(9122, $a);
        $this->assertSame(9020, $keluar);
        $this->assertSame(87, $ditolak);
        $this->assertSame(15, $tidak);

        $printed = $r['printed_totals'];
        $this->assertSame([4471, 4549], $printed['undi']);
        $this->assertSame(9020, $printed['keluar']);
        $this->assertSame(40, $printed['saluran_count']);
    }

    /**
     * UNDI POS is a DUN-level row: no pusat, which is how Borang14ScenarioMapper
     * represents it. Its C/D (18/14) can only be recovered arithmetically — the
     * PDF fuses them into the digit run "1711814".
     */
    public function test_undi_pos_row_is_dun_level_and_correctly_split(): void
    {
        $r = self::parsed();
        $pos = collect($r['rows'])->firstWhere('saluran', 'UNDI POS');

        $this->assertNotNull($pos);
        $this->assertSame('', $pos['pusat']);
        $this->assertSame([98, 73], $pos['undi']);
        $this->assertSame(171, $pos['keluar']);
        $this->assertSame(18, $pos['ditolak']);
        $this->assertSame(14, $pos['tidak_dimasukkan']);
        $this->assertSame(203, $pos['a']);
    }

    /** Each saluran must land in its own Daerah Mengundi — putVote() keys on it. */
    public function test_rows_are_assigned_to_the_right_daerah_mengundi(): void
    {
        $r = self::parsed();

        $perDm = [];
        foreach ($r['rows'] as $row) {
            if ($row['kod_dm'] !== null) {
                $perDm[$row['kod_dm']][] = $row['saluran'];
            }
        }

        $this->assertSame([
            '129/15/01' => 3, '129/15/02' => 2, '129/15/03' => 1, '129/15/04' => 3,
            '129/15/05' => 2, '129/15/06' => 3, '129/15/07' => 6, '129/15/08' => 2,
            '129/15/09' => 6, '129/15/10' => 6, '129/15/11' => 5,
        ], array_map('count', $perDm));

        $this->assertSame(['1', '2', '3'], $perDm['129/15/01']);
        $this->assertSame(
            'SEKOLAH KEBANGSAAN TENGKEK',
            collect($r['rows'])->firstWhere('kod_dm', '129/15/01')['pusat'],
        );
    }

    /** Every row must satisfy the form's own arithmetic. */
    public function test_every_row_is_internally_consistent(): void
    {
        foreach (self::parsed()['rows'] as $row) {
            $this->assertSame(
                array_sum($row['undi']),
                $row['keluar'],
                "keluar != sum(undi) for saluran {$row['saluran']}",
            );
            $this->assertSame(
                $row['keluar'] + $row['ditolak'] + $row['tidak_dimasukkan'],
                $row['a'],
                "A != keluar + C + D for saluran {$row['saluran']}",
            );
        }
    }

    /** A non-SPR-760 file must be refused so the AI fallback can take it. */
    public function test_returns_null_for_an_unrecognised_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'notpdf');
        file_put_contents($tmp, 'bukan scoresheet');

        $this->assertNull(Spr760Parser::parse($tmp));

        unlink($tmp);
    }
}
