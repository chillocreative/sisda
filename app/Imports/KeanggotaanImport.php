<?php

namespace App\Imports;

/**
 * Parses membership rows straight from the uploaded file — no SISDA cross-check.
 *
 * Strategy: find the HEADER row (scanning past any title rows) and map each
 * field column by its header name, so values are read from their real column.
 * Captured per row: no_anggota, nama, no_ic, no_tel, jantina, bangsa, cabang,
 * negeri, alamat. ICs are normalised (dashes/spaces stripped, leading zeros restored,
 * valid month/day checked). If a file has no recognisable header, it falls back
 * to content detection (IC by pattern, name by the most alphabetic cell).
 *
 * Members may be spread across sheets with different layouts; the caller merges
 * rows by IC to assemble the most complete record.
 */
class KeanggotaanImport
{
    /** Header aliases, compared after lowercasing + stripping non-alphanumerics. */
    private const NAMA_KEYS = ['nama', 'namaahli', 'namapenuh', 'namaanggota', 'name', 'fullname'];

    private const IC_KEYS = ['noic', 'ic', 'nokp', 'kp', 'kadpengenalan', 'nokadpengenalan', 'nombokadpengenalan', 'nokadpengenalanbaru', 'mykad', 'icnumber'];

    private const TEL_KEYS = ['notel', 'notelefon', 'telefon', 'tel', 'phone', 'nohp', 'hp', 'nombortelefon', 'telbimbit', 'mobilenumber', 'mobile', 'nombormobile'];

    private const ANGGOTA_KEYS = ['noanggota', 'noahli', 'nokeanggotaan', 'nokeahlian', 'membershipno', 'memberno', 'idanggota'];

    private const JANTINA_KEYS = ['jantina', 'gender', 'sex'];

    private const BANGSA_KEYS = ['bangsa', 'kaum', 'race'];

    private const CABANG_KEYS = ['cabang', 'bahagian', 'parlimen', 'branch'];

    private const NEGERI_KEYS = ['negeri', 'state'];

    private const ALAMAT_KEYS = ['alamat', 'address', 'alamatrumah', 'alamatpenuh', 'alamattetap', 'alamatsurat', 'addressline'];

    /** Empty per-field map. */
    private const EMPTY_MAP = ['nama' => null, 'ic' => null, 'tel' => null, 'anggota' => null, 'jantina' => null, 'bangsa' => null, 'cabang' => null, 'negeri' => null, 'alamat' => null];

    /**
     * @param  array  $rows  array of rows, each an array of cell values
     * @param  array{kept?:int, skipped_no_ic?:int}  $tally  filled in-place with counts
     * @return array<int, array{no_ic:string, nama:string, no_tel:?string, no_anggota:?string, jantina:?string, bangsa:?string, cabang:?string, negeri:?string}>
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

            $out[] = [
                'no_ic' => $ic,
                'nama' => $nama ?: '-',
                'no_tel' => $tel,
                'no_anggota' => self::cell($cells, $map['anggota']),
                'jantina' => self::normaliseJantina(self::cell($cells, $map['jantina'])),
                'bangsa' => self::upperOrNull(self::cell($cells, $map['bangsa'])),
                'cabang' => self::upperOrNull(self::cell($cells, $map['cabang'])),
                'negeri' => self::upperOrNull(self::cell($cells, $map['negeri'])),
                // Address is kept in its original casing (just trimmed + collapsed).
                'alamat' => self::cleanAlamat(self::cell($cells, $map['alamat'])),
            ];
            $tally['kept']++;
        }

        return $out;
    }

    /**
     * Find the header row and the column index for each known field.
     *
     * @return array{0:?int, 1:array<string,?int>}
     */
    private static function detectHeader(array $rows): array
    {
        $aliases = [
            'nama' => self::NAMA_KEYS, 'ic' => self::IC_KEYS, 'tel' => self::TEL_KEYS,
            'anggota' => self::ANGGOTA_KEYS, 'jantina' => self::JANTINA_KEYS,
            'bangsa' => self::BANGSA_KEYS, 'cabang' => self::CABANG_KEYS, 'negeri' => self::NEGERI_KEYS,
            'alamat' => self::ALAMAT_KEYS,
        ];

        $limit = min(count($rows), 30);
        for ($i = 0; $i < $limit; $i++) {
            $map = self::EMPTY_MAP;
            foreach (array_values($rows[$i]) as $idx => $cell) {
                $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $cell));
                if ($key === '') {
                    continue;
                }
                foreach ($aliases as $field => $keys) {
                    if ($map[$field] === null && in_array($key, $keys, true)) {
                        $map[$field] = $idx;
                        break;
                    }
                }
            }
            // A real header row names at least the Nama or IC column.
            if ($map['nama'] !== null || $map['ic'] !== null) {
                return [$i, $map];
            }
        }

        return [null, self::EMPTY_MAP];
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

    /** Value at a mapped column index, trimmed, or null. */
    private static function cell(array $cells, ?int $idx): ?string
    {
        if ($idx === null || ! isset($cells[$idx])) {
            return null;
        }
        $v = trim((string) $cells[$idx]);

        return $v === '' ? null : $v;
    }

    private static function upperOrNull(?string $v): ?string
    {
        return $v === null ? null : strtoupper($v);
    }

    /** Trim + collapse whitespace, preserving the address's original casing. */
    private static function cleanAlamat(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = preg_replace('/\s+/', ' ', trim($v));

        return $v === '' ? null : $v;
    }

    /** Normalise a gender cell to LELAKI / PEREMPUAN, else null. */
    private static function normaliseJantina(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $k = strtoupper(trim($v));
        if (in_array($k, ['L', 'LELAKI', 'MALE', 'M'], true)) {
            return 'LELAKI';
        }
        if (in_array($k, ['P', 'PEREMPUAN', 'FEMALE', 'F', 'W', 'WANITA'], true)) {
            return 'PEREMPUAN';
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
