<?php

namespace App\Services\Pilihanraya;

use App\Services\ClaudeService;
use App\Support\Pilihanraya\ScoresheetParser;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Turns an uploaded scoresheet (any layout) into a party-agnostic result set:
 *
 *   ['parties' => ['PAKATAN HARAPAN', ...],
 *    'rows'    => [['kawasan'=>..,'pemilih'=>..,'keluar'=>..,'ditolak'=>..,'undi'=>['PARTI'=>int]], ...],
 *    'totals'  => ['pemilih'=>..,'keluar'=>..,'ditolak'=>..,'undi'=>['PARTI'=>int], 'parties'=>[...]],
 *    'source'  => 'deterministic'|'ai', 'contest' => string|null]
 *
 * The parties are read from the SHEET ITSELF — for the standard Buloh Kasap
 * layout via the deterministic ScoresheetParser (free/instant); for any other
 * layout via Claude, which identifies the contesting parties from the file's
 * own headers and extracts the votes. This lets the comparison work with
 * whatever parties actually contested, not a fixed PH/BN/PN template.
 */
class ScoresheetExtractor
{
    private const SYSTEM = 'You extract structured results from a raw Malaysian election scoresheet (given as a '
        ."delimited grid, as raw text extracted from a PDF, or as a document/image you can read directly) for analysis of a STATE assembly seat (DUN). Read the sheet's own column headers to identify "
        .'the contesting parties/coalitions — do NOT assume a fixed party set. If the sheet contains more than one '
        .'contest (e.g. a parliamentary/PRU block and a state/PRN or DUN block side by side or stacked), extract ONLY '
        .'the STATE (DUN / ADUN / PRN) contest. Aggregate per polling area (Daerah Mengundi, or the largest area level '
        .'available) — do not return per-saluran detail. '
        .'MANY of these files are the standard SPR score sheet (HELAIAN MATA / SCORE SHEET, Borang SPR 760). Its columns '
        .'run, left to right: Bil; No. Kod Daerah Mengundi; Nama Pusat Mengundi; Nombor Tempat Mengundi (saluran); '
        .'"Jumlah kertas undi yang patut berada di dalam peti undi (A)"; then ONE COLUMN PER CANDIDATE under the banner '
        .'"Bilangan undian oleh pemilih bagi setiap orang calon yang bertanding" — each column is headed by the CANDIDATE '
        .'NAME with the party symbol/logo beneath it; then "Jumlah undian oleh pemilih"; "Bilangan kertas undi yang '
        .'ditolak (C)"; and "...tidak dimasukkan ke dalam peti undi (D)". For THIS form follow these rules exactly: '
        .'(1) registered voters `pemilih` = the "JUMLAH PEMILIH" figure printed at the TOP of the sheet — NOT column (A); '
        .'(2) take each candidate total from the final bold "JUMLAH" (grand-total) row. The number of candidate '
        .'vote-totals in that JUMLAH row MUST equal the number of candidate columns — align every total to its OWN '
        .'candidate column strictly by left-to-right position, and NEVER shift, merge, or skip a column even when a '
        ."candidate's total is very small (e.g. a 3-digit value between larger ones); double-check the count matches "
        .'before answering; (3) `keluar` (votes cast) = the "Jumlah undian oleh pemilih" total, which normally equals the '
        .'sum of all candidate totals plus rejected votes — use it as a cross-check that your columns are aligned; '
        .'(4) `ditolak` = the "Bilangan kertas undi yang ditolak (C)" total; (5) for each candidate set the party key to '
        .'the coalition/party shown by the symbol or party name in that candidate\'s column (PAKATAN HARAPAN, PERIKATAN '
        .'NASIONAL, BARISAN NASIONAL, PEJUANG, etc.); use the candidate\'s own name only if no party is identifiable. '
        .'Respond ONLY with a JSON object, no prose: '
        .'{"contest":"short description of which contest you extracted",'
        .'"parties":["exact party/coalition name as printed"],'
        .'"rows":[{"kawasan":"area name","pemilih":int or null,"keluar":int or null,"ditolak":int or null,'
        .'"undi":{"PARTY NAME":int}}],'
        .'"totals":{"pemilih":int or null,"keluar":int or null,"ditolak":int or null,"undi":{"PARTY NAME":int}}}. '
        .'Every number MUST be copied verbatim from the sheet (integers) — never invent, estimate, or compute figures '
        .'not present. Party keys in every `undi` map must exactly match the `parties` list. If a total row is printed '
        .'in the sheet, use it; otherwise leave totals numbers null and they will be summed from rows.';

