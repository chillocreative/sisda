<?php

namespace App\Services\Pilihanraya;

use App\Models\AnalisaComparison;
use App\Models\ClaudeSetting;
use App\Models\Kadun;
use App\Models\UploadBatch;
use App\Services\ClaudeService;
use App\Support\Borang14Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the AI comparison of 1–3 election scenarios on the Analisa
 * Keputusan page.
 *
 * Follows the ElectionForecastService pattern: every NUMBER the report shows
 * is computed server-side into fact_payload (ground truth) — the AI is told,
 * explicitly, never to invent figures. The AI uses live web_search only for
 * qualitative political context and causal argument, and its prose output is
 * sanitised before it is trusted. A deterministic BM fallback guarantees the
 * UI/PDF always render even when Claude is disabled or errors.
 */
class ElectionComparisonService
{
    private const PERSONA = 'You are a senior Malaysian election analyst and psephologist writing for SISDA, '
        .'a voter-intelligence system. You compare official election results (scoresheets) across time for a '
        .'single state seat (DUN). '
        .'CRITICAL LANGUAGE RULE: every piece of human-readable text you output MUST be written entirely in formal '
        .'Bahasa Malaysia. Do NOT use English words or sentences. NEVER write raw JSON field names in your prose. '
        .'CRITICAL NUMBER RULE: every number in your output MUST come verbatim from the supplied `fakta` payload, or '
        .'be a simple percentage/difference derivable from two supplied numbers. NEVER estimate, recall, or search the '
        .'web for vote counts, electorate sizes, turnout, or any figure about this seat — the payload is the sole '
        .'numeric ground truth. ';

    public function __construct(
        protected ClaudeService $claude,
        protected ElectionAnalyticsService $analytics,
    ) {}

    /* ----------------------------------------------------------------
     |  Public entry point
     * ---------------------------------------------------------------- */

    public function analyze(AnalisaComparison $comparison, int $userId): array
    {
        $comparison->loadMissing('scenarios');
        $facts = $this->buildFactPayload($comparison);
        $comparison->fact_payload = $facts;

        $today = now()->translatedFormat('d F Y');
        $system = self::PERSONA
            .'Use the web_search tool (maximum 5 searches) to research the political context of Malaysia/Johor around '
            ."each scenario's `tarikh` (coalition landscape, national events such as PRU-14 2018, Langkah Sheraton 2020, "
            .'PRN Johor 2022, PRU-15, Undi18 / automatic voter registration, economic conditions) AND the situation now '
            ."({$today}). Use search ONLY for qualitative context and causal argument — never for numbers about this seat. "
            .'Respond ONLY with a JSON object matching exactly this schema: '
            .'{"tajuk":"string","ringkasan_eksekutif":"string",'
            .'"pengundi_baru_lama":{"analisis":"string","bullet_points":["string"]},'
            .'"pengundi_muda":{"analisis":"string","bullet_points":["string"]},'
            .'"saluran":{"analisis":"string","bullet_points":["string"]},'
            .'"perbandingan_senario":[{"label":"string","tahun":"string","sorotan":"string"}],'
            .'"faktor_perubahan":[{"tajuk":"string","hujah":"string","sumber":"string atau null"}],'
            .'"kesimpulan":"string","rujukan":[{"tajuk":"string","url":"string"}]}. '
            .'`faktor_perubahan` is your professional, well-argued explanation of WHY the results/electorate changed '
            .'(maximum 6 factors, each a titled argument with an optional cited source). All prose in Bahasa Malaysia.';

        $result = $this->claude->chatWithWebSearch(
            $system,
            json_encode($facts, JSON_UNESCAPED_UNICODE),
            6000,
            180,
            'analisa_comparison',
            5,
        );

        if ($result['ok']) {
            $parsed = self::sanitizeComparison($this->claude->extractJson($result['content']), $result['citations'] ?? []);
            if ($parsed) {
                $comparison->fill([
                    'fact_payload' => $facts,
                    'ai_result' => $parsed,
                    'ai_status' => 'ok',
                    'ai_model' => ClaudeSetting::current()?->model,
                    'ai_generated_at' => now(),
                    'web_search_count' => (int) ($result['searches'] ?? 0),
                    'status' => 'analyzed',
                ])->save();

                return ['status' => 'ok', 'result' => $parsed, 'facts' => $facts, 'generated_at' => $comparison->ai_generated_at->toIso8601String()];
            }
        }

        Log::warning('Analisa comparison fell back to deterministic report', ['error' => $result['error'] ?? 'parse_failed']);
        $fallback = $this->fallbackReport($facts);
        $comparison->fill([
            'fact_payload' => $facts,
            'ai_result' => $fallback,
            'ai_status' => 'fallback',
            'ai_model' => ClaudeSetting::current()?->model,
            'ai_generated_at' => now(),
            'web_search_count' => (int) ($result['searches'] ?? 0),
            'status' => 'analyzed',
        ])->save();

        return ['status' => 'fallback', 'result' => $fallback, 'facts' => $facts, 'generated_at' => $comparison->ai_generated_at->toIso8601String()];
    }

