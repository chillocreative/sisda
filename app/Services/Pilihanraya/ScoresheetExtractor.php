<?php

namespace App\Services\Pilihanraya;

use App\Services\ClaudeService;
use App\Support\Pilihanraya\ScoresheetParser;
use App\Support\Pilihanraya\Spr760Parser;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Turns an uploaded scoresheet (any layout) into a party-agnostic result set:
 *
 *   ['parties' => ['PAKATAN HARAPAN', ...],
 *    'rows'    => [['kawasan'=>..,'pemilih'=>int|null,'keluar'=>..,'ditolak'=>..,'undi'=>['PARTI'=>int]], ...],
 *    'totals'  => ['pemilih'=>int|null,'keluar'=>..,'ditolak'=>..,'undi'=>['PARTI'=>int], 'parties'=>[...]],
 *    'source'  => 'deterministic'|'ai', 'contest' => string|null]
 *
 * `pemilih` (registered voters) is genuinely absent from many real SPR sheets
 * (no "JUMLAH PEMILIH" header) — it is `null`, NEVER coerced to 0, both per
 * row and in `totals`, whenever no row and no printed total carries a figure.
 * A real printed/summed 0 stays 0; only "nothing was ever known" is null.
 * Callers (e.g. ElectionComparisonService) rely on this to avoid fabricating
 * a "-100%" registered-voter swing against an unknown denominator.
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
            return $this->fromMedia($file);
        }
        if ($this->isPdf($file)) {
            // Native document read first; fall back to text extraction if the
            // configured model can't take documents.
            return $this->fromMedia($file)
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
    private function fromMedia(UploadedFile $file): ?array
    {
        $content = $this->buildContentBlocks($file);
        if ($content === null) {
            return null;
        }

        $result = $this->claude->chat(self::SYSTEM, $content, 6000, 180, 'scoresheet_extract', $this->claude->documentModel());
        if (! $result['ok']) {
            return null;
        }

        return $this->sanitize($this->claude->extractJson($result['content']));
    }

    /**
     * Build the base64 document/image content block(s) for a PDF or photographed
     * scoresheet. Shared by extract() (via fromMedia()) and extractDetailed() so
     * both paths send the file to Claude identically. Returns null when the file
     * is neither an image nor a PDF, or when it can't be read from disk.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function buildContentBlocks(UploadedFile $file): ?array
    {
        if ($this->isImage($file)) {
            $ext = strtolower($file->getClientOriginalExtension());
            $blockType = 'image';
            $mediaType = self::IMAGE_MEDIA[$ext] ?? 'image/jpeg';
        } elseif ($this->isPdf($file)) {
            $blockType = 'document';
            $mediaType = 'application/pdf';
        } else {
            return null;
        }

        $bytes = @file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return [
            [
                'type' => $blockType,
                'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($bytes)],
            ],
            ['type' => 'text', 'text' => 'Baca scoresheet dalam fail ini dan ekstrak pertandingan DUN (negeri) mengikut arahan sistem. Balas JSON sahaja.'],
        ];
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

        // `pemilih` (registered voters) is absent on many real sheets — the
        // ScoresheetParser::normalize() accumulator only ever adds KNOWN row
        // values, so it cannot itself distinguish "no row had a figure" from
        // "the figures summed to zero". Use the row-level nulls (already
        // preserved above) to make that distinction here instead of coercing
        // to 0 when nothing was ever known.
        $pemilihKnown = collect($rows)->contains(fn ($r) => $r['pemilih'] !== null);

        return [
            'parties' => $parties,
            'rows' => $rows,
            'totals' => [
                'pemilih' => $pemilihKnown ? (int) ($totals['pemilih'] ?? 0) : null,
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
        $result = $this->claude->chat(self::SYSTEM, $text, 6000, 120, 'scoresheet_extract', $this->claude->documentModel());
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
        // `pemilih` (registered voters) is genuinely absent from many real SPR
        // sheets (no "JUMLAH PEMILIH" header) — sum() must NOT coerce that to
        // a 0 that reads as "registered voters: zero" downstream. Only sum
        // the rows that actually carry a figure; if NONE do, stay null.
        $sumPemilihOrNull = function () use ($rows) {
            $known = collect($rows)->filter(fn ($r) => $r['pemilih'] !== null);

            return $known->isEmpty() ? null : $known->sum(fn ($r) => (int) $r['pemilih']);
        };
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
                'pemilih' => $int($t['pemilih'] ?? null) ?? $sumPemilihOrNull(),
                'keluar' => $keluar,
                'ditolak' => $int($t['ditolak'] ?? null) ?? $sum('ditolak'),
                'undi' => $undiTotals,
                'parties' => $parties,
            ],
            'source' => 'ai',
            'contest' => trim((string) ($json['contest'] ?? '')) ?: null,
        ];
    }

    /**
     * Prompt kedua: kekalkan Pusat Mengundi + Saluran (lajur 3 & 4) yang SYSTEM sengaja buang.
     * Borang SPR 760 Pin. 1/99 susunan lajur kiri -> kanan:
     *   Bil | No. Kod Daerah Mengundi | Nama Pusat Mengundi | No. Tempat Mengundi (Saluran)
     *   | Jumlah kertas undi dalam peti (A) | [satu lajur per CALON] | Jumlah undian oleh pemilih
     *   | Bilangan kertas undi ditolak (C) | Jumlah kertas undi tidak dimasukkan ke peti (D)
     */
    private const SYSTEM_DETAILED = <<<'TXT'
You read Malaysian SPR "HELAIAN MATA (SCORE SHEET)", Borang SPR 760, and return JSON only.

COLUMN ORDER (left to right, fixed):
  Bil | No. Kod Daerah Mengundi | Nama Pusat Mengundi | No. Tempat Mengundi (Saluran)
  | Jumlah kertas undi yang patut berada di dalam peti undi (A)
  | one column PER CANDIDATE under "Bilangan undian oleh pemilih bagi setiap orang calon"
  | Jumlah undian oleh pemilih | Bilangan kertas undi yang ditolak (C)
  | Jumlah kertas undi ... tidak dimasukkan ke dalam peti undi (D)

RULES:
1. PRESERVE "Nama Pusat Mengundi" and "No. Tempat Mengundi (Saluran)" on EVERY row.
   Do NOT aggregate per Daerah Mengundi. One JSON row per saluran row on the sheet.
2. "undi" is a POSITIONAL ARRAY aligned to "calon" left-to-right. Never reorder, merge,
   skip, or shift a column — not even when a small 3-digit value sits between larger ones.
   The count of numbers in "undi" MUST equal the count of entries in "calon" on every row.
3. Rows before the "UNDI BIASA" section header (e.g. "UNDI POS", "UNDI AWAL") have no
   Pusat Mengundi and no Saluran. Emit them as {"pusat":"","saluran":"UNDI POS"} etc.
   Only emit a row that actually appears — never fabricate a missing UNDI AWAL/POS row.
4. Candidate columns are headed by a PERSON'S NAME with a party LOGO IMAGE. Set
   "parti_tekaan" only if the coalition is unambiguous from visible text; otherwise null
   with "yakin": false. Never guess from the candidate's name.
5. "jumlah_pemilih" is the "JUMLAH PEMILIH" figure at the TOP of the sheet. It is NOT
   column (A). There is NO registered-voter ("berdaftar") figure per saluran — never invent one.
6. IGNORE diagonal watermarks ("DRAFT", "JPRP") and footer text.
7. Copy every number verbatim. Never compute, estimate, or invent.
7b. "jumlah" MUST be transcribed from the sheet's own printed bold JUMLAH
   (grand-total) row — NOT summed from the rows you returned. It is used as an
   INDEPENDENT cross-check against those rows, so a computed total defeats it.
   If the sheet prints no JUMLAH row, omit "jumlah" entirely rather than
   synthesising one. Likewise "saluran_count" is the saluran COUNT printed on
   that JUMLAH row — omit it if not printed.
7c. Return EVERY saluran row on EVERY page of the document. A sheet has dozens
   (e.g. 40); returning only the first block, only the UNDI POS line, or a
   single seat-level summary row is a serious error that publishes a vote count
   short by orders of magnitude.
8. Read the seat from the header: "BAHAGIAN PILIHAN RAYA NEGERI : N.15 JUASSEH" ->
   kawasan_kod "N.15", kawasan_nama "JUASSEH". Kod DM "129 / 15 / 01" encodes
   Parlimen 129 / DUN 15 / DM 01 -> parlimen_kod "129".

Return ONLY this JSON:
{"negeri":str,"kawasan_kod":str,"kawasan_nama":str,"parlimen_kod":str|null,
 "jumlah_pemilih":int,
 "calon":[{"nama":str,"parti_tekaan":str|null,"yakin":bool}],
 "rows":[{"dm_kod":str|null,"dm":str|null,"pusat":str,"saluran":str,
          "a":int,"undi":[int],"jumlah_undian":int,"ditolak":int,"tidak_dimasukkan":int}],
 "jumlah":{"a":int,"undi":[int],"jumlah_undian":int,"ditolak":int,"tidak_dimasukkan":int},
 "saluran_count":int|null}
TXT;

    /**
     * Baca scoresheet dengan mengekalkan Pusat Mengundi + Saluran.
     * Guna semula penghantaran media milik extract() — PDF/imej dihantar native ke Claude.
     */
    public function extractDetailed(UploadedFile $file): array
    {
        // Laluan pantas: Borang SPR 760 bertaip (bukan imbasan) boleh dibaca
        // TEPAT tanpa AI — percuma, serta-merta, boleh diuji dalam CI, dan tiada
        // peluang nombor direka. Parser memulangkan null (bukan bacaan separa)
        // untuk sebarang fail lain, jadi Claude kekal sebagai sandaran penuh.
        if ($this->isPdf($file)) {
            $deterministic = Spr760Parser::detailed($file->getRealPath());
            if ($deterministic !== null) {
                return ['ok' => true, 'data' => $deterministic + ['source' => 'deterministic'], 'error' => null];
            }
        }

        $content = $this->buildContentBlocks($file);   // kaedah sedia ada yang dipakai extract()
        if ($content === null) {
            return ['ok' => false, 'data' => null, 'error' => 'Format fail tidak disokong.'];
        }

        $res = $this->claude->chat(
            self::SYSTEM_DETAILED,
            $content,
            maxTokens: 8000,
            timeout: 180,
            context: 'scoresheet_extract_detailed',
            model: $this->claude->documentModel(),
        );

        if (! ($res['ok'] ?? false)) {
            return ['ok' => false, 'data' => null, 'error' => $res['error'] ?? 'Bacaan AI gagal.'];
        }

        $data = $this->claude->extractJson($res['content'] ?? '');
        if (! is_array($data) || empty($data['rows'])) {
            return ['ok' => false, 'data' => null, 'error' => 'AI tidak memulangkan baris yang sah.'];
        }

        return ['ok' => true, 'data' => $data, 'error' => null];
    }

    /**
     * Silang-semak setiap baris (dan baris JUMLAH/grand-total) terhadap TIGA peraturan:
     *
     *   - calon_count:    count(undi) == count(calon) — `undi` ialah ARRAY POSISI yang
     *                      MESTI selaras 1-ke-1 dengan senarai `calon`; ketidakpadanan
     *                      bilangan ialah tanda pasti lajur calon tersalah jajar/hilang.
     *   - jumlah_undian:  jumlah_undian == sum(undi) — jumlah setiap baris mesti sama
     *                      dengan jumlah nilai undi bagi baris itu.
     *   - balance:        (A) == sum(undi) + ditolak + tidak_dimasukkan.
     *                      Disahkan pada sheet Juasseh sebenar: 4471+4549+87+15 == 9122.
     *
     * @return array<int, array{rule:string, index:int|string, pusat:string, saluran:string,
     *                           jangka?:int, dapat?:int, expected?:int, actual?:int}>
     */
    public static function validateBalance(array $data): array
    {
        $bad = [];
        $expectedCalon = count($data['calon'] ?? []);

        $check = function ($r, $index) use (&$bad, $expectedCalon) {
            $undi = $r['undi'] ?? [];
            $pusat = (string) ($r['pusat'] ?? '');
            $saluran = (string) ($r['saluran'] ?? '');

            $actualCalon = count($undi);
            if ($actualCalon !== $expectedCalon) {
                $bad[] = [
                    'rule' => 'calon_count',
                    'index' => $index,
                    'pusat' => $pusat,
                    'saluran' => $saluran,
                    'expected' => $expectedCalon,
                    'actual' => $actualCalon,
                ];
            }

            $sumUndi = array_sum($undi);
            $jumlahUndian = (int) ($r['jumlah_undian'] ?? 0);
            if ($jumlahUndian !== $sumUndi) {
                $bad[] = [
                    'rule' => 'jumlah_undian',
                    'index' => $index,
                    'pusat' => $pusat,
                    'saluran' => $saluran,
                    'jangka' => $sumUndi,
                    'dapat' => $jumlahUndian,
                ];
            }

            $jangka = $sumUndi + (int) ($r['ditolak'] ?? 0) + (int) ($r['tidak_dimasukkan'] ?? 0);
            $dapat = (int) ($r['a'] ?? 0);
            if ($jangka !== $dapat) {
                $bad[] = [
                    'rule' => 'balance',
                    'index' => $index,
                    'pusat' => $pusat,
                    'saluran' => $saluran,
                    'jangka' => $jangka,
                    'dapat' => $dapat,
                ];
            }
        };

        foreach (($data['rows'] ?? []) as $i => $r) {
            $check($r, $i);
        }

        if (! empty($data['jumlah'])) {
            $check($data['jumlah'], 'jumlah');
        }

        return $bad;
    }

    /**
     * Silang-semak JUMLAH baris terhadap baris JUMLAH YANG DICETAK pada sheet.
     *
     * validateBalance() hanya menyemak setiap baris terhadap DIRINYA SENDIRI, jadi
     * satu ekstrak yang memulangkan HANYA baris UNDI POS lulus semua semakannya
     * — itulah yang berlaku di produksi: Keyin menerbitkan PN 98 / BN 73 sedangkan
     * baris JUMLAH bercetak berbunyi 4,471 / 4,549 (kurang ~98%). Baris JUMLAH
     * pada sheet ialah satu-satunya rujukan bebas yang ada, jadi ia digunakan di
     * sini sebagai pihak berkuasa: `jangka` = angka BERCETAK, `dapat` = hasil
     * campuran baris yang diekstrak.
     *
     * Sheet tanpa baris JUMLAH bercetak tidak menghasilkan sebarang penemuan —
     * ketiadaan rujukan bukan bukti percanggahan.
     *
     * @return array<int, array{rule:string, index:string, pusat:string, saluran:string,
     *                          calon?:int, jangka:int, dapat:int}>
     */
    public static function reconcileTotals(array $data): array
    {
        $printed = $data['jumlah'] ?? null;
        $rows = $data['rows'] ?? [];
        if (! is_array($printed) || $printed === [] || $rows === []) {
            return [];
        }

        $bad = [];
        $flag = function (string $rule, int $jangka, int $dapat, ?int $calon = null) use (&$bad) {
            if ($jangka !== $dapat) {
                $bad[] = array_filter([
                    'rule' => $rule,
                    'index' => 'jumlah',
                    'pusat' => '',
                    'saluran' => 'JUMLAH',
                    'calon' => $calon,
                    'jangka' => $jangka,
                    'dapat' => $dapat,
                ], fn ($v) => $v !== null);
            }
        };

        // Setiap lajur calon diperiksa SECARA BERASINGAN: dua lajur yang tertukar
        // menghasilkan jumlah keseluruhan yang sama, jadi jumlah besar sahaja
        // tidak akan mengesannya.
        foreach (($printed['undi'] ?? []) as $i => $undi) {
            $flag('jumlah_undi', (int) $undi, (int) collect($rows)->sum(fn ($r) => (int) ($r['undi'][$i] ?? 0)), $i + 1);
        }

        foreach (['a', 'jumlah_undian', 'ditolak', 'tidak_dimasukkan'] as $key) {
            if (isset($printed[$key])) {
                $flag('jumlah_'.$key, (int) $printed[$key], (int) collect($rows)->sum(fn ($r) => (int) ($r[$key] ?? 0)));
            }
        }

        // Bilangan saluran bercetak — semakan yang paling terus bagi ekstrak yang
        // memulangkan segelintir baris sahaja daripada keseluruhan sheet.
        $salurCount = $data['saluran_count'] ?? null;
        if (is_numeric($salurCount)) {
            $flag('saluran_count', (int) $salurCount, count($rows));
        }

        return $bad;
    }
}