    public function __construct(protected ClaudeService $claude) {}

    private const IMAGE_MEDIA = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif',
    ];

    /** @return array{parties:array,rows:array,totals:array,source:string,contest:?string}|null */
    public function extract(UploadedFile $file): ?array
    {
        // PDFs and images have no reliable tabular grid — send the file to
        // Claude as a native document/image so it reads the scoresheet itself
        // (this handles scanned PDFs and photographed sheets, not just text).
        if ($this->isImage($file)) {
            $ext = strtolower($file->getClientOriginalExtension());
            return $this->fromMedia($file, 'image', self::IMAGE_MEDIA[$ext] ?? 'image/jpeg');
        }
        if ($this->isPdf($file)) {
            // Native document read first; fall back to text extraction if the
            // configured model can't take documents.
            return $this->fromMedia($file, 'document', 'application/pdf')
                ?? $this->fromPdfText($file);
        }

        $grid = ScoresheetParser::grid($file);

        // Fast path: the standard "DAERAH MENGUNDI + PH/BN" layout.
        $std = ScoresheetParser::normalize($grid);
        if ($std) {
            return $this->fromStandard($std);
        }

        // Any other layout → let Claude read the sheet and detect the parties.
        return $this->extractWithAi($this->gridToText($grid));
    }

    private function isPdf(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'pdf'
            || strtolower((string) $file->getClientMimeType()) === 'application/pdf';
    }

    private function isImage(UploadedFile $file): bool
    {
        return isset(self::IMAGE_MEDIA[strtolower($file->getClientOriginalExtension())])
            || str_starts_with(strtolower((string) $file->getClientMimeType()), 'image/');
    }

    /**
     * Send the raw file to Claude as a native document/image content block —
     * the most reliable path for PDFs and photos. Returns null on any failure
     * so the caller can fall back or surface the standard error.
     */
    private function fromMedia(UploadedFile $file, string $blockType, string $mediaType): ?array
    {
        $bytes = @file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $content = [
            [
                'type' => $blockType,
                'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($bytes)],
            ],
            ['type' => 'text', 'text' => 'Baca scoresheet dalam fail ini dan ekstrak pertandingan DUN (negeri) mengikut arahan sistem. Balas JSON sahaja.'],
        ];

        $result = $this->claude->chat(self::SYSTEM, $content, 6000, 180, 'scoresheet_extract');
        if (! $result['ok']) {
            return null;
        }

        return $this->sanitize($this->claude->extractJson($result['content']));
    }

    /** Fallback for PDFs: extract the text layer and read that instead. */
    private function fromPdfText(UploadedFile $file): ?array
    {
        try {
            $text = trim((string) (new PdfParser)->parseFile($file->getRealPath())->getText());
        } catch (\Throwable $e) {
            return null;
        }

        // A scanned/image-only PDF yields (almost) no text — nothing to read.
        if (mb_strlen($text) < 40) {
            return null;
        }

        return $this->extractWithAi("Raw scoresheet text extracted from a PDF:\n".mb_substr($text, 0, 24000));
    }

    /** Convert the deterministic parser output into the party-agnostic shape. */
    private function fromStandard(array $std): array
    {
        $names = ['ph' => 'PH', 'bn' => 'BN', 'pn' => 'PN', 'pejuang' => 'PEJUANG'];
        $totals = $std['totals'];
        $parties = [];
        foreach ($names as $key => $label) {
            if ((float) ($totals[$key] ?? 0) > 0) {
                $parties[] = $label;
            }
        }

        $rows = array_map(function ($r) use ($names, $parties) {
            $undi = [];
            foreach ($names as $key => $label) {
                if (in_array($label, $parties, true)) {
                    $undi[$label] = (int) ($r[$key] ?? 0);
                }
            }

            return [
                'kawasan' => $r['dm'] ?? '',
                'pemilih' => isset($r['pemilih']) ? (int) $r['pemilih'] : null,
                'keluar' => isset($r['keluar']) ? (int) $r['keluar'] : null,
                'ditolak' => isset($r['ditolak']) ? (int) $r['ditolak'] : null,
                'undi' => $undi,
            ];
        }, $std['rows']);

        $undiTotals = [];
        foreach ($parties as $label) {
            $key = array_search($label, $names, true);
            $undiTotals[$label] = (int) ($totals[$key] ?? 0);
        }

        return [
            'parties' => $parties,
            'rows' => $rows,
            'totals' => [
                'pemilih' => (int) ($totals['pemilih'] ?? 0),
                'keluar' => (int) ($totals['keluar'] ?? 0),
                'ditolak' => (int) ($totals['ditolak'] ?? 0),
                'undi' => $undiTotals,
                'parties' => $parties,
            ],
            'source' => 'deterministic',
            'contest' => null,
        ];
    }

    /** Read raw scoresheet text (grid dump or PDF text) with Claude. */
    private function extractWithAi(string $text): ?array
    {
        $result = $this->claude->chat(self::SYSTEM, $text, 6000, 120, 'scoresheet_extract');
        if (! $result['ok']) {
            return null;
        }

        $json = $this->claude->extractJson($result['content']);

        return $this->sanitize($json);
    }

    /** Compact delimited representation of the grid for the model. */
    private function gridToText(array $grid): string
    {
        $lines = [];
        foreach (array_slice($grid, 0, 300) as $i => $row) {
            $cells = array_map(fn ($c) => is_null($c) ? '' : (string) $c, array_slice($row, 0, 40));
            // Drop fully-empty rows to save tokens.
            if (implode('', $cells) === '') {
                continue;
            }
            $lines[] = $i.': '.implode(' | ', $cells);
        }

        return "Raw scoresheet grid (row: cell | cell | ...):\n".implode("\n", $lines);
    }

    /** Force the AI reply into the party-agnostic shape; null if unusable. */
    private function sanitize(?array $json): ?array
    {
        if (! $json) {
            return null;
        }

        $int = fn ($v) => is_numeric($v) ? (int) round((float) $v) : null;
        $partyName = fn ($v) => mb_strtoupper(trim((string) $v));

        $parties = collect(is_array($json['parties'] ?? null) ? $json['parties'] : [])
            ->map($partyName)->filter()->unique()->take(10)->values()->all();

        $cleanUndi = function ($undi) use ($int, $partyName) {
            $out = [];
            foreach (is_array($undi) ? $undi : [] as $k => $v) {
                $name = $partyName($k);
                $n = $int($v);
                if ($name !== '' && $n !== null) {
                    $out[$name] = $n;
                }
            }

            return $out;
        };

        $rows = collect(is_array($json['rows'] ?? null) ? $json['rows'] : [])
            ->filter(fn ($r) => is_array($r))
            ->map(fn ($r) => [
                'kawasan' => trim((string) ($r['kawasan'] ?? '')),
                'pemilih' => $int($r['pemilih'] ?? null),
                'keluar' => $int($r['keluar'] ?? null),
                'ditolak' => $int($r['ditolak'] ?? null),
                'undi' => $cleanUndi($r['undi'] ?? []),
            ])
            ->filter(fn ($r) => $r['kawasan'] !== '' && array_sum($r['undi']) > 0)
            ->values()->all();

        if (empty($rows)) {
            return null;
        }

        // Party set = whatever appears in the data (union of detected + row keys).
        $fromRows = collect($rows)->flatMap(fn ($r) => array_keys($r['undi']))->unique()->values()->all();
        $parties = collect(array_merge($parties, $fromRows))->unique()->take(10)->values()->all();
        if (empty($parties)) {
            return null;
        }

        // Totals — printed if present, else summed from the rows.
        $t = is_array($json['totals'] ?? null) ? $json['totals'] : [];
        $undiTotals = $cleanUndi($t['undi'] ?? []);
        $sum = fn ($key) => collect($rows)->sum(fn ($r) => (int) ($r[$key] ?? 0));
        $sumUndi = function ($p) use ($rows) {
            return collect($rows)->sum(fn ($r) => (int) ($r['undi'][$p] ?? 0));
        };
        foreach ($parties as $p) {
            if (! isset($undiTotals[$p]) || $undiTotals[$p] <= 0) {
                $undiTotals[$p] = $sumUndi($p);
            }
        }

        $keluar = $int($t['keluar'] ?? null) ?? $sum('keluar');
        if ($keluar <= 0) {
            $keluar = array_sum($undiTotals) + ($int($t['ditolak'] ?? null) ?? 0);
        }

        return [
            'parties' => $parties,
            'rows' => $rows,
            'totals' => [
                'pemilih' => $int($t['pemilih'] ?? null) ?? $sum('pemilih'),
                'keluar' => $keluar,
                'ditolak' => $int($t['ditolak'] ?? null) ?? $sum('ditolak'),
                'undi' => $undiTotals,
                'parties' => $parties,
            ],
            'source' => 'ai',
            'contest' => trim((string) ($json['contest'] ?? '')) ?: null,
        ];
    }
}