    /* ----------------------------------------------------------------
     |  Ground-truth payload (numbers the AI must not invent)
     * ---------------------------------------------------------------- */

    public function buildFactPayload(AnalisaComparison $comparison): array
    {
        $scenarios = $comparison->scenarios
            ->sortBy(fn ($s) => $s->election_date?->timestamp ?? 0)
            ->values();

        $summaries = $scenarios->map(fn ($s) => $this->scenarioSummary($s))->all();

        return [
            'kawasan' => [
                'dun' => $comparison->dun,
                'parlimen' => $comparison->parlimen,
            ],
            'tarikh_analisis' => now()->format('Y-m-d'),
            'senario' => $summaries,
            'perubahan' => $this->deltas($summaries),
            'roll_semasa' => $this->currentRoll($comparison->dun),
            'saluran_semasa' => $this->currentSaluran($comparison->dun),
        ];
    }

    /** Derived per-scenario summary — all figures from the persisted totals. */
    private function scenarioSummary($scenario): array
    {
        $t = $scenario->parsed_totals ?? [];
        $rows = $scenario->parsed_rows ?? [];

        $pemilih = (float) ($t['pemilih'] ?? 0);
        $keluar = (float) ($t['keluar'] ?? 0);
        $ph = (float) ($t['ph'] ?? 0);
        $bn = (float) ($t['bn'] ?? 0);
        $pn = (float) ($t['pn'] ?? 0);
        $pejuang = (float) ($t['pejuang'] ?? 0);
        $ditolak = (float) ($t['ditolak'] ?? 0);

        $share = fn ($v) => $keluar > 0 ? round($v / $keluar * 100, 1) : 0;

        $parties = ['PH' => $ph, 'BN' => $bn, 'PN' => $pn, 'PEJUANG' => $pejuang];
        arsort($parties);
        $ranked = array_keys($parties);
        $vals = array_values($parties);

        // Cap oversized sheets in the AI payload (totals stay exact).
        $truncated = count($rows) > 40;
        $dmRows = collect($rows)
            ->sortByDesc(fn ($r) => (float) ($r['keluar'] ?? 0))
            ->take($truncated ? 15 : 100)
            ->map(fn ($r) => [
                'dm' => $r['dm'] ?? '',
                'pemilih' => (int) ($r['pemilih'] ?? 0),
                'keluar' => (int) ($r['keluar'] ?? 0),
                'ph' => (int) ($r['ph'] ?? 0),
                'bn' => (int) ($r['bn'] ?? 0),
                'pn' => (int) ($r['pn'] ?? 0),
            ])->values()->all();

        return [
            'label' => $scenario->label,
            'tarikh' => $scenario->election_date?->format('Y-m-d'),
            'tahun' => $scenario->election_date?->format('Y'),
            'pemilih_berdaftar' => (int) $pemilih,
            'undi_keluar' => (int) $keluar,
            'peratus_keluar' => $pemilih > 0 ? round($keluar / $pemilih * 100, 1) : null,
            'undi' => ['ph' => (int) $ph, 'bn' => (int) $bn, 'pn' => (int) $pn, 'pejuang' => (int) $pejuang, 'ditolak' => (int) $ditolak],
            'peratus_undi' => ['ph' => $share($ph), 'bn' => $share($bn), 'pn' => $share($pn), 'pejuang' => $share($pejuang)],
            'pemenang' => $ranked[0] ?? null,
            'majoriti' => (int) round(($vals[0] ?? 0) - ($vals[1] ?? 0)),
            'daerah_mengundi' => $dmRows,
            'rows_truncated' => $truncated,
        ];
    }

