<?php

namespace App\Services\Upload;

use App\Imports\VoterDatabaseImport;
use App\Services\ClaudeService;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * AI fallback for the voter-database upload. Runs ONLY when the fast parser
 * (VoterDatabaseImport) read 0 rows — i.e. the file has weird/unlabeled headers,
 * title/junk rows, several stacked tables, or is freeform (incl. scanned PDFs).
 *
 * Two strategies, mirroring CommitteeImportMapper:
 *   - Spreadsheet: Claude inspects the top rows and returns a column mapping;
 *     PHP applies it to every row (cheap, deterministic — Claude never touches
 *     the data). A header-alias heuristic is the fallback when Claude is off.
 *   - Freeform (pdf/txt): Claude transcribes the records straight from the
 *     document — text is chunked; scanned/image PDFs go to the vision
 *     document_model as a base64 document block.
 *
 * Guardrail: every produced row must carry a valid 12-digit IC
 * (VoterDatabaseImport::normaliseIc) or it is dropped. A hallucinated or
 * mis-typed IC that isn't 12 valid digits never reaches the database. This
 * service is best-effort and never throws.
 */
class AiVoterExtractor
{
    /** Canonical pangkalan_data_pengundi fields this extractor produces. */
    public const FIELDS = [
        'no_ic', 'nama', 'lokaliti', 'kod_lokaliti', 'daerah_mengundi',
        'kadun', 'parlimen', 'negeri', 'bangsa', 'jantina', 'tahun_lahir',
    ];

    /** Header aliases for the heuristic mapping fallback (lowercased, alnum only). */
    private const ALIASES = [
        'no_ic' => ['noic', 'ic', 'nokp', 'kp', 'kadpengenalan', 'nokadpengenalan', 'nokadpengenalanbaru', 'mykad', 'icnumber'],
        'nama' => ['nama', 'namapenuh', 'namapengundi', 'namaahli', 'name', 'fullname'],
        'negeri' => ['namanegeri', 'negeri', 'state'],
        'parlimen' => ['namaparlimen', 'parlimen', 'parliament', 'bahagianpilihanraya'],
        'kadun' => ['namadun', 'namakadun', 'kadun', 'dun', 'stateassembly'],
        'daerah_mengundi' => ['namadm', 'daerahmengundi', 'dm', 'pollingdistrict'],
        'lokaliti' => ['namalokaliti', 'lokaliti', 'locality'],
        'kod_lokaliti' => ['kodlokaliti', 'kodlok'],
        'bangsa' => ['bangsaspr', 'bangsa', 'kaum', 'race'],
        'jantina' => ['kodjantina', 'jantina', 'gender', 'sex'],
        'tahun_lahir' => ['tahunlahir', 'tahunkelahiran', 'birthyear', 'yob'],
    ];

    private const MAX_TEXT = 16000;      // chars per freeform text chunk
    private const MAX_CHUNKS = 40;       // ceiling for very large freeform files
    private const MAX_VISION_BYTES = 30 * 1024 * 1024; // Anthropic PDF limit ~32MB

    public function __construct(protected ClaudeService $claude) {}

    /**
     * @return array{ai_used:bool, path:string, rows:array<int,array<string,?string>>, skipped:int, total:int, mapping:?array, error:?string, chunks:int}
     */
    public function analyze(string $absPath, string $ext, ?string $filename = null): array
    {
        $ext = strtolower($ext);
        try {
            return in_array($ext, ['pdf', 'txt'], true)
                ? $this->analyzeFreeform($absPath, $ext, $filename)
                : $this->analyzeSpreadsheet($absPath, $filename);
        } catch (\Throwable $e) {
            Log::warning('AiVoterExtractor gagal: '.$e->getMessage());

            return $this->blank('exception:'.$e->getMessage());
        }
    }

    private function blank(?string $error = null, string $path = 'none'): array
    {
        return ['ai_used' => false, 'path' => $path, 'rows' => [], 'skipped' => 0, 'total' => 0, 'mapping' => null, 'error' => $error, 'chunks' => 0];
    }

