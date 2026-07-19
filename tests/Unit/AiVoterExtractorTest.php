<?php

namespace Tests\Unit;

use App\Services\Upload\AiVoterExtractor;
use Tests\TestCase;

/**
 * Locks in the DETERMINISTIC half of the AI voter-upload fallback — the part
 * that must be correct regardless of what Claude returns. The AI only decides a
 * column mapping (or transcribes records); these methods move/validate the data.
 *
 * No DB or Claude call is exercised here: applyMapping / heuristicMapping /
 * normalizeRecords / sanitizeMapping are pure and only lean on the shared
 * VoterDatabaseImport::normaliseIc guard.
 */
class AiVoterExtractorTest extends TestCase
{
    private function extractor(): AiVoterExtractor
    {
        return app(AiVoterExtractor::class);
    }

    public function test_apply_mapping_reads_rows_below_header_and_maps_columns(): void
    {
        $rows = [
            ['LAPORAN DPT JUASSEH', '', '', ''],          // title/junk row
            ['Kad Pengenalan', 'Nama Pengundi', 'DUN', 'Lokaliti'], // header row (idx 1)
            ['850101-01-5523', 'ahmad bin ali', 'juasseh', 'kampung a'],
            ['920304-08-1122', 'siti nur', 'juasseh', 'kampung b'],
        ];
        $mapping = ['header_row' => 1, 'columns' => [
            'no_ic' => 0, 'nama' => 1, 'kadun' => 2, 'lokaliti' => 3,
        ]];

        [$built, $skipped] = $this->extractor()->applyMapping($rows, $mapping);

        $this->assertCount(2, $built);
        $this->assertSame(0, $skipped);
        $this->assertSame('850101015523', $built[0]['no_ic']);
        $this->assertSame('AHMAD BIN ALI', $built[0]['nama']); // uppercased
        $this->assertSame('JUASSEH', $built[0]['kadun']);
        $this->assertSame('KAMPUNG A', $built[0]['lokaliti']);
        $this->assertNull($built[0]['negeri']); // unmapped field stays null, not 0/''
    }

    public function test_apply_mapping_drops_rows_without_a_valid_ic(): void
    {
        $rows = [
            ['IC', 'Nama'],
            ['850101-01-5523', 'VALID PERSON'],
            ['not-an-ic', 'JUNK ROW'],       // no valid IC anywhere → dropped
            ['', ''],                         // blank → dropped
            ['920304081122', 'DIGITS ONLY'],  // 12 digits, kept
        ];
        $mapping = ['header_row' => 0, 'columns' => ['no_ic' => 0, 'nama' => 1]];

        [$built, $skipped] = $this->extractor()->applyMapping($rows, $mapping);

        $this->assertCount(2, $built);
        $this->assertSame(2, $skipped);
        $this->assertEqualsCanonicalizing(
            ['850101015523', '920304081122'],
            array_column($built, 'no_ic')
        );
    }

    public function test_apply_mapping_finds_ic_by_content_when_column_is_wrong(): void
    {
        // no_ic mapped to the wrong column; a MyKad-shaped cell still rescues it.
        $rows = [
            ['Nama', 'Butiran'],
            ['AHMAD', '850101-01-5523'],
        ];
        $mapping = ['header_row' => 0, 'columns' => ['no_ic' => 0, 'nama' => 0]];

        [$built] = $this->extractor()->applyMapping($rows, $mapping);

        $this->assertCount(1, $built);
        $this->assertSame('850101015523', $built[0]['no_ic']);
    }

    public function test_heuristic_mapping_matches_header_aliases(): void
    {
        $rows = [['No. KP', 'NAMA PENUH', 'Kadun', 'Daerah Mengundi', 'Kod Lokaliti']];

        $mapping = $this->extractor()->heuristicMapping($rows);

        $this->assertSame(0, $mapping['header_row']);
        $this->assertSame(0, $mapping['columns']['no_ic']);
        $this->assertSame(1, $mapping['columns']['nama']);
        $this->assertSame(2, $mapping['columns']['kadun']);
        $this->assertSame(3, $mapping['columns']['daerah_mengundi']);
        $this->assertSame(4, $mapping['columns']['kod_lokaliti']);
        $this->assertNull($mapping['columns']['negeri']); // absent → null
    }

    public function test_normalize_records_validates_ic_dedupes_and_uppercases(): void
    {
        $records = [
            ['no_ic' => '850101-01-5523', 'nama' => 'ahmad bin ali', 'negeri' => 'johor'],
            ['no_ic' => '850101015523', 'nama' => 'duplicate'],   // dup IC → skipped
            ['no_ic' => 'garbage', 'nama' => 'no ic'],            // invalid IC → skipped
            ['no_ic' => '920304-08-1122', 'nama' => 'siti', 'jantina' => 'P'],
            'not-an-array',                                        // malformed → skipped
        ];

        [$built, $skipped] = $this->extractor()->normalizeRecords($records);

        $this->assertCount(2, $built);
        $this->assertSame(3, $skipped);
        $this->assertSame('AHMAD BIN ALI', $built[0]['nama']);
        $this->assertSame('JOHOR', $built[0]['negeri']);
        $this->assertSame('PEREMPUAN', $built[1]['jantina']); // P → PEREMPUAN
        $this->assertNull($built[0]['jantina']);              // absent stays null
    }

    public function test_sanitize_mapping_coerces_stray_ai_values(): void
    {
        $mapping = $this->extractor()->sanitizeMapping([
            'header_row' => '2',                       // numeric string → int
            'columns' => ['no_ic' => 3, 'nama' => 'x', 'kadun' => null],
        ]);

        $this->assertSame(2, $mapping['header_row']);
        $this->assertSame(3, $mapping['columns']['no_ic']);
        $this->assertNull($mapping['columns']['nama']);   // non-numeric → null
        $this->assertNull($mapping['columns']['kadun']);
        $this->assertArrayHasKey('negeri', $mapping['columns']); // fully keyed
    }
}