    /** Changes between consecutive (chronological) scenarios. */
    private function deltas(array $summaries): array
    {
        $out = [];
        for ($i = 1; $i < count($summaries); $i++) {
            $a = $summaries[$i - 1];
            $b = $summaries[$i];
            $dPemilih = $b['pemilih_berdaftar'] - $a['pemilih_berdaftar'];
            $out[] = [
                'dari' => $a['label'],
                'ke' => $b['label'],
                'perubahan_pemilih' => $dPemilih,
                'perubahan_pemilih_pct' => $a['pemilih_berdaftar'] > 0 ? round($dPemilih / $a['pemilih_berdaftar'] * 100, 1) : null,
                'perubahan_peratus_keluar' => ($a['peratus_keluar'] !== null && $b['peratus_keluar'] !== null)
                    ? round($b['peratus_keluar'] - $a['peratus_keluar'], 1) : null,
                'ayunan_undi' => [
                    'ph' => round($b['peratus_undi']['ph'] - $a['peratus_undi']['ph'], 1),
                    'bn' => round($b['peratus_undi']['bn'] - $a['peratus_undi']['bn'], 1),
                    'pn' => round($b['peratus_undi']['pn'] - $a['peratus_undi']['pn'], 1),
                ],
            ];
        }

        return $out;
    }

    /**
     * Current electorate metrics for the DUN — new vs old voters, youth share,
     * age bands — from the live roll union (active batches OR DPT uploads).
     */
    private function currentRoll(string $dun): array
    {
        $kadun = $this->resolveKadun($dun);
        $kadunName = $kadun?->nama ?? $this->stripCode($dun);

        $ageExpr = $this->analytics->rollAgeExpr();
        $guard = $this->analytics->rollAgeGuard();
        $activeIds = UploadBatch::activeIds();

        $base = fn () => DB::table('pangkalan_data_pengundi')
            ->where('is_deceased', false)
            ->whereRaw('UPPER(kadun) = ?', [mb_strtoupper($kadunName)])
            ->where(function ($w) use ($activeIds) {
                $w->whereIn('upload_batch_id', $activeIds ?: [-1])->orWhereNotNull('dpt_upload_id');
            });

        $bandSelects = [];
        foreach (ElectionAnalyticsService::AGE_BANDS as $i => $band) {
            $bandSelects[] = "COALESCE(SUM({$guard} AND {$ageExpr} BETWEEN {$band['min']} AND {$band['max']}), 0) AS band_{$i}";
        }

        $row = $base()->selectRaw(
            'COUNT(*) AS jumlah,
             COALESCE(SUM(pendaftaran_baru = 1), 0) AS baru,
             COALESCE(SUM('.$guard.' AND '.$ageExpr.' BETWEEN 18 AND 29), 0) AS muda_18_29,
             '.implode(', ', $bandSelects)
        )->first();

        $jumlah = (int) ($row->jumlah ?? 0);
        $baru = (int) ($row->baru ?? 0);
        $muda = (int) ($row->muda_18_29 ?? 0);

        $bands = [];
        foreach (ElectionAnalyticsService::AGE_BANDS as $i => $band) {
            $bands[] = ['band' => $band['label'], 'jumlah' => (int) ($row->{"band_{$i}"} ?? 0)];
        }

        return [
            'tersedia' => $jumlah > 0,
            'jumlah' => $jumlah,
            'baru' => $baru,
            'lama' => max(0, $jumlah - $baru),
            'pct_baru' => $jumlah > 0 ? round($baru / $jumlah * 100, 1) : 0,
            'muda_18_29' => $muda,
            'pct_muda' => $jumlah > 0 ? round($muda / $jumlah * 100, 1) : 0,
            'jalur_umur' => $bands,
        ];
    }