    // ---- Spreadsheet path -------------------------------------------------

    private function analyzeSpreadsheet(string $absPath, ?string $filename): array
    {
        $sheet = Excel::toCollection(null, $absPath)->first() ?? collect();
        $rows = $sheet
            ->map(fn ($r) => array_values(collect($r)->map(fn ($v) => $v === null ? '' : trim((string) $v))->all()))
            ->values()->all();

        if ($rows === []) {
            return $this->blank('empty_sheet', 'spreadsheet');
        }

        $mapping = $this->aiMapping($rows, $filename);
        $aiUsed = $mapping !== null;
        if (! $aiUsed) {
            $mapping = $this->heuristicMapping($rows);
        }

        [$built, $skipped] = $this->applyMapping($rows, $mapping);

        // Mapping produced nothing usable — the sheet may hold prose rather than
        // a table. Flatten it to text and transcribe records instead.
        if ($built === []) {
            $extract = $this->extractFromText($this->flatten($rows), $filename);
            $extract['path'] = 'spreadsheet_freeform';

            return $extract;
        }

        return [
            'ai_used' => $aiUsed,
            'path' => 'spreadsheet',
            'rows' => $built,
            'skipped' => $skipped,
            'total' => count($built) + $skipped,
            'mapping' => $mapping,
            'error' => null,
            'chunks' => 0,
        ];
    }

    /**
     * Ask Claude for the header row and each field's 0-based column index from
     * the top rows. Returns null when Claude is disabled / returns nothing usable.
     */
    private function aiMapping(array $rows, ?string $filename): ?array
    {
        $preview = collect($rows)->take(20)
            ->map(fn ($cells, $i) => "Row {$i}: ".implode(' | ', $cells))
            ->implode("\n");

        $fields = implode(', ', self::FIELDS);
        $system = <<<SYS
        You map the columns of a Malaysian voter-roll (DPT / "senarai pengundi")
        spreadsheet. The file has messy or unlabeled headers, title/banner rows,
        or several stacked tables. Given the file name and the top rows, find the
        header row and which 0-based column index holds each target field.

        Target fields ({$fields}):
        - no_ic: IC / No. KP / Kad Pengenalan — a 12-digit MyKad (may contain dashes)
        - nama: voter full name
        - lokaliti: locality / lokaliti; kod_lokaliti: its code
        - daerah_mengundi: polling district (DM)
        - kadun: DUN / state seat; parlimen: parliament seat; negeri: state
        - bangsa: race / kaum; jantina: gender (L/P); tahun_lahir: birth year

        Reply with JSON only, no prose:
        {"header_row": <int index of the header row, or null if there is no header>,
         "columns": {"no_ic": <col index or null>, "nama": <col index or null>,
                     "lokaliti": <col index or null>, "kod_lokaliti": <col index or null>,
                     "daerah_mengundi": <col index or null>, "kadun": <col index or null>,
                     "parlimen": <col index or null>, "negeri": <col index or null>,
                     "bangsa": <col index or null>, "jantina": <col index or null>,
                     "tahun_lahir": <col index or null>}}
        SYS;

        $user = 'File name: '.($filename ?: '(unknown)')."\n\nTop rows of the spreadsheet:\n\n{$preview}";

        $res = $this->claude->chat($system, $user, 1024, 30, 'voter_upload_mapping');
        if (! ($res['ok'] ?? false)) {
            return null;
        }

        $json = $this->claude->extractJson($res['content']);
        if (! is_array($json) || ! isset($json['columns']) || ! is_array($json['columns'])) {
            return null;
        }

        return $this->sanitizeMapping($json);
    }

