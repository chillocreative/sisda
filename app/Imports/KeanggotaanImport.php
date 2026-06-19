<?php

namespace App\Imports;

use App\Models\Keanggotaan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Imports a membership spreadsheet into the keanggotaan table.
 *
 * Detection is CONTENT-based, not header-based: each row is scanned for a
 * cell that looks like a Malaysian IC (12 digits, valid month/day, after
 * stripping dashes/spaces). This survives title rows, arbitrary/missing
 * header names, dashed ICs and extra columns — the things that make real
 * membership exports fail a fixed-column parser. The name is taken as the
 * most alphabetic cell in the same row.
 *
 * The SISDA match (kawasan / DUN / age / voter_color) is computed
 * afterwards in one set-based pass by MemberMatchService::syncTable().
 */
class KeanggotaanImport implements ToCollection, WithChunkReading
{
    public function __construct(protected int $batchId) {}

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        $records = [];

        foreach ($rows as $row) {
            $cells = array_values(is_array($row) ? $row : $row->toArray());

            $ic = null;
            $icIndex = null;
            foreach ($cells as $i => $cell) {
                $candidate = self::normaliseIc((string) $cell);
                if ($candidate !== null) {
                    $ic = $candidate;
                    $icIndex = $i;
                    break;
                }
            }
            if ($ic === null) {
                continue; // title rows, header rows, blank rows — no IC, skip
            }

            $nama = self::pickName($cells, $icIndex);
            $tel = self::pickPhone($cells, $icIndex);

            $records[] = [
                'batch_id' => $this->batchId,
                'no_ic' => $ic,
                'nama' => $nama ?: '-',
                'no_tel' => $tel,
                'status_kawasan' => 'luar_kawasan',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($records) >= 500) {
                Keanggotaan::insert($records);
                $records = [];
            }
        }

        if (! empty($records)) {
            Keanggotaan::insert($records);
        }
    }

    /** A 12-digit Malaysian IC (after stripping non-digits) with a plausible birth date, else null. */
    public static function normaliseIc(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 12) {
            return null;
        }
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
            return null; // a phone/other number, not an IC
        }

        return $digits;
    }

    /** The cell with the most alphabetic characters (the name), excluding the IC cell. */
    private static function pickName(array $cells, ?int $icIndex): string
    {
        $best = '';
        $bestScore = 0;
        foreach ($cells as $i => $cell) {
            if ($i === $icIndex) {
                continue;
            }
            $text = trim((string) $cell);
            $letters = preg_match_all('/\p{L}/u', $text);
            if ($letters >= 3 && $letters > $bestScore) {
                $bestScore = $letters;
                $best = $text;
            }
        }

        return strtoupper(preg_replace('/\s+/', ' ', $best));
    }

    /** A phone-like cell: 9–11 digits starting with 0, distinct from the IC. */
    private static function pickPhone(array $cells, ?int $icIndex): ?string
    {
        foreach ($cells as $i => $cell) {
            if ($i === $icIndex) {
                continue;
            }
            $digits = preg_replace('/\D/', '', (string) $cell);
            if (preg_match('/^0\d{8,10}$/', $digits)) {
                return $digits;
            }
        }

        return null;
    }
}