    /** Saluran (voting-stream) registered-voter breakdown for the DUN. */
    private function currentSaluran(string $dun): array
    {
        $kadun = $this->resolveKadun($dun);
        if (! $kadun) {
            return ['tersedia' => false, 'sumber' => null, 'jumlah_berdaftar' => 0, 'saluran' => []];
        }

        $ref = Borang14Reference::forKadun($kadun->id);
        if (! $ref) {
            return ['tersedia' => false, 'sumber' => null, 'jumlah_berdaftar' => 0, 'saluran' => []];
        }

        $flat = [];
        $total = 0;
        foreach ($ref['daerah_mengundi'] ?? [] as $dm) {
            foreach ($dm['pusat_mengundi'] ?? [] as $pusat) {
                foreach ($pusat['saluran'] ?? [] as $sal) {
                    $berdaftar = (int) ($sal['berdaftar'] ?? 0);
                    $total += $berdaftar;
                    $flat[] = [
                        'dm' => $dm['nama'] ?? '',
                        'pusat' => $pusat['nama'] ?? '',
                        'saluran' => 'Saluran '.($sal['no'] ?? 1),
                        'berdaftar' => $berdaftar,
                    ];
                }
            }
        }

        foreach ($flat as &$s) {
            $s['pct'] = $total > 0 ? round($s['berdaftar'] / $total * 100, 1) : 0;
        }
        unset($s);

        // Keep the payload compact — 30 largest saluran + the total.
        usort($flat, fn ($a, $b) => $b['berdaftar'] <=> $a['berdaftar']);

        return [
            'tersedia' => $total > 0,
            'sumber' => $ref['source'] ?? 'gazet',
            'jumlah_saluran' => count($flat),
            'jumlah_berdaftar' => $total,
            'saluran' => array_slice($flat, 0, 30),
        ];
    }

    private function resolveKadun(string $dun): ?Kadun
    {
        $name = $this->stripCode($dun);

        return Kadun::whereRaw('UPPER(nama) = ?', [mb_strtoupper($name)])->first()
            ?? Kadun::whereRaw('UPPER(nama) = ?', [mb_strtoupper($dun)])->first();
    }

    /** Strip a leading DUN code ("N01 BULOH KASAP" → "BULOH KASAP"). */
    private function stripCode(string $dun): string
    {
        return trim(preg_replace('/^[A-Z]\d+\s+/i', '', $dun));
    }

    /* ----------------------------------------------------------------
     |  Sanitiser + deterministic fallback
     * ---------------------------------------------------------------- */

    /**
     * Force AI output into the exact renderable shape (strings stay strings,
     * lists stay sequential, lengths capped), and merge server-collected
     * citations into `rujukan` deduped by URL. Returns null if unrenderable.
     */
    public static function sanitizeComparison(?array $r, array $citations = []): ?array
    {
        if (! $r) {
            return null;
        }

        $str = fn ($v) => is_scalar($v) ? (string) $v : '';
        $strList = fn ($v, $max = 8) => collect(is_array($v) ? $v : [])
            ->map($str)->filter(fn ($s) => $s !== '')->take($max)->values()->all();

        $section = fn ($s) => [
            'analisis' => $str(is_array($s) ? ($s['analisis'] ?? '') : $s),
            'bullet_points' => $strList(is_array($s) ? ($s['bullet_points'] ?? []) : []),
        ];

        $faktor = collect(is_array($r['faktor_perubahan'] ?? null) ? $r['faktor_perubahan'] : [])
            ->filter(fn ($f) => is_array($f))
            ->map(fn ($f) => [
                'tajuk' => $str($f['tajuk'] ?? ''),
                'hujah' => $str($f['hujah'] ?? ''),
                'sumber' => $str($f['sumber'] ?? ''),
            ])
            ->filter(fn ($f) => $f['tajuk'] !== '' || $f['hujah'] !== '')
            ->take(6)->values()->all();

        $perbandingan = collect(is_array($r['perbandingan_senario'] ?? null) ? $r['perbandingan_senario'] : [])
            ->filter(fn ($s) => is_array($s))
            ->map(fn ($s) => [
                'label' => $str($s['label'] ?? ''),
                'tahun' => $str($s['tahun'] ?? ''),
                'sorotan' => $str($s['sorotan'] ?? ''),
            ])
            ->take(3)->values()->all();

        // Merge AI-cited references with the server-collected search citations.
        $refs = [];
        foreach (is_array($r['rujukan'] ?? null) ? $r['rujukan'] : [] as $ref) {
            if (is_array($ref) && ! empty($ref['url'])) {
                $refs[(string) $ref['url']] = ['tajuk' => $str($ref['tajuk'] ?? $ref['url']), 'url' => (string) $ref['url']];
            }
        }
        foreach ($citations as $c) {
            if (! empty($c['url']) && ! isset($refs[$c['url']])) {
                $refs[$c['url']] = ['tajuk' => $str($c['tajuk'] ?? $c['url']), 'url' => (string) $c['url']];
            }
        }

        return [
            'tajuk' => $str($r['tajuk'] ?? '') ?: 'Perbandingan Senario Pilihanraya',
            'ringkasan_eksekutif' => $str($r['ringkasan_eksekutif'] ?? ''),
            'pengundi_baru_lama' => $section($r['pengundi_baru_lama'] ?? []),
            'pengundi_muda' => $section($r['pengundi_muda'] ?? []),
            'saluran' => $section($r['saluran'] ?? []),
            'perbandingan_senario' => $perbandingan,
            'faktor_perubahan' => $faktor,
            'kesimpulan' => $str($r['kesimpulan'] ?? ''),
            'rujukan' => array_slice(array_values($refs), 0, 15),
        ];
    }

