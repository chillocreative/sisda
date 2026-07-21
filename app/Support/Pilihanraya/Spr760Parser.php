<?php

namespace App\Support\Pilihanraya;

use Smalot\PdfParser\Parser as PdfParser;

/**
 * Deterministic reader for the SPR "HELAIAN MATA (SCORE SHEET)" — Borang SPR 760.
 *
 * The form has a fixed column layout, so a text-based PDF of it can be read
 * exactly: no AI, no cost, no chance of a hallucinated vote. Claude stays as the
 * fallback in ScoresheetExtractor for scanned or photographed sheets, which this
 * parser refuses (returns null) rather than guessing at.
 *
 * Two things make this harder than "read the text":
 *
 * 1. Reading order is unusable — the extracted text runs column-major within each
 *    Daerah Mengundi block, so rows are rebuilt from each cell's own (x, y) via
 *    getDataTm(), and the column centres are discovered by clustering the x
 *    positions of the sheet's own numeric cells (never hardcoded: candidate count
 *    and page geometry differ between seats).
 *
 * 2. Some rows arrive as ONE fused text item — typically single-saluran rows and
 *    the summary rows: "203 98 73 1711814" (UNDI POS) or
 *    "JUMLAH 409,122 4,471 4,549 9,0208715". Splitting that digit run by eye is
 *    ambiguous (171|18|14 vs 171|1|814 vs …), so it is resolved against the form's
 *    own arithmetic instead:
 *
 *        keluar = Σ candidate votes        A = keluar + C + D
 *
 *    Only a split satisfying both is accepted, and only when it is the ONLY such
 *    split. Anything else is reported unresolved — never guessed, never zeroed.
 */
class Spr760Parser
{
    /** Cells within this many points of a cluster centre belong to that column. */
    private const X_TOLERANCE = 14.0;

    /** Text baselines within this many points are the same visual row. */
    private const Y_TOLERANCE = 3.0;

    /** Largest plausible digit count for one printed cell. */
    private const MAX_CELL_DIGITS = 6;

    /** Lines containing any of these are page furniture, not data. */
    private const FURNITURE = '/HELAIAN\s+MATA|MUKA\s+SURAT|SPR\s*760|TARIKH\s+CETAK|JABATAN\s+PILIHAN|BAHAGIAN\s+PILIHAN|PILIHAN\s+RAYA\s+UMUM|KERTAS\s+UNDI|DAERAH\s+MENGUNDI|TEMPAT\s+MENGUNDI|OLEH\s+PEMILIH|DIMASUKKAN|DIKELUARKAN|BILANGAN|UNDI\s+BIASA|keAdilan/i';

