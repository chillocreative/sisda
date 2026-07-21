<?php

namespace App\Imports;

use App\Models\PangkalanDataPengundi;
use App\Support\MalaysianIc;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Smart voter-roll importer — reads the file even when its columns are
 * uncertain. It finds the HEADER row by matching known field aliases (scanning
 * past any title rows), then maps each field to its real column. When no header
 * is recognisable it falls back to content detection: the IC is the cell that
 * looks like a 12-digit MyKad, the name the most alphabetic cell.
 *
 * Streams in chunks (WithChunkReading) so large rolls (80k+ rows) stay within
 * memory. The header map is detected once (first chunk) and reused; if the
 * reader ever hands a chunk without a header, per-row content detection still
 * pulls the IC so no rows are silently lost.
 */
class VoterDatabaseImport implements ToCollection, WithChunkReading
{
    /** Header aliases, compared after lowercasing + stripping non-alphanumerics. */
    private const ALIASES = [
        'ic' => ['noic', 'ic', 'nokp', 'kp', 'kadpengenalan', 'nokadpengenalan', 'nokadpengenalanbaru', 'mykad', 'icnumber', 'nombokadpengenalan'],
        'nama' => ['nama', 'namapenuh', 'namapengundi', 'namaahli', 'name', 'fullname'],
        'negeri' => ['namanegeri', 'negeri', 'state'],
        'parlimen' => ['namaparlimen', 'parlimen', 'parliament', 'bahagianpilihanraya'],
        'kadun' => ['namadun', 'namakadun', 'kadun', 'dun', 'stateassembly'],
        'dm' => ['namadm', 'daerahmengundi', 'dm', 'pollingdistrict'],
        'lokaliti' => ['namalokaliti', 'lokaliti', 'locality'],
        'kodlokaliti' => ['kodlokaliti', 'kodlok'],
        'bangsa' => ['bangsaspr', 'bangsa', 'kaum', 'race'],
        'jantina' => ['kodjantina', 'jantina', 'gender', 'sex'],
        'tahunlahir' => ['tahunlahir', 'tahunkelahiran', 'birthyear', 'yob'],
    ];

    /** Field => column index (null per field ⇒ content detection for it). */
    private ?array $map = null;

    private array $buffer = [];

    /** How many rows this importer actually wrote — lets the job decide whether
     *  to escalate to the AI fallback (0 rows ⇒ the fast path could not read it). */
    private int $inserted = 0;

    public function __construct(private int $uploadBatchId) {}

    public function rowsDetected(): int
    {
        return $this->inserted;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function collection(Collection $rows): void
    {
        $rows = $rows->map(fn ($r) => array_values(is_array($r) ? $r : $r->toArray()))->all();

        // Re-detect the header on every chunk. detectHeader only matches a row
        // that literally NAMES the IC/Nama columns, so it fires at the top of
        // each sheet (and after any title rows) but never on data rows. This
        // makes multi-sheet workbooks work even when each sheet has a DIFFERENT
        // column layout — every sheet gets its own correct mapping — and a
        // metadata cover sheet with no header simply falls through to content
        // detection. Without this, sheet 2's rows are read through sheet 1's
        // map and every column lands in the wrong field.
        $start = 0;
        [$idx, $map] = self::detectHeader($rows);
        if ($idx !== null) {
            $this->map = $map;
            $start = $idx + 1; // skip any title rows + the header row itself
        }
        // Before any header is seen, fall back to content detection (IC + name).
        $this->map ??= array_fill_keys(array_keys(self::ALIASES), null);

        for ($i = $start, $n = count($rows); $i < $n; $i++) {
            $rec = $this->buildRecord($rows[$i]);
            if ($rec !== null) {
                $this->buffer[] = $rec;
                if (count($this->buffer) >= 500) {
                    $this->flush();
                }
            }
        }

        $this->flush();
    }

    private function flush(): void
    {
        if ($this->buffer !== []) {
            PangkalanDataPengundi::insert($this->buffer);
            $this->inserted += count($this->buffer);
            $this->buffer = [];
        }
    }

    /**
     * Find the header row + each field's column index by matching aliases.
     *
     * @return array{0:?int, 1:array<string,?int>}
     */
    public static function detectHeader(array $rows): array
    {
        $empty = array_fill_keys(array_keys(self::ALIASES), null);
        $limit = min(count($rows), 30);
        for ($i = 0; $i < $limit; $i++) {
            $map = $empty;
            foreach (array_values((array) $rows[$i]) as $idx => $cell) {
                $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $cell));
                if ($key === '') {
                    continue;
                }
                foreach (self::ALIASES as $field => $keys) {
                    if ($map[$field] === null && in_array($key, $keys, true)) {
                        $map[$field] = $idx;
                        break;
                    }
                }
            }
            // A real header names at least the IC or the Nama column.
            if ($map['ic'] !== null || $map['nama'] !== null) {
                return [$i, $map];
            }
        }

