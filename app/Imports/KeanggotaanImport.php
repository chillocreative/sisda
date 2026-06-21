<?php

namespace App\Imports;

/**
 * Parses membership rows into [no_ic, nama, no_tel] records.
 *
 * Strategy: find the HEADER row (scanning past any title rows) and map the
 * Nama / IC / Tel columns by their header names, so each field is read
 * from its real column. ICs are normalised (dashes/spaces stripped, valid
 * month/day checked). If a file has no recognisable header, it falls back
 * to content detection (IC by pattern, name by the most alphabetic cell).
 *
 * The SISDA match (kawasan / DUN / age / voter_color) is computed
 * afterwards by MemberMatchService::syncTable().
 */
class KeanggotaanImport
{
    /** Header aliases, compared after lowercasing + stripping non-alphanumerics. */
    private const NAMA_KEYS = ['nama', 'namaahli', 'namapenuh', 'namaanggota', 'name', 'fullname'];

    private const IC_KEYS = ['noic', 'ic', 'nokp', 'kp', 'kadpengenalan', 'nokadpengenalan', 'nombokadpengenalan', 'nokadpengenalanbaru', 'mykad', 'icnumber'];

    private const TEL_KEYS = ['notel', 'notelefon', 'telefon', 'tel', 'phone', 'nohp', 'hp', 'nombortelefon', 'telbimbit'];

    /**
     * @param  array  $rows  array of rows, each an array of cell values
     * @param  array{kept?:int, skipped_no_ic?:int}  $tally  filled in-place with counts
     * @return array<int, array{no_ic:string, nama:string, no_tel:?string}>
     */
    public static function extract(array $rows, array &$tally = []): array
    {
        $tally += ['kept' => 0, 'skipped_no_ic' => 0];

        [$headerIdx, $map] = self::detectHeader($rows);
        $start = $headerIdx === null ? 0 : $headerIdx + 1;

        $out = [];
        $count = count($rows);
        for ($i = $start; $i < $count; $i++) {
            $cells = array_values($rows[$i]);

            // IC: header column first, else detect by content.
            $ic = ($map['ic'] !== null && isset($cells[$map['ic']]))
                ? self::normaliseIc((string) $cells[$map['ic']])
                : null;
            if ($ic === null) {
                $ic = self::detectIcInRow($cells);
            }
            if ($ic === null) {
                // Only count rows that carry some content (ignore blank spacer rows).
                if (trim(implode('', array_map('strval', $cells))) !== '') {
                    $tally['skipped_no_ic']++;
                }

                continue;
            }

            // Nama: header column first, else the most alphabetic cell.
            $nama = ($map['nama'] !== null && isset($cells[$map['nama']]))
                ? self::cleanName((string) $cells[$map['nama']])
                : '';
            if ($nama === '') {
                $nama = self::pickName($cells);
            }

            // Phone: header column first, else a 0-prefixed number.
            $tel = ($map['tel'] !== null && isset($cells[$map['tel']]))
                ? (preg_replace('/\D/', '', (string) $cells[$map['tel']]) ?: null)
                : null;
            if ($tel === null) {
                $tel = self::pickPhone($cells);
            }

            $out[] = ['no_ic' => $ic, 'nama' => $nama ?: '-', 'no_tel' => $tel];
            $tally['kept']++;
        }

        return $out;
    }

    /**
     * Find the header row and the column indices for nama / ic / tel.
     *
     * @return array{0:?int, 1:array{nama:?int, ic:?int, tel:?int}}
     */
    private static function detectHeader(array $rows): array
    {
        $limit = min(count($rows), 30);
        for ($i = 0; $i < $limit; $i++) {
            $map = ['nama' => null, 'ic' => null, 'tel' => null];
            foreach (array_values($rows[$i]) as $idx => $cell) {
                $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $cell));
                if ($key === '') {
                    continue;
                }
                if ($map['nama'] === null && in_array($key, self::NAMA_KEYS, true)) {
                    $map['nama'] = $idx;
                } elseif ($map['ic'] === null && in_array($key, self::IC_KEYS, true)) {
                    $map['ic'] = $idx;
                } elseif ($map['tel'] === null && in_array($key, self::TEL_KEYS, true)) {
                    $map['tel'] = $idx;
                }
            }
            // A real header row names at least the Nama or IC column.
            if ($map['nama'] !== null || $map['ic'] !== null) {
                return [$i, $map];
            }
        }

        return [null, ['nama' => null, 'ic' => null, 'tel' => null]];
    }

    /** A 12-digit Malaysian IC (after stripping non-digits) with a plausible birth date, else null. */
    public static function normaliseIc(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value);

        // Excel stores numeric IC cells as numbers and eats leading zeros, so
        // members born in 2000+ (IC starts with 0) arrive with 9-11 digits.
        // Restore the zeros, but only trust the result if the birth date validates.
        if (strlen($digits) >= 9 && strlen($digits) < 12) {
            $digits = str_pad($digits, 12, '0', STR_PAD_LEFT);
        }

        if (strlen($digits) !== 12) {
            return null;
        }
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
            return null;
        }

        return $digits;
    }

    private static function detectIcInRow(array $cells): ?string
    {
        foreach ($cells as $cell) {
            $ic = self::normaliseIc((string) $cell);
            if ($ic !== null) {
                return $ic;
            }
        }

        return null;
    }

    private static function cleanName(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value)));
    }

    /** Fallback only: the cell with the most letters. */
    private static function pickName(array $cells): string
    {
        $best = '';
        $bestScore = 0;
        foreach ($cells as $cell) {
            $text = trim((string) $cell);
            if (self::normaliseIc($text) !== null) {
                continue;
            }
            $letters = preg_match_all('/\p{L}/u', $text);
            if ($letters >= 3 && $letters > $bestScore) {
                $bestScore = $letters;
                $best = $text;
            }
        }

        return self::cleanName($best);
    }

    private static function pickPhone(array $cells): ?string
    {
        foreach ($cells as $cell) {
            $digits = preg_replace('/\D/', '', (string) $cell);
            if (preg_match('/^0\d{8,10}$/', $digits)) {
                return $digits;
            }
        }

        return null;
    }
}
