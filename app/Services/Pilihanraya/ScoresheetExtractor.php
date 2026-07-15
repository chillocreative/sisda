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
        ."delimited grid, or as raw text extracted from a PDF) for analysis of a STATE assembly seat (DUN). Read the sheet's own column headers to identify "
        .'the contesting parties/coalitions — do NOT assume a fixed party set. If the sheet contains more than one '
        .'contest (e.g. a parliamentary/PRU block and a state/PRN or DUN block side by side or stacked), extract ONLY '
        .'the STATE (DUN / ADUN / PRN) contest. Aggregate per polling area (Daerah Mengundi, or the largest area level '
        .'available) — do not return per-saluran detail. '
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

    /** @return array{parties:array,rows:array,totals:array,source:string,contest:?string}|null */
    public function extract(UploadedFile $file): ?array
    {
        // PDF scoresheet → extract its text and let Claude read it. There is no
        // deterministic fast path (PDFs have no reliable tabular grid).
        if ($this->isPdf($file)) {
            return $this->fromPdf($file);
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

    /** Pull the text layer out of a PDF and hand it to Claude for extraction. */
    private function fromPdf(UploadedFile $file): ?array
    {
        try {
            $pdf = (new PdfParser)->parseFile($file->getRealPath());
            $text = trim((string) $pdf->getText());
        } catch (\Throwable $e) {
            return null;
        }

        // A scanned/image-only PDF yields (almost) no text — nothing to read.
        if (mb_strlen($text) < 40) {
            return null;
        }

        // Cap the payload so a huge PDF can't blow the token budget.
        $text = mb_substr($text, 0, 24000);

        return $this->extractWithAi("Raw scoresheet text extracted from a PDF:\n".$text);
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