    /** Coerce a raw AI/heuristic mapping into a safe, fully-keyed structure. */
    public function sanitizeMapping(array $json): array
    {
        $columns = [];
        foreach (self::FIELDS as $field) {
            $idx = $json['columns'][$field] ?? null;
            $columns[$field] = is_numeric($idx) ? (int) $idx : null;
        }

        return [
            'header_row' => isset($json['header_row']) && is_numeric($json['header_row']) ? (int) $json['header_row'] : null,
            'columns' => $columns,
        ];
    }

    /** Fallback: match the first row's cells against known header aliases. */
    public function heuristicMapping(array $rows): array
    {
        $header = collect($rows[0] ?? [])
            ->map(fn ($v) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)))
            ->all();

        $columns = [];
        foreach (self::FIELDS as $field) {
            $columns[$field] = null;
            foreach (self::ALIASES[$field] as $alias) {
                $i = array_search($alias, $header, true);
                if ($i !== false) {
                    $columns[$field] = (int) $i;
                    break;
                }
            }
        }

        return ['header_row' => 0, 'columns' => $columns];
    }

    /**
     * Apply a mapping to every data row below the header. Pure/deterministic.
     *
     * @return array{0:array<int,array<string,?string>>, 1:int} [rows, skipped]
     */
    public function applyMapping(array $rows, array $mapping): array
    {
        $columns = $mapping['columns'] ?? [];
        $headerRow = $mapping['header_row'] ?? null;
        $start = is_int($headerRow) ? $headerRow + 1 : 0;
        $dataRows = array_slice($rows, $start);

        $built = [];
        $skipped = 0;
        foreach ($dataRows as $cells) {
            $rec = $this->buildRecord(array_values((array) $cells), $columns);
            if ($rec === null) {
                $skipped++;

                continue;
            }
            $built[] = $rec;
        }

        return [$built, $skipped];
    }

    /** Build one voter row from mapped columns, or null if it has no valid IC. */
    private function buildRecord(array $cells, array $columns): ?array
    {
        // IC from the mapped column (lenient), else scan any cell for a MyKad
        // shape (strict). No valid IC ⇒ drop the row (the hallucination guard).
        $ic = VoterDatabaseImport::normaliseIc((string) $this->cellAt($cells, $columns['no_ic'] ?? null));
        if ($ic === null) {
            foreach ($cells as $c) {
                $ic = VoterDatabaseImport::normaliseIc((string) $c, true);
                if ($ic !== null) {
                    break;
                }
            }
        }
        if ($ic === null) {
            return null;
        }

        $nama = $this->upper($this->cellAt($cells, $columns['nama'] ?? null)) ?? $this->pickName($cells);

        return [
            'no_ic' => $ic,
            'nama' => $nama !== '' ? $nama : '-',
            'lokaliti' => $this->upper($this->cellAt($cells, $columns['lokaliti'] ?? null)),
            'kod_lokaliti' => $this->cellAt($cells, $columns['kod_lokaliti'] ?? null),
            'daerah_mengundi' => $this->upper($this->cellAt($cells, $columns['daerah_mengundi'] ?? null)),
            'kadun' => $this->upper($this->cellAt($cells, $columns['kadun'] ?? null)),
            'parlimen' => $this->upper($this->cellAt($cells, $columns['parlimen'] ?? null)),
            'negeri' => $this->upper($this->cellAt($cells, $columns['negeri'] ?? null)),
            'bangsa' => $this->upper($this->cellAt($cells, $columns['bangsa'] ?? null)),
            'jantina' => $this->normaliseJantina($this->cellAt($cells, $columns['jantina'] ?? null)),
            'tahun_lahir' => $this->cellAt($cells, $columns['tahun_lahir'] ?? null),
        ];
    }

    // ---- Freeform path (pdf/txt) -----------------------------------------

    private function analyzeFreeform(string $absPath, string $ext, ?string $filename): array
    {
        if ($ext === 'txt') {
            $text = (string) @file_get_contents($absPath);

            return $this->extractFromText($text, $filename);
        }

        // PDF: prefer extracted text; if the PDF is scanned (little/no text),
        // hand the whole document to the vision model.
        $text = '';
        try {
            $text = (new PdfParser)->parseFile($absPath)->getText();
        } catch (\Throwable $e) {
            $text = '';
        }

        if (mb_strlen(trim($text)) > 100) {
            return $this->extractFromText($text, $filename);
        }

        return $this->extractFromVision($absPath, $filename);
    }

    /** Chunk free text and transcribe records from each chunk, deduping by IC. */
    private function extractFromText(string $text, ?string $filename): array
    {
        $text = trim($text);
        if ($text === '') {
            return $this->blank('empty_text', 'freeform');
        }

        $chunks = $this->chunkText($text);
        $rawRecords = [];
        $used = 0;
        foreach ($chunks as $chunk) {
            $recs = $this->aiExtractRecords($chunk, $filename);
            if ($recs !== null) {
                $used++;
                $rawRecords = array_merge($rawRecords, $recs);
            }
        }

        if ($used === 0) {
            return $this->blank('ai_unavailable', 'freeform');
        }

        [$built, $skipped] = $this->normalizeRecords($rawRecords);

        return [
            'ai_used' => true,
            'path' => 'freeform',
            'rows' => $built,
            'skipped' => $skipped,
            'total' => count($rawRecords),
            'mapping' => null,
            'error' => null,
            'chunks' => count($chunks),
        ];
    }

    /** Scanned/image PDF: send the whole document to the vision document_model. */
    private function extractFromVision(string $absPath, ?string $filename): array
    {
        $bytes = @file_get_contents($absPath);
        if ($bytes === false || $bytes === '') {
            return $this->blank('unreadable_pdf', 'vision');
        }
        if (strlen($bytes) > self::MAX_VISION_BYTES) {
            return $this->blank('pdf_too_large_for_vision', 'vision');
        }

        $content = [
            ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($bytes)]],
            ['type' => 'text', 'text' => $this->extractionPrompt($filename)],
        ];

        $res = $this->claude->chat($this->extractionSystem(), $content, 8000, 180, 'voter_upload_vision', $this->claude->documentModel());
        if (! ($res['ok'] ?? false)) {
            return $this->blank('vision_unavailable', 'vision');
        }

        $json = $this->claude->extractJson($res['content']);
        $records = (is_array($json) && isset($json['records']) && is_array($json['records'])) ? $json['records'] : [];
        [$built, $skipped] = $this->normalizeRecords($records);

        return [
            'ai_used' => true,
            'path' => 'vision',
            'rows' => $built,
            'skipped' => $skipped,
            'total' => count($records),
            'mapping' => null,
            'error' => null,
            'chunks' => 1,
        ];
    }

    /** One transcription call over a text chunk. Returns records[] or null. */
    private function aiExtractRecords(string $content, ?string $filename): ?array
    {
        $user = 'File name: '.($filename ?: '(unknown)')."\n\nDocument text:\n\n{$content}";

        $res = $this->claude->chat($this->extractionSystem(), $user, 8000, 90, 'voter_upload_text');
        if (! ($res['ok'] ?? false)) {
            return null;
        }

        $json = $this->claude->extractJson($res['content']);
        if (! is_array($json) || ! isset($json['records']) || ! is_array($json['records'])) {
            return null;
        }

        return $json['records'];
    }

    private function extractionSystem(): string
    {
        $fields = implode(', ', self::FIELDS);

        return <<<SYS
        You transcribe voter records from the raw content of a Malaysian voter
        roll (DPT / "senarai pengundi"). The layout is unpredictable — extracted
        PDF text or a scanned document. Transcribe ONLY what is literally present;
        never invent a value. Leave a field null when it is not shown.

        Fields ({$fields}): no_ic (12-digit MyKad, may have dashes), nama (full
        name), lokaliti, kod_lokaliti, daerah_mengundi, kadun (DUN / state seat),
        parlimen, negeri, bangsa (race), jantina (L/P), tahun_lahir (birth year).

        Reply with JSON only, no prose:
        {"records": [{"no_ic": <string or null>, "nama": <string or null>,
          "lokaliti": <string or null>, "kod_lokaliti": <string or null>,
          "daerah_mengundi": <string or null>, "kadun": <string or null>,
          "parlimen": <string or null>, "negeri": <string or null>,
          "bangsa": <string or null>, "jantina": <string or null>,
          "tahun_lahir": <string or null>}]}
        Extract every distinct voter you can find.
        SYS;
    }

    private function extractionPrompt(?string $filename): string
    {
        return 'File name: '.($filename ?: '(unknown)').
            "\n\nTranscribe every voter record in this document as JSON per the schema.";
    }

    /**
     * Normalise AI-transcribed records into DB rows. Pure/deterministic. Drops
     * any record without a valid 12-digit IC and dedupes by IC.
     *
     * @return array{0:array<int,array<string,?string>>, 1:int} [rows, skipped]
     */
    public function normalizeRecords(array $records): array
    {
        $built = [];
        $skipped = 0;
        $seen = [];
        foreach ($records as $r) {
            if (! is_array($r)) {
                $skipped++;

                continue;
            }
            $ic = VoterDatabaseImport::normaliseIc((string) ($r['no_ic'] ?? ''));
            if ($ic === null || isset($seen[$ic])) {
                $skipped++;

                continue;
            }
            $seen[$ic] = true;

            $built[] = [
                'no_ic' => $ic,
                'nama' => $this->upper($this->clean($r['nama'] ?? null)) ?? '-',
                'lokaliti' => $this->upper($this->clean($r['lokaliti'] ?? null)),
                'kod_lokaliti' => $this->clean($r['kod_lokaliti'] ?? null),
                'daerah_mengundi' => $this->upper($this->clean($r['daerah_mengundi'] ?? null)),
                'kadun' => $this->upper($this->clean($r['kadun'] ?? null)),
                'parlimen' => $this->upper($this->clean($r['parlimen'] ?? null)),
                'negeri' => $this->upper($this->clean($r['negeri'] ?? null)),
                'bangsa' => $this->upper($this->clean($r['bangsa'] ?? null)),
                'jantina' => $this->normaliseJantina($this->clean($r['jantina'] ?? null)),
                'tahun_lahir' => $this->clean($r['tahun_lahir'] ?? null),
            ];
        }

        return [$built, $skipped];
    }

    // ---- helpers ----------------------------------------------------------

    /** @return array<int,string> */
    private function chunkText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $chunks = [];
        $buf = '';
        foreach ($lines as $line) {
            if (mb_strlen($buf) + mb_strlen($line) + 1 > self::MAX_TEXT && $buf !== '') {
                $chunks[] = $buf;
                $buf = '';
                if (count($chunks) >= self::MAX_CHUNKS) {
                    break;
                }
            }
            $buf .= $line."\n";
        }
        if ($buf !== '' && count($chunks) < self::MAX_CHUNKS) {
            $chunks[] = $buf;
        }

        return $chunks;
    }

    private function flatten(array $rows): string
    {
        return collect($rows)
            ->map(fn ($cells) => implode(' | ', array_map(fn ($v) => trim((string) $v), (array) $cells)))
            ->implode("\n");
    }

    private function cellAt(array $cells, ?int $idx): ?string
    {
        if ($idx === null || ! array_key_exists($idx, $cells)) {
            return null;
        }
        $v = trim((string) $cells[$idx]);

        return $v === '' ? null : $v;
    }

    private function clean($v): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private function upper(?string $v): ?string
    {
        return $v === null ? null : (strtoupper($v) ?: null);
    }

    /** Content fallback for a missing name: the most-alphabetic non-IC cell. */
    private function pickName(array $cells): string
    {
        $best = '';
        $bestScore = 0;
        foreach ($cells as $cell) {
            $text = trim((string) $cell);
            if (VoterDatabaseImport::normaliseIc($text, true) !== null) {
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