    /**
     * @return array{
     *     kawasan_nama:string, kawasan_type:string, pemilih:?int, calon_count:int,
     *     rows:array<int,array>, printed_totals:?array, unresolved:array<int,string>
     * }|null  null when the file is not a readable SPR 760
     */
    public static function parse(string $path): ?array
    {
        try {
            $pages = (new PdfParser)->parseFile($path)->getPages();
        } catch (\Throwable) {
            return null;
        }
        if ($pages === []) {
            return null;
        }

        $header = self::header($pages[0]);
        if ($header === null) {
            return null;                  // not an SPR 760 — let the AI fallback take it
        }

        // Lines are kept PER PAGE: y coordinates restart on every page, so a row
        // on page 1 would otherwise be matched to a block label on page 3.
        $perPage = [];
        $lines = [];
        foreach ($pages as $i => $page) {
            $perPage[$i] = self::lines($page);
            foreach ($perPage[$i] as $cells) {
                $lines[] = $cells;
            }
        }

        // Pass 1 — learn how many candidate columns this sheet has, from the rows
        // that read unambiguously. The count is a property of the seat, not of a
        // row, so the modal value wins and odd rows cannot skew it.
        $votes = [];
        foreach ($lines as $cells) {
            $joined = self::joined($cells);
            if (self::isFurniture($joined)) {
                continue;
            }
            $hit = self::readRight(self::numbers($cells), null);
            if ($hit !== null) {
                $votes[$hit['calon']] = ($votes[$hit['calon']] ?? 0) + 1;
            }
        }
        if ($votes === []) {
            return null;
        }
        arsort($votes);
        $calonCount = (int) array_key_first($votes);

        // Pass 2 — read every line with that fixed shape, page by page.
        $rows = [];
        $printedTotals = null;
        $unresolved = [];

        $blocks = [];
        foreach ($perPage as $pageLines) {
            foreach (self::blocks($pageLines) as $b) {
                // A block continued across a page break re-prints its kod on the
                // next page; that is the same block, not a new one.
                if (($blocks !== [] ? end($blocks)['kod'] : null) === $b['kod']) {
                    continue;
                }
                $blocks[] = $b;
            }
            foreach ($pageLines as $cells) {
                $joined = self::joined($cells);
                if (self::isFurniture($joined)) {
                    continue;
                }

                $isTotal = (bool) preg_match('/\bJUMLAH\b/i', $joined);
                $isPos = (bool) preg_match('/UNDI\s+(POS|AWAL)/i', $joined);

                $nums = self::numbers($cells);
                $hit = self::readRight($nums, $calonCount);

                if ($hit !== null) {
                    // The cell immediately left of (A) is the saluran number, when present.
                    $lead = array_slice($nums, 0, max(0, count($nums) - ($calonCount + 4)));
                    $saluran = $lead === [] ? null : (int) end($lead);
                    $body = $hit['vals'];
                } else {
                    $solved = self::solveFused($joined, $calonCount);
                    if ($solved === null) {
                        if (self::looksLikeData($joined)) {
                            $unresolved[] = $joined;
                        }

                        continue;
                    }
                    $saluran = $solved[0];
                    $body = array_slice($solved, 1);
                }

                $row = self::shape($saluran, $body, null, $joined, $calonCount, $isPos, $isTotal);
                if ($isTotal) {
                    $printedTotals = $row;
                } else {
                    $row['_saluran_no'] = $saluran;
                    $rows[] = $row;
                    $anchorQueue = $anchorQueue ?? [];
                }
            }
        }

        if ($rows === []) {
            return null;
        }

        $rows = self::assignBlocks($rows, $blocks);

        return $header + [
            'calon_count' => $calonCount,
            'rows' => $rows,
            'printed_totals' => $printedTotals,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * The same read, reshaped into the contract KawasanResolver and
     * Borang14Controller::uploadCommit() consume (identical to what
     * ScoresheetExtractor::extractDetailed() gets from Claude), so the
     * deterministic path is a drop-in replacement for the AI one.
     *
     * Returns null — deliberately, rather than a partial record — when the seat
     * cannot be placed with certainty (no state, no seat code, no Parlimen code)
     * or no candidate votes were read. KawasanResolver CREATES geography from
     * these fields, so a half-read header would seed a phantom seat that
     * silently orphans every row string-matched against it.
     *
     * @return array{negeri:string, kawasan_kod:string, kawasan_nama:string,
     *     parlimen_kod:string, jumlah_pemilih:?int, calon:array, rows:array, jumlah:?array}|null
     */
    public static function detailed(string $path): ?array
    {
        $r = self::parse($path);
        if ($r === null || $r['rows'] === [] || $r['calon_count'] < 1) {
            return null;
        }
        if (! $r['negeri'] || ! $r['kawasan_kod'] || ! $r['kawasan_nama']) {
            return null;
        }

        // Kod DM "129/15/01" encodes Parlimen 129 / DUN 15 / DM 01.
        $parlimenKod = null;
        foreach ($r['rows'] as $row) {
            if ($row['kod_dm'] !== null && preg_match('#^(\d{2,3})/#', $row['kod_dm'], $m)) {
                $parlimenKod = $m[1];
                break;
            }
        }
        if ($parlimenKod === null) {
            return null;
        }

        $shape = fn (array $row) => [
            'dm_kod' => $row['kod_dm'],
            'dm' => $row['pusat'] !== '' ? $row['pusat'] : null,
            'pusat' => $row['pusat'],
            'saluran' => $row['saluran'],
            'a' => $row['a'],
            'undi' => array_values($row['undi']),
            'jumlah_undian' => $row['keluar'],
            'ditolak' => $row['ditolak'],
            'tidak_dimasukkan' => $row['tidak_dimasukkan'],
        ];

        return [
            'negeri' => $r['negeri'],
            'kawasan_kod' => $r['kawasan_kod'],
            'kawasan_nama' => $r['kawasan_nama'],
            'parlimen_kod' => $parlimenKod,
            // Absent on some sheets — stays null, never 0 (a 0 denominator
            // fabricates a 0% turnout downstream).
            'jumlah_pemilih' => $r['pemilih'],
            // The sheet fuses every candidate name into ONE text item with no
            // splittable geometry, so slots are numbered placeholders flagged
            // `yakin: false` (which forces needs_review) for the user to name.
            // A guessed split would misattribute votes — the precise class of
            // error this parser exists to prevent.
            'calon' => array_map(
                fn (int $i) => ['nama' => 'CALON '.$i, 'parti_tekaan' => null, 'yakin' => false],
                range(1, $r['calon_count']),
            ),
            'rows' => array_map($shape, $r['rows']),
            'jumlah' => $r['printed_totals'] ? $shape($r['printed_totals']) : null,
            'saluran_count' => $r['printed_totals']['saluran_count'] ?? null,
        ];
    }

    /** Seat identity + registered voters. Null when the SPR 760 banner is absent. */
    private static function header($page): ?array
    {
        $text = $page->getText();
        if (! preg_match('/HELAIAN\s+MATA|SPR\s*760/i', $text)) {
            return null;
        }

        $nama = '';
        $kod = null;
        $type = 'dun';
        if (preg_match('/BAHAGIAN\s+PILIHAN\s+RAYA\s+(NEGERI|PERSEKUTUAN)\s*:\s*([^\n]+)/i', $text, $m)) {
            $type = mb_strtoupper(trim($m[1])) === 'PERSEKUTUAN' ? 'parlimen' : 'dun';
            $nama = trim($m[2]);

            // "N.15 JUASSEH" — KawasanResolver keys the seat LEVEL off this
            // prefix ("N." vs "P."), so kod and nama are split rather than
            // handed over fused.
            if (preg_match('/^([NP]\s*\.\s*\d+)\s+(.+)$/i', $nama, $s)) {
                $kod = mb_strtoupper(preg_replace('/\s+/', '', $s[1]));
                $nama = trim($s[2]);
            }
        }

        // The state is printed only in the footer banner ("PILIHAN RAYA UMUM
        // DEWAN NEGERI NEGERI SEMBILAN KE -15"). A parliamentary sheet says
        // DEWAN RAKYAT and names no state at all — that stays null, and
        // detailed() then declines the sheet rather than guessing a state,
        // because KawasanResolver would otherwise place it in the wrong one.
        $negeri = null;
        if (preg_match('/DEWAN\s+NEGERI\s+(.+?)\s+KE\s*-\s*\d+/iu', $text, $m)) {
            $negeri = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $m[1])));
        }

