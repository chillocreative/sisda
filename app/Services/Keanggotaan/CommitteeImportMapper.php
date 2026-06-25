<?php

namespace App\Services\Keanggotaan;

use App\Models\KeanggotaanJawatankuasa;
use App\Services\ClaudeService;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Reads an uploaded committee list and normalises it into the
 * keanggotaan_jawatankuasa shape.
 *
 * Spreadsheets (xlsx/xls/csv): Claude inspects the top rows and returns a
 * column mapping; PHP then applies it to every data row (cheap & deterministic;
 * a heuristic header-alias mapping is the fallback when Claude is unavailable).
 *
 * Free-text files (pdf/txt): the structure is unpredictable, so Claude extracts
 * the member list directly from the document text.
 */
class CommitteeImportMapper
{
    /** Target fields, in table order. */
    private const FIELDS = ['no_ic', 'nama', 'jenis', 'jawatan', 'cabang', 'dun', 'no_tel'];

    /** Header aliases for the heuristic fallback (normalised: lowercase, alnum only). */
    private const ALIASES = [
        'no_ic' => ['ic', 'noic', 'nokp', 'kadpengenalan', 'mykad', 'nokadpengenalan'],
        'nama' => ['nama', 'name', 'namapenuh'],
        'jenis' => ['jenis', 'kategori', 'type'],
        'jawatan' => ['jawatan', 'position', 'jawatandipegang'],
        'cabang' => ['cabang', 'branch', 'bahagian'],
        'dun' => ['dun', 'kadun', 'kawasan'],
        'no_tel' => ['notel', 'telefon', 'phone', 'notelefon', 'hp'],
    ];

    public function __construct(protected ClaudeService $claude) {}