        return [null, $empty];
    }

    /** Build one voter record, or null if the row has no usable IC. */
    private function buildRecord(array $cells): ?array
    {
        $cells = array_values($cells);

        // IC: mapped column first (lenient), else detect a MyKad-shaped cell (strict).
        $ic = self::normaliseIc((string) $this->cell($cells, $this->map['ic'] ?? null));
        if ($ic === null) {
            $ic = $this->detectIc($cells);
        }
        if ($ic === null) {
            return null;
        }

        $nama = strtoupper(trim((string) $this->cell($cells, $this->map['nama'] ?? null)));
        if ($nama === '') {
            $nama = $this->pickName($cells);
        }

        return [
            'upload_batch_id' => $this->uploadBatchId,
            'no_ic' => $ic,
            'nama' => $nama,
            'lokaliti' => $this->upper($this->cell($cells, $this->map['lokaliti'] ?? null)),
            'kod_lokaliti' => $this->str($this->cell($cells, $this->map['kodlokaliti'] ?? null)),
            'daerah_mengundi' => $this->upper($this->cell($cells, $this->map['dm'] ?? null)),
            'kadun' => $this->upper($this->cell($cells, $this->map['kadun'] ?? null)),
            'parlimen' => $this->upper($this->cell($cells, $this->map['parlimen'] ?? null)),
            'negeri' => $this->upper($this->cell($cells, $this->map['negeri'] ?? null)),
            'bangsa' => $this->upper($this->cell($cells, $this->map['bangsa'] ?? null)),
            'jantina' => $this->normaliseJantina($this->cell($cells, $this->map['jantina'] ?? null)),
            // Voter exports often carry no birth-year column at all; the IC's
            // leading YYMMDD is then the only source, so fall back to it.
            'tahun_lahir' => $this->str($this->cell($cells, $this->map['tahunlahir'] ?? null))
                ?? ($ic !== null ? (string) (MalaysianIc::voterBirthYear($ic) ?? '') ?: null : null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function cell(array $cells, ?int $idx): ?string
    {
        if ($idx === null || ! array_key_exists($idx, $cells)) {
            return null;
        }
        $v = trim((string) $cells[$idx]);

        return $v === '' ? null : $v;
    }

    private function upper(?string $v): ?string
    {
        return $v === null ? null : (strtoupper($v) ?: null);
    }

    private function str(?string $v): ?string
    {
        return $v === null || $v === '' ? null : $v;
    }

    /**
     * A 12-digit MyKad after stripping non-digits. Restores Excel-eaten leading
     * zeros (born 2000+). When $strict, the birth date must validate — used for
     * content detection so a phone/serial isn't mistaken for an IC.
     */
    public static function normaliseIc(string $value, bool $strict = false): ?string
    {
        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) >= 9 && strlen($digits) < 12) {
            $digits = str_pad($digits, 12, '0', STR_PAD_LEFT);
        }
        if (strlen($digits) !== 12) {
            return null;
        }
        if ($strict) {
            $mm = (int) substr($digits, 2, 2);
            $dd = (int) substr($digits, 4, 2);
            if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
                return null;
            }
        }

        return $digits;
    }

    private function detectIc(array $cells): ?string
    {
        foreach ($cells as $cell) {
            $ic = self::normaliseIc((string) $cell, true);
            if ($ic !== null) {
                return $ic;
            }
        }

        return null;
    }

    /** Content fallback: the cell with the most letters that isn't an IC. */
    private function pickName(array $cells): string
    {
        $best = '';
        $bestScore = 0;
        foreach ($cells as $cell) {
            $text = trim((string) $cell);
            if (self::normaliseIc($text, true) !== null) {
                continue;
            }
            $letters = preg_match_all('/\p{L}/u', $text);
            if ($letters >= 3 && $letters > $bestScore) {
                $bestScore = $letters;
                $best = $text;
            }
        }

        return strtoupper(preg_replace('/\s+/', ' ', $best));
    }

    private function normaliseJantina(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $k = strtoupper(trim($v));

        return match ($k) {
            'L', 'LELAKI', 'MALE', 'M' => 'LELAKI',
            'P', 'PEREMPUAN', 'FEMALE', 'F', 'W' => 'PEREMPUAN',
            default => $k ?: null,
        };
    }
}