        // Genuinely absent on some sheets — stays null, never 0. A 0 denominator
        // fabricates a "-100%" registered-voter swing downstream.
        $pemilih = null;
        if (preg_match('/JUMLAH\s+PEMILIH\s*:\s*([\d,\.]+)/i', $text, $m)) {
            $pemilih = (int) preg_replace('/\D/', '', $m[1]);
        }

        return [
            'kawasan_kod' => $kod,
            'kawasan_nama' => $nama,
            'kawasan_type' => $type,
            'negeri' => $negeri,
            'pemilih' => $pemilih,
        ];
    }

    /**
     * Read one line's numbers by anchoring on the RIGHT of the row.
     *
     * The last three cells are always keluar, C, D; the candidate block sits
     * immediately before them and (A) immediately before that. Anything further
     * left is the BIL. / saluran / kod columns, which differ per sheet and must
     * not be counted as votes. Rather than trusting geometry for where the
     * candidates start, every possible start is tested against the form's own
     * identities and only an unambiguous fit is accepted.
     *
     * @param  array<int,int>  $nums  numeric cells in left-to-right order
     * @param  int|null  $calonCount  fixed count once known; null = discover it
     * @return array{calon:int, vals:array<int,int>}|null
     */
    private static function readRight(array $nums, ?int $calonCount = null): ?array
    {
        $len = count($nums);
        if ($len < 5) {
            return null;
        }

        $keluar = $nums[$len - 3];
        $c = $nums[$len - 2];
        $d = $nums[$len - 1];

        $hits = [];
        $range = $calonCount !== null ? [$calonCount] : range(1, $len - 4);
        foreach ($range as $n) {
            $firstCandidate = $len - 3 - $n;
            $aIndex = $firstCandidate - 1;
            if ($aIndex < 0) {
                continue;
            }
            $a = $nums[$aIndex];
            $undi = array_slice($nums, $firstCandidate, $n);
            if (array_sum($undi) === $keluar && $keluar + $c + $d === $a) {
                $hits[] = ['calon' => $n, 'vals' => array_merge([$a], $undi, [$keluar, $c, $d])];
            }
        }

        // Exactly one reading satisfies the arithmetic = certainty.
        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * Rebuild visual rows from positioned text.
     *
     * @return array<int, array<int, array{x:float, text:string}>>
     */
    private static function lines($page): array
    {
        $rows = [];
        foreach ($page->getDataTm() as [$m, $text]) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $y = (float) $m[5];
            $key = null;
            foreach (array_keys($rows) as $existing) {
                if (abs((float) $existing - $y) <= self::Y_TOLERANCE) {
                    $key = $existing;
                    break;
                }
            }
            $key ??= (string) $y;
            $rows[$key][] = ['x' => (float) $m[4], 'y' => $y, 'text' => $text];
        }

        foreach ($rows as &$cells) {
            usort($cells, fn ($a, $b) => $a['x'] <=> $b['x']);
        }
        unset($cells);

        // Keep pages in visual order (PDF y grows upward, so descending = top-down).
        uasort($rows, fn ($a, $b) => $b[0]['y'] <=> $a[0]['y']);

        return array_values($rows);
    }

    /**
     * Recover a fused row's columns using the form's own arithmetic. Accepts the
     * split only when exactly one candidate reading satisfies both identities.
     *
     * @return array<int,int>|null
     */
    private static function solveFused(string $joined, int $calonCount): ?array
    {
        $digits = preg_replace('/\D/', '', $joined);
        if ($digits === '' || strlen($digits) < 6) {
            return null;
        }

        $body = 1 + $calonCount + 3;       // A, candidates, keluar, C, D

        // Try with a leading saluran/count cell first, then without.
        foreach ([$body + 1, $body] as $slots) {
            $found = [];
            self::recurse($digits, 0, $slots, [], $found, $calonCount, $slots > $body);
            if (count($found) === 1) {
                return $slots > $body ? $found[0] : array_merge([null], $found[0]);
            }
            if (count($found) > 1) {
                return null;               // ambiguous — say so rather than pick one
            }
        }

        return null;
    }

    private static function recurse(string $digits, int $pos, int $slotsLeft, array $acc, array &$out, int $calonCount, bool $hasSaluran): void
    {
        if (count($out) > 1) {
            return;                        // already ambiguous; stop searching
        }
        if ($slotsLeft === 0) {
            if ($pos === strlen($digits)) {
                $body = $hasSaluran ? array_slice($acc, 1) : $acc;
                if (self::consistent($body, $calonCount)) {
                    $out[] = $acc;
                }
            }

            return;
        }
        $remaining = strlen($digits) - $pos;
        if ($remaining < $slotsLeft) {
            return;
        }
        $max = min(self::MAX_CELL_DIGITS, $remaining - $slotsLeft + 1);
        for ($take = 1; $take <= $max; $take++) {
            $chunk = substr($digits, $pos, $take);
            if ($take > 1 && $chunk[0] === '0') {
                continue;                  // printed cells carry no leading zeros
            }
            $acc[] = (int) $chunk;
            self::recurse($digits, $pos + $take, $slotsLeft - 1, $acc, $out, $calonCount, $hasSaluran);
            array_pop($acc);
        }
    }

    /**
     * The form's two identities, applied to [A, c1..cN, keluar, C, D].
     * These are what make a fused split provable rather than guessed.
     */
    private static function consistent(array $body, int $calonCount): bool
    {
        if (count($body) !== $calonCount + 4) {
            return false;
        }
        $a = $body[0];
        $undi = array_slice($body, 1, $calonCount);
        [$keluar, $c, $d] = array_slice($body, $calonCount + 1);

        return array_sum($undi) === $keluar && $keluar + $c + $d === $a;
    }

    /**
     * @param  int|null  $saluran  saluran number, or the saluran COUNT on the totals row
     * @param  array<int,int>  $body  [A, c1..cN, keluar, C, D]
     */
    private static function shape(?int $saluran, array $body, ?array $block, string $joined, int $calonCount, bool $isPos, bool $isTotal): array
    {
        // A row with no saluran number and no block of its own is a DUN-level
        // row (UNDI POS / UNDI AWAL) — represented with an empty pusat, exactly
        // how Borang14ScenarioMapper already models them.
        $dunLevel = $isPos || $isTotal || ($saluran === null && $block === null);

        return [
            'pusat' => $dunLevel ? '' : (string) ($block['pusat'] ?? ''),
            'saluran' => $isTotal
                ? 'JUMLAH'
                : ($dunLevel ? ($isPos ? self::posLabel($joined) : 'UNDI POS') : (string) ($saluran ?? '')),
            'kod_dm' => $dunLevel ? null : ($block['kod'] ?? null),
            'a' => $body[0],
            'undi' => array_slice($body, 1, $calonCount),
            'keluar' => $body[$calonCount + 1],
            'ditolak' => $body[$calonCount + 2],
            'tidak_dimasukkan' => $body[$calonCount + 3],
            'saluran_count' => $isTotal ? $saluran : null,
        ];
    }

    /**
     * Tie each saluran row to its Daerah Mengundi.
     *
     * Matching by nearest label fails where a one-saluran block sits beside a
     * six-saluran one — the label is printed beside the middle of its own block,
     * so the boundaries move. The sheet carries a far better signal: the saluran
     * number restarts at 1 in every block. Rows are therefore segmented on that
     * reset and the segments consumed in printed order, which is exactly how the
     * form is laid out.
     *
     * @param  array<int,array>  $rows
     * @param  array<int,array{kod:string, pusat:string}>  $blocks
     * @return array<int,array>
     */
    private static function assignBlocks(array $rows, array $blocks): array
    {
        $segment = -1;
        $previous = null;

        foreach ($rows as $i => $row) {
            if (($row['_saluran_no'] ?? null) === null) {
                unset($rows[$i]['_saluran_no']);

                continue;                     // UNDI POS / AWAL — no block of its own
            }
            $no = (int) $row['_saluran_no'];
            if ($previous === null || $no <= $previous) {
                $segment++;
            }
            $previous = $no;

            $block = $blocks[$segment] ?? null;
            $rows[$i]['pusat'] = (string) ($block['pusat'] ?? '');
            $rows[$i]['kod_dm'] = $block['kod'] ?? null;
            unset($rows[$i]['_saluran_no']);
        }

        return array_values($rows);
    }

    /**
     * Build the Daerah Mengundi blocks: each printed kod ("129/15/01") anchors a
     * block, and the polling-centre name sits in the column between the kod and
     * the first number column. Rows are matched to the nearest anchor by y, since
     * the label is typically printed beside the middle row of its own block —
     * not above it — so "last seen while scanning" would mis-assign the first row.
     *
     * @return array<int, array{y:float, kod:string, pusat:string}>
     */
    private static function blocks(array $lines): array
    {
        $anchors = [];
        $nameCells = [];
        $firstNumberX = INF;
        $minKodX = INF;

        foreach ($lines as $cells) {
            foreach ($cells as $c) {
                if (self::isNumber($c['text'])) {
                    // Ignore the narrow BIL. column on the far left.
                    if ($c['x'] > 100.0) {
                        $firstNumberX = min($firstNumberX, $c['x']);
                    }
                }
            }
        }

        foreach ($lines as $cells) {
            foreach ($cells as $c) {
                if (preg_match('#^\d{2,3}\s*/\s*\d{1,2}\s*/\s*\d{1,2}$#', $c['text'])) {
                    $anchors[] = ['y' => $c['y'], 'kod' => preg_replace('/\s+/', '', $c['text']), 'x' => $c['x']];
                    $minKodX = min($minKodX, $c['x']);
                } elseif (preg_match('/\p{Lu}{3,}/u', $c['text']) && ! self::isNumber($c['text'])) {
                    $nameCells[] = $c;
                }
            }
        }

        // Assign each name fragment to its NEAREST anchor rather than to every
        // anchor within a fixed window: blocks sit close together, so a window
        // wide enough for a tall block would also swallow its neighbour's name.
        $byAnchor = [];
        foreach ($nameCells as $c) {
            if ($c['x'] <= $minKodX + 20.0 || $c['x'] >= $firstNumberX - 20.0) {
                continue;                      // outside the centre-name column
            }
            $best = null;
            $bestDist = INF;
            foreach ($anchors as $i => $anchor) {
                $d = abs($c['y'] - $anchor['y']);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $best = $i;
                }
            }
            if ($best !== null && $bestDist <= 40.0) {
                $byAnchor[$best][(string) $c['y']] = $c['text'];
            }
        }

        foreach ($anchors as $i => &$anchor) {
            $parts = $byAnchor[$i] ?? [];
            krsort($parts);
            $anchor['pusat'] = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
        }
        unset($anchor);

        return $anchors;
    }

    /** The block whose label sits nearest this row's baseline. */
    private static function blockFor(array $blocks, float $y): ?array
    {
        $best = null;
        $bestDist = INF;
        foreach ($blocks as $b) {
            $d = abs($b['y'] - $y);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $b;
            }
        }

        return $bestDist <= 60.0 ? $best : null;
    }

    /** @return array<int,int> numeric cell values, left to right */
    private static function numbers(array $cells): array
    {
        $out = [];
        foreach ($cells as $c) {
            if (self::isNumber($c['text'])) {
                $out[] = (int) str_replace(',', '', $c['text']);
            }
        }

        return $out;
    }

    private static function posLabel(string $joined): string
    {
        return preg_match('/UNDI\s+(POS|AWAL)/i', $joined, $m)
            ? 'UNDI '.mb_strtoupper($m[1])
            : 'UNDI POS';
    }

    private static function kodFrom(string $joined): ?string
    {
        return preg_match('#(\d{2,3}\s*/\s*\d{1,2}\s*/\s*\d{1,2})#', $joined, $m)
            ? preg_replace('/\s+/', '', $m[1])
            : null;
    }

    private static function joined(array $cells): string
    {
        return trim(implode(' ', array_column($cells, 'text')));
    }

    /**
     * Page furniture — headers, column captions, the printer's footer. The footer
     * is letter-spaced ("k e A d i l a n"), so the test is also run against a
     * despaced copy of the line.
     */
    private static function isFurniture(string $joined): bool
    {
        if ($joined === '') {
            return true;
        }

        return (bool) preg_match(self::FURNITURE, $joined)
            || (bool) preg_match(self::FURNITURE, preg_replace('/\s+/', '', $joined));
    }

    private static function isNumber(string $text): bool
    {
        return (bool) preg_match('/^[\d][\d,]*$/', $text);
    }

    /**
     * Is this line plausibly a data row we failed to read (worth reporting), as
     * opposed to a label or page furniture?
     *
     * The Daerah Mengundi code ("129 / 15 / 02") is stripped first — a label line
     * carrying only a code is not an unread data row, and reporting it as one
     * would bury real failures in noise.
     */
    private static function looksLikeData(string $joined): bool
    {
        $withoutKod = preg_replace('#\d{2,3}\s*/\s*\d{1,2}\s*/\s*\d{1,2}#', '', $joined);

        return strlen(preg_replace('/\D/', '', $withoutKod)) >= 6;
    }
}