    /**
     * @return array{ai_used:bool, mapping:array, rows:array<int,array<string,?string>>, skipped:int, total:int}
     */
    public function analyze(UploadedFile $file, ?string $jenisDefault, ?string $filename = null): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo((string) $filename, PATHINFO_EXTENSION));

        return in_array($ext, ['pdf', 'txt'], true)
            ? $this->analyzeText($file, $ext, $jenisDefault, $filename)
            : $this->analyzeSpreadsheet($file, $jenisDefault, $filename);
    }

    /** Spreadsheet path: AI maps columns, PHP applies the mapping to every row. */
    private function analyzeSpreadsheet(UploadedFile $file, ?string $jenisDefault, ?string $filename): array
    {
        $sheet = Excel::toCollection(null, $file)->first() ?? collect();
        // Normalise every cell to a flat array indexed from 0.
        $rows = $sheet->map(fn ($r) => array_values(collect($r)->map(fn ($v) => $v === null ? '' : trim((string) $v))->all()))
            ->values()->all();

        if ($rows === []) {
            return ['ai_used' => false, 'mapping' => [], 'rows' => [], 'skipped' => 0, 'total' => 0];
        }

        $mapping = $this->aiMapping($rows, $filename);
        $aiUsed = $mapping !== null;
        if (! $aiUsed) {
            $mapping = $this->heuristicMapping($rows);
        }

        [$built, $skipped, $total] = $this->applyMapping($rows, $mapping, $jenisDefault);

        return [
            'ai_used' => $aiUsed,
            'mapping' => $mapping,
            'rows' => $built,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * Free-text path (pdf/txt): the layout is unpredictable, so Claude extracts
     * the member list straight from the document text. No heuristic fallback —
     * this needs the AI.
     */
    private function analyzeText(UploadedFile $file, string $ext, ?string $jenisDefault, ?string $filename): array
    {
        $empty = ['ai_used' => false, 'mapping' => [], 'rows' => [], 'skipped' => 0, 'total' => 0];

        try {
            $text = $ext === 'pdf'
                ? (new PdfParser)->parseFile($file->getRealPath())->getText()
                : (string) file_get_contents($file->getRealPath());
        } catch (\Throwable $e) {
            return $empty;
        }

        if (trim($text) === '') {
            return $empty;
        }

        $extracted = $this->aiExtractMembers($text, $filename);
        if ($extracted === null) {
            return $empty;
        }

        [$built, $skipped, $total] = $this->normalizeMembers(
            $extracted['members'], $extracted['jenis_constant'], $extracted['dun_constant'], $extracted['parlimen_constant'], $jenisDefault
        );

        return [
            'ai_used' => true,
            'mapping' => ['header_row' => null, 'columns' => [], 'jenis_constant' => $extracted['jenis_constant'], 'dun_constant' => $extracted['dun_constant'], 'parlimen_constant' => $extracted['parlimen_constant']],
            'rows' => $built,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * Ask Claude to extract every committee member from free document text.
     * Returns ['members'=>[...], 'jenis_constant'=>?string, 'dun_constant'=>?string]
     * or null when the AI is unavailable / returns nothing usable.
     */
    private function aiExtractMembers(string $text, ?string $filename): ?array
    {
        $jenisList = implode(', ', KeanggotaanJawatankuasa::JENIS);
        // Cap the text so the prompt stays within token limits.
        $snippet = mb_substr($text, 0, 16000);

        $system = <<<SYS
        You extract committee members ("Ahli Jawatankuasa") from the raw text of a
        Malaysian political party committee document. The text may be messy
        (extracted from a PDF or TXT) and often has NO IC numbers.

        Committee types (jenis):
        - JPRC = Jawatankuasa Pilihan Raya Cabang
        - JPRD = Jawatankuasa Pilihan Raya DUN (one per DUN / state seat)
        - AJK_CABANG = Ahli Jawatankuasa Cabang
        - WANITA = Wanita (women's wing)
        - AMK = Angkatan Muda (youth wing)
        - MPKK = Majlis Pengurusan Komuniti Kampung
        - JBPP, JPWK = match these by the acronym shown in the document title/heading

        Reply with JSON only, no prose:
        {"jenis_constant": <one of [{$jenisList}] if the whole document is a single type
                            (from the title/heading), otherwise null>,
         "dun_constant": <the DUN / state-seat name if the document is for one DUN, else null>,
         "parlimen_constant": <the Parlimen / Cabang name from the title/heading (e.g.
                               "KEPALA BATAS"), else null>,
         "members": [{"nama": <full name>, "no_ic": <12-digit IC or null>,
                      "jenis": <one of [{$jenisList}] or null>, "jawatan": <position or null>,
                      "cabang": <branch or null>, "dun": <DUN or null>, "no_tel": <phone or null>}]}
        Extract every distinct member you can find. Leave a field null when unknown.
        SYS;

        $user = 'File name: '.($filename ?: '(unknown)')."\n\nDocument text:\n\n{$snippet}";

        $res = $this->claude->chat($system, $user, 8000, 60, 'committee_import_text');
        if (! ($res['ok'] ?? false)) {
            return null;
        }

        $json = $this->claude->extractJson($res['content']);
        if (! is_array($json) || ! isset($json['members']) || ! is_array($json['members'])) {
            return null;
        }

        $jenisConstant = $this->normalizeJenis(is_string($json['jenis_constant'] ?? null) ? $json['jenis_constant'] : '');
        $dunConstant = (isset($json['dun_constant']) && is_string($json['dun_constant']) && trim($json['dun_constant']) !== '')
            ? strtoupper(trim($json['dun_constant'])) : null;
        $parlimenConstant = (isset($json['parlimen_constant']) && is_string($json['parlimen_constant']) && trim($json['parlimen_constant']) !== '')
            ? strtoupper(trim($json['parlimen_constant'])) : null;

        return ['members' => $json['members'], 'jenis_constant' => $jenisConstant, 'dun_constant' => $dunConstant, 'parlimen_constant' => $parlimenConstant];
    }

    /**
     * Normalise an AI-extracted member list (from free text) into table rows,
     * sharing the same rules as the spreadsheet path: IC optional, a row is kept
     * if it has a name, jenis resolves to the enum, DUN falls back to the
     * jawatan text.
     *
     * @return array{0:array<int,array<string,?string>>, 1:int, 2:int}
     */
    private function normalizeMembers(array $members, ?string $jenisConstant, ?string $dunConstant, ?string $parlimenConstant, ?string $jenisDefault): array
    {
        $jenisFallback = $jenisConstant ?: $this->normalizeJenis((string) $jenisDefault);

        $clean = fn ($v) => (is_scalar($v) && trim((string) $v) !== '') ? trim((string) $v) : null;

        $built = [];
        $skipped = 0;
        foreach ($members as $m) {
            if (! is_array($m)) {
                $skipped++;

                continue;
            }

            $ic = preg_replace('/\D/', '', (string) ($m['no_ic'] ?? ''));
            $ic = (strlen($ic) === 12 && ctype_digit($ic)) ? $ic : null;

            $nama = $clean($m['nama'] ?? null);
            $jenis = $this->normalizeJenis((string) ($m['jenis'] ?? '')) ?: $jenisFallback;
            if (($nama === null && $ic === null) || ! $jenis) {
                $skipped++;

                continue;
            }

            $jawatan = $clean($m['jawatan'] ?? null);
            $dun = $clean($m['dun'] ?? null) ?: $dunConstant ?: KeanggotaanJawatankuasa::extractDunFromJawatan($jawatan);
            $built[] = [
                'no_ic' => $ic,
                'nama' => strtoupper((string) ($nama ?? '-')),
                'jenis' => $jenis,
                'jawatan' => $jawatan,
                'cabang' => $clean($m['cabang'] ?? null) ?: $parlimenConstant,
                'dun' => $dun ? strtoupper($dun) : null,
                'no_tel' => $clean($m['no_tel'] ?? null),
            ];
        }

        return [$built, $skipped, count($members)];
    }

    /**
     * Ask Claude to identify the header row and per-field column index from the
     * top rows, plus the committee type and DUN inferred from the file name /
     * title. Returns null when the AI is disabled or returns nothing usable.
     */
    private function aiMapping(array $rows, ?string $filename): ?array
    {
        $preview = collect($rows)->take(15)
            ->map(fn ($cells, $i) => "Row {$i}: ".implode(' | ', $cells))
            ->implode("\n");

        $jenisList = implode(', ', KeanggotaanJawatankuasa::JENIS);
        $system = <<<SYS
        You map the columns of a Malaysian political election committee list
        ("Senarai Ahli Jawatankuasa") spreadsheet. Given the file name and the
        top rows, identify the header row and which 0-based column index holds
        each target field. Many of these files are "struktur" / structure files
        listing positions and names with NO IC column — that is fine, return
        null for no_ic then.

        Committee types:
        - JPRC = Jawatankuasa Pilihan Raya Cabang (parliament / cabang level)
        - JPRD = Jawatankuasa Pilihan Raya DUN (one committee per DUN / state seat)
        - AJK_CABANG = Cabang, WANITA, AMK = party wings; MPKK = Majlis Pengurusan
          Komuniti Kampung; JBPP / JPWK = match by the acronym in the title/heading

        Target fields:
        - no_ic: IC / No. KP / Kad Pengenalan (a 12-digit number, may contain dashes) — often absent
        - nama: member full name
        - jenis: committee type, one of [{$jenisList}]
        - jawatan: position / jawatan held
        - cabang: branch / bahagian
        - dun: DUN / KADUN / state seat name
        - no_tel: phone number

        Reply with JSON only, no prose:
        {"header_row": <int index of the header row, or null if the data has no header>,
         "columns": {"no_ic": <col index or null>, "nama": <col index or null>,
                     "jenis": <col index or null>, "jawatan": <col index or null>,
                     "cabang": <col index or null>, "dun": <col index or null>,
                     "no_tel": <col index or null>},
         "jenis_constant": <one of [{$jenisList}] inferred from the file name or a title/section
                            row (e.g. "Struktur JPRC ..." => JPRC), otherwise null>,
         "dun_constant": <the DUN / state-seat name this file belongs to if it is a single-DUN
                          (JPRD) file named/titled for one DUN, otherwise null>,
         "parlimen_constant": <the Parlimen / Cabang name from the file name or title
                               (e.g. "KEPALA BATAS"), otherwise null>}
        SYS;

        $user = 'File name: '.($filename ?: '(unknown)')."\n\nTop rows of the spreadsheet:\n\n{$preview}";

        $res = $this->claude->chat($system, $user, 1024, 30, 'committee_import_mapping');
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
    private function sanitizeMapping(array $json): array
    {
        $columns = [];
        foreach (self::FIELDS as $field) {
            $idx = $json['columns'][$field] ?? null;
            $columns[$field] = is_numeric($idx) ? (int) $idx : null;
        }

        $jenisConstant = $json['jenis_constant'] ?? null;
        $jenisConstant = $this->normalizeJenis(is_string($jenisConstant) ? $jenisConstant : '');

        $dunConstant = $json['dun_constant'] ?? null;
        $dunConstant = is_string($dunConstant) && trim($dunConstant) !== '' ? strtoupper(trim($dunConstant)) : null;

        $parlimenConstant = $json['parlimen_constant'] ?? null;
        $parlimenConstant = is_string($parlimenConstant) && trim($parlimenConstant) !== '' ? strtoupper(trim($parlimenConstant)) : null;

        return [
            'header_row' => isset($json['header_row']) && is_numeric($json['header_row']) ? (int) $json['header_row'] : null,
            'columns' => $columns,
            'jenis_constant' => $jenisConstant,
            'dun_constant' => $dunConstant,
            'parlimen_constant' => $parlimenConstant,
        ];
    }

    /** Fallback: match header cells against known aliases. */
    private function heuristicMapping(array $rows): array
    {
        $header = collect($rows[0] ?? [])
            ->map(fn ($v) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $v)))
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

        return ['header_row' => 0, 'columns' => $columns, 'jenis_constant' => null, 'dun_constant' => null, 'parlimen_constant' => null];
    }

    /**
     * Apply a mapping to every data row.
     *
     * @return array{0:array<int,array<string,?string>>, 1:int, 2:int} [rows, skipped, total]
     */
    private function applyMapping(array $rows, array $mapping, ?string $jenisDefault): array
    {
        $headerRow = $mapping['header_row'];
        $columns = $mapping['columns'];
        $jenisConstant = $mapping['jenis_constant'] ?: $this->normalizeJenis((string) $jenisDefault);
        $dunConstant = $mapping['dun_constant'] ?? null;
        $parlimenConstant = $mapping['parlimen_constant'] ?? null;

        $start = is_int($headerRow) ? $headerRow + 1 : 0;
        $dataRows = array_slice($rows, $start);

        $built = [];
        $skipped = 0;
        foreach ($dataRows as $cells) {
            $cell = fn (?int $idx) => ($idx !== null && isset($cells[$idx]) && $cells[$idx] !== '') ? trim((string) $cells[$idx]) : null;

            // IC is optional. When present, keep it only if it cleans up to a
            // valid 12-digit number; otherwise store null rather than dropping
            // the whole row.
            $ic = preg_replace('/\D/', '', (string) $cell($columns['no_ic']));
            $ic = (strlen($ic) === 12 && ctype_digit($ic)) ? $ic : null;

            $nama = $cell($columns['nama']);
            $jenis = $this->normalizeJenis((string) $cell($columns['jenis'])) ?: $jenisConstant;

            // A row is only meaningful if it identifies a person (name or IC)
            // and we know which committee it belongs to.
            if (($nama === null && $ic === null) || ! $jenis) {
                $skipped++;

                continue;
            }

            $jawatan = $cell($columns['jawatan']);
            // Prefer an explicit DUN column, then a file-level DUN, then the DUN
            // embedded in the position text (e.g. "... DUN PINANG TUNGGAL").
            $dun = $cell($columns['dun']) ?: $dunConstant ?: KeanggotaanJawatankuasa::extractDunFromJawatan($jawatan);
            $built[] = [
                'no_ic' => $ic,
                'nama' => strtoupper((string) ($nama ?? '-')),
                'jenis' => $jenis,
                'jawatan' => $jawatan,
                'cabang' => $cell($columns['cabang']) ?: $parlimenConstant,
                'dun' => $dun ? strtoupper($dun) : null,
                'no_tel' => $cell($columns['no_tel']),
            ];
        }

        return [$built, $skipped, count($dataRows)];
    }

    /** Normalise a free-text jenis value to one of the enum members, or null. */
    private function normalizeJenis(string $value): ?string
    {
        $norm = strtoupper(str_replace([' ', '-'], '_', trim($value)));

        return in_array($norm, KeanggotaanJawatankuasa::JENIS, true) ? $norm : null;
    }
}