    /** Deterministic BM report built from the computed deltas — no AI needed. */
    private function fallbackReport(array $facts): array
    {
        $roll = $facts['roll_semasa'] ?? [];
        $sal = $facts['saluran_semasa'] ?? [];
        $deltas = $facts['perubahan'] ?? [];
        $n = fn ($v) => number_format((float) $v);

        $growthBullets = [];
        foreach ($deltas as $d) {
            $arah = $d['perubahan_pemilih'] >= 0 ? 'pertambahan' : 'pengurangan';
            $growthBullets[] = "Daripada {$d['dari']} ke {$d['ke']}: {$arah} ".$n(abs($d['perubahan_pemilih']))
                .' pengundi berdaftar'.($d['perubahan_pemilih_pct'] !== null ? " ({$d['perubahan_pemilih_pct']}%)" : '').'.';
        }

        return [
            'tajuk' => 'Perbandingan Senario Pilihanraya — '.($facts['kawasan']['dun'] ?? ''),
            'ringkasan_eksekutif' => 'Laporan ini dijana secara deterministik kerana AI tidak tersedia. Semua angka '
                .'diambil terus daripada scoresheet yang dimuat naik dan pangkalan data pengundi semasa. Aktifkan '
                .'Tetapan Claude AI untuk analisis naratif dan carian web penuh.',
            'pengundi_baru_lama' => [
                'analisis' => ($roll['tersedia'] ?? false)
                    ? 'Daftar pemilih semasa mempunyai '.$n($roll['jumlah']).' pengundi, di mana '.$n($roll['baru'])
                        .' ('.($roll['pct_baru'] ?? 0).'%) adalah pendaftaran baru dan '.$n($roll['lama']).' adalah pengundi sedia ada.'
                    : 'Tiada data pangkalan pengundi aktif untuk kawasan ini.',
                'bullet_points' => $growthBullets,
            ],
            'pengundi_muda' => [
                'analisis' => ($roll['tersedia'] ?? false)
                    ? 'Pengundi muda (18–29 tahun) berjumlah '.$n($roll['muda_18_29']).' orang ('.($roll['pct_muda'] ?? 0).'% daripada daftar).'
                    : 'Tiada data umur pengundi untuk kawasan ini.',
                'bullet_points' => [],
            ],
            'saluran' => [
                'analisis' => ($sal['tersedia'] ?? false)
                    ? 'Terdapat '.($sal['jumlah_saluran'] ?? 0).' saluran dengan jumlah '.$n($sal['jumlah_berdaftar'] ?? 0).' pengundi berdaftar.'
                        .(($sal['sumber'] ?? '') === 'dpt_estimate' ? ' (Anggaran daripada DPT — pecahan saluran rasmi belum tersedia.)' : '')
                    : 'Tiada data saluran untuk kawasan ini.',
                'bullet_points' => [],
            ],
            'perbandingan_senario' => collect($facts['senario'] ?? [])->map(fn ($s) => [
                'label' => (string) ($s['label'] ?? ''),
                'tahun' => (string) ($s['tahun'] ?? ''),
                'sorotan' => 'Pemenang: '.($s['pemenang'] ?? '—').', majoriti '.$n($s['majoriti'] ?? 0).' undi; peratus keluar '
                    .($s['peratus_keluar'] ?? '—').'%.',
            ])->take(3)->values()->all(),
            'faktor_perubahan' => [],
            'kesimpulan' => 'AI tidak tersedia — hanya ringkasan berangka deterministik dipaparkan.',
            'rujukan' => [],
        ];
    }
}
