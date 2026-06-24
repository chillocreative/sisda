<?php

namespace App\Services\Keanggotaan;

use App\Models\KeanggotaanJawatankuasa;
use App\Services\ClaudeService;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads an uploaded committee-list spreadsheet and normalises its rows into
 * the keanggotaan_jawatankuasa shape — regardless of how the columns are
 * arranged. Claude inspects the top rows and returns a column mapping; PHP
 * then applies that mapping to every data row (cheap & deterministic — the
 * AI never sees, drops, or invents the bulk of the data). If Claude is
 * unavailable, a heuristic header-alias mapping is used instead.
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
    public function analyze(UploadedFile $file, ?string $jenisDefault): array
    {
        $sheet = Excel::toCollection(null, $file)->first() ?? collect();
        // Normalise every cell to a flat array indexed from 0.
        $rows = $sheet->map(fn ($r) => array_values(collect($r)->map(fn ($v) => $v === null ? '' : trim((string) $v))->all()))
            ->values()->all();

        if ($rows === []) {
            return ['ai_used' => false, 'mapping' => [], 'rows' => [], 'skipped' => 0, 'total' => 0];
        }

        $mapping = $this->aiMapping($rows);
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
     * Ask Claude to identify the header row and per-field column index from the
     * top rows. Returns null when the AI is disabled or returns nothing usable.
     */
    private function aiMapping(array $rows): ?array
    {
        $preview = collect($rows)->take(15)
            ->map(fn ($cells, $i) => "Row {$i}: ".implode(' | ', $cells))
            ->implode("\n");

        $jenisList = implode(', ', KeanggotaanJawatankuasa::JENIS);
        $system = <<<SYS
        You map the columns of a Malaysian political committee member list
        ("Senarai Ahli Jawatankuasa") spreadsheet. Given the top rows, identify
        the header row and which 0-based column index holds each target field.

        Target fields:
        - no_ic: IC / No. KP / Kad Pengenalan (a 12-digit number, may contain dashes)
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
         "jenis_constant": <one of [{$jenisList}] if the whole file is a single committee
                            type stated in a title/section, otherwise null>}
        SYS;

        $user = "Top rows of the spreadsheet:\n\n{$preview}";

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

        return [
            'header_row' => isset($json['header_row']) && is_numeric($json['header_row']) ? (int) $json['header_row'] : null,
            'columns' => $columns,
            'jenis_constant' => $jenisConstant,
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

        return ['header_row' => 0, 'columns' => $columns, 'jenis_constant' => null];
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

        $start = is_int($headerRow) ? $headerRow + 1 : 0;
        $dataRows = array_slice($rows, $start);

        $built = [];
        $skipped = 0;
        foreach ($dataRows as $cells) {
            $cell = fn (?int $idx) => ($idx !== null && isset($cells[$idx]) && $cells[$idx] !== '') ? trim((string) $cells[$idx]) : null;

            $rawIc = $cell($columns['no_ic']);
            $ic = preg_replace('/\D/', '', (string) $rawIc);
            $ic = $ic !== '' ? str_pad($ic, 12, '0', STR_PAD_LEFT) : '';
            if (strlen($ic) !== 12 || ! ctype_digit($ic)) {
                $skipped++;

                continue;
            }

            $jenis = $this->normalizeJenis((string) $cell($columns['jenis'])) ?: $jenisConstant;
            if (! $jenis) {
                $skipped++;

                continue;
            }

            $dun = $cell($columns['dun']);
            $built[] = [
                'no_ic' => $ic,
                'nama' => strtoupper((string) ($cell($columns['nama']) ?? '-')),
                'jenis' => $jenis,
                'jawatan' => $cell($columns['jawatan']),
                'cabang' => $cell($columns['cabang']),
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
