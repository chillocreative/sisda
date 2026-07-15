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

        $today = now()->translatedFormat('d F Y');
        $factsJson = json_encode($facts, JSON_UNESCAPED_UNICODE);

        // ── Phase 1: research + narrative WITH live web search ──────────────
        // Tool-using models (incl. Haiku) write prose reliably but emit clean
        // JSON poorly while interleaving tool calls, so we do NOT ask for JSON
        // here — just a thorough BM analysis grounded in the supplied facts.
        $p1system = self::PERSONA
            .'Gunakan alat web_search (maksimum 5 carian) untuk mengkaji konteks politik Malaysia/Johor pada masa setiap '
            ."`tarikh` senario (landskap gabungan, peristiwa nasional seperti PRU-14 2018, Langkah Sheraton 2020, PRN Johor 2022, "
            ."PRU-15, Undi18 / pendaftaran automatik, keadaan ekonomi) DAN keadaan sekarang ({$today}). Guna carian HANYA untuk "
            .'konteks kualitatif dan hujah sebab-akibat — jangan sekali-kali untuk angka tentang kerusi ini. '
            .'Parti yang bertanding berbeza mengikut senario dan diberikan dalam medan `parti` serta peta `undi`/`peratus_undi` '
            .'setiap senario (dibaca daripada scoresheet sebenar) — rujuk parti mengikut nama sebenar; jangan andaikan barisan '
            .'PH/BN/PN tetap. Tulis analisis profesional yang lengkap dalam Bahasa Malaysia formal, meliputi dengan jelas: '
            .'(1) pertumbuhan pengundi baru berbanding pengundi lama, (2) peratus pengundi muda, (3) pecahan mengikut saluran, '
            .'(4) faktor-faktor KENAPA keputusan dan komposisi pengundi berubah (hujah bernas, sertakan sumber carian web), '
            .'dan (5) kesimpulan. Setiap angka mesti diambil terus daripada fakta yang diberi.';

        $p1 = $this->claude->chatWithWebSearch($p1system, $factsJson, 6000, 180, 'analisa_comparison', 5);
        $searches = (int) ($p1['searches'] ?? 0);
        $citations = $p1['citations'] ?? [];

        $parsed = null;
        if ($p1['ok'] && trim((string) $p1['content']) !== '') {
            // ── Phase 2: structure the narrative into strict JSON (no tools) ──
            $p2system = 'Anda menyusun analisis pilihanraya kepada JSON yang tepat. Balas HANYA dengan satu objek JSON, '
                .'tanpa sebarang teks lain, pembuka, atau pagar kod, mengikut skema INI dengan tepat: '
                .'{"tajuk":"string","ringkasan_eksekutif":"string",'
                .'"pengundi_baru_lama":{"analisis":"string","bullet_points":["string"]},'
                .'"pengundi_muda":{"analisis":"string","bullet_points":["string"]},'
                .'"saluran":{"analisis":"string","bullet_points":["string"]},'
                .'"perbandingan_senario":[{"label":"string","tahun":"string","sorotan":"string"}],'
                .'"faktor_perubahan":[{"tajuk":"string","hujah":"string","sumber":"string atau null"}],'
                .'"kesimpulan":"string","rujukan":[{"tajuk":"string","url":"string"}]}. '
                .'Kekalkan semua kandungan dan SEMUA angka daripada draf/fakta; `faktor_perubahan` maksimum 6 faktor; '
                .'semua teks dalam Bahasa Malaysia formal.';
            $p2user = "FAKTA (angka rasmi — jangan ubah):\n{$factsJson}\n\nDRAF ANALISIS:\n"
                .$p1['content']
                ."\n\nRUJUKAN (URL carian web):\n".json_encode(array_values($citations), JSON_UNESCAPED_UNICODE);

            $p2 = $this->claude->chat($p2system, $p2user, 6000, 120, 'analisa_comparison_format');
            if ($p2['ok']) {
                $parsed = self::sanitizeComparison($this->claude->extractJson($p2['content']), $citations);
            }
            // Fallback attempt: maybe phase 1 already contained a JSON object.
            if (! $this->isMeaningful($parsed)) {
                $alt = self::sanitizeComparison($this->claude->extractJson($p1['content']), $citations);
                $parsed = $this->isMeaningful($alt) ? $alt : $parsed;
            }
        }

        if ($this->isMeaningful($parsed)) {
            $comparison->fill([
                'fact_payload' => $facts,
                'ai_result' => $parsed,
                'ai_status' => 'ok',
                'ai_model' => ClaudeSetting::current()?->model,
                'ai_generated_at' => now(),
                'web_search_count' => $searches,
                'status' => 'analyzed',
            ])->save();

            return ['status' => 'ok', 'result' => $parsed, 'facts' => $facts, 'generated_at' => $comparison->ai_generated_at->toIso8601String()];
        }

        Log::warning('Analisa comparison fell back to deterministic report', ['error' => $p1['error'] ?? 'parse_failed', 'searches' => $searches]);
        $fallback = $this->fallbackReport($facts);
        $comparison->fill([
            'fact_payload' => $facts,
            'ai_result' => $fallback,
            'ai_status' => 'fallback',
            'ai_model' => ClaudeSetting::current()?->model,
            'ai_generated_at' => now(),
            'web_search_count' => $searches,
            'status' => 'analyzed',
        ])->save();

        return ['status' => 'fallback', 'result' => $fallback, 'facts' => $facts, 'generated_at' => $comparison->ai_generated_at->toIso8601String()];
    }

    /** A parsed AI result is only "real" if it carries actual narrative content. */
    private function isMeaningful(?array $r): bool
    {
        if (! $r) {
            return false;
        }

        return trim((string) ($r['ringkasan_eksekutif'] ?? '')) !== ''
            || ! empty($r['faktor_perubahan'])
            || trim((string) ($r['pengundi_baru_lama']['analisis'] ?? '')) !== '';
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
                'negeri' => $comparison->negeri,
                'parlimen' => $comparison->parlimen,
                'dun' => $comparison->dun,
                'level' => $comparison->level,
                'nama' => $comparison->level === 'dun' ? $comparison->dun : $comparison->parlimen,
            ],
            'tarikh_analisis' => now()->format('Y-m-d'),
            'senario' => $summaries,
            'perubahan' => $this->deltas($summaries),
            'roll_semasa' => $this->currentRoll($comparison),
            'saluran_semasa' => $this->currentSaluran($comparison),
        ];
    }

    /**
     * Derived per-scenario summary — party-agnostic. Parties come from the
     * scenario's own `undi` map (detected from the uploaded sheet), so any set
     * of contesting parties is supported.
     */
    private function scenarioSummary($scenario): array
    {
        $t = $scenario->parsed_totals ?? [];
        $rows = $scenario->parsed_rows ?? [];

        $undi = collect($t['undi'] ?? [])->map(fn ($v) => (int) $v)->all();
        $parties = ! empty($t['parties']) ? array_values($t['parties']) : array_keys($undi);

        $pemilih = (float) ($t['pemilih'] ?? 0);
        $ditolak = (float) ($t['ditolak'] ?? 0);
        $keluar = (float) ($t['keluar'] ?? 0);
        if ($keluar <= 0) {
            $keluar = array_sum($undi) + $ditolak;
        }

        $share = fn ($v) => $keluar > 0 ? round($v / $keluar * 100, 1) : 0;
        $peratus = [];
        foreach ($parties as $p) {
            $peratus[$p] = $share((int) ($undi[$p] ?? 0));
        }

        $ranked = $undi;
        arsort($ranked);
        $rankedKeys = array_keys($ranked);
        $rankedVals = array_values($ranked);

        // Cap oversized sheets in the AI payload (totals stay exact).
        $truncated = count($rows) > 40;
        $dmRows = collect($rows)
            ->sortByDesc(fn ($r) => (float) ($r['keluar'] ?? array_sum($r['undi'] ?? [])))
            ->take($truncated ? 15 : 100)
            ->map(fn ($r) => [
                'kawasan' => $r['kawasan'] ?? ($r['dm'] ?? ''),
                'pemilih' => (int) ($r['pemilih'] ?? 0),
                'keluar' => (int) ($r['keluar'] ?? 0),
                'undi' => collect($r['undi'] ?? [])->map(fn ($v) => (int) $v)->all(),
            ])->values()->all();

        return [
            'label' => $scenario->label,
            'tarikh' => $scenario->election_date?->format('Y-m-d'),
            'tahun' => $scenario->election_date?->format('Y'),
            'parti' => $parties,
            'pemilih_berdaftar' => (int) $pemilih,
            'undi_keluar' => (int) $keluar,
            'peratus_keluar' => $pemilih > 0 ? round($keluar / $pemilih * 100, 1) : null,
            'undi' => $undi,
            'peratus_undi' => $peratus,
            'pemenang' => $rankedKeys[0] ?? null,
            'majoriti' => (int) round(($rankedVals[0] ?? 0) - ($rankedVals[1] ?? 0)),
            'kawasan' => $dmRows,
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

            $parties = array_values(array_unique(array_merge($a['parti'] ?? [], $b['parti'] ?? [])));
            $ayun = [];
            foreach ($parties as $p) {
                $ayun[$p] = round(($b['peratus_undi'][$p] ?? 0) - ($a['peratus_undi'][$p] ?? 0), 1);
            }

            $out[] = [
                'dari' => $a['label'],
                'ke' => $b['label'],
                'perubahan_pemilih' => $dPemilih,
                'perubahan_pemilih_pct' => $a['pemilih_berdaftar'] > 0 ? round($dPemilih / $a['pemilih_berdaftar'] * 100, 1) : null,
                'perubahan_peratus_keluar' => ($a['peratus_keluar'] !== null && $b['peratus_keluar'] !== null)
                    ? round($b['peratus_keluar'] - $a['peratus_keluar'], 1) : null,
                'ayunan_undi' => $ayun,
            ];
        }

        return $out;
    }

    /**
     * Current electorate metrics for the kawasan — new vs old voters, youth
     * share, age bands — from the live roll union (active batches OR DPT
     * uploads). Scoped to the DUN (kadun) or the whole Parlimen.
     */
    private function currentRoll(AnalisaComparison $comparison): array
    {
        $ageExpr = $this->analytics->rollAgeExpr();
        $guard = $this->analytics->rollAgeGuard();
        $activeIds = UploadBatch::activeIds();
        $scope = $this->rollScope($comparison);

        $base = fn () => $scope(DB::table('pangkalan_data_pengundi')->where('is_deceased', false))
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

    /**
     * Saluran (voting-stream) registered-voter breakdown for the kawasan — the
     * DUN's Borang 14 reference, or the union across every DUN in the Parlimen.
     */
    private function currentSaluran(AnalisaComparison $comparison): array
    {
        $kadunIds = $comparison->level === 'dun'
            ? array_filter([$comparison->kadun_id])
            : Kadun::where('bandar_id', $comparison->bandar_id)->pluck('id')->all();

        $flat = [];
        $total = 0;
        $estimate = false;
        foreach ($kadunIds as $kid) {
            $ref = Borang14Reference::forKadun((int) $kid);
            if (! $ref) {
                continue;
            }
            if (($ref['source'] ?? '') === 'dpt_estimate') {
                $estimate = true;
            }
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
        }

        if ($total === 0) {
            return ['tersedia' => false, 'sumber' => null, 'jumlah_saluran' => 0, 'jumlah_berdaftar' => 0, 'saluran' => []];
        }

        foreach ($flat as &$s) {
            $s['pct'] = round($s['berdaftar'] / $total * 100, 1);
        }
        unset($s);

        // Keep the payload compact — 30 largest saluran + the total.
        usort($flat, fn ($a, $b) => $b['berdaftar'] <=> $a['berdaftar']);

        return [
            'tersedia' => true,
            'sumber' => $estimate ? 'dpt_estimate' : 'gazet',
            'jumlah_saluran' => count($flat),
            'jumlah_berdaftar' => $total,
            'saluran' => array_slice($flat, 0, 30),
        ];
    }

    /** Roll-query scope predicate for the comparison's level (DUN or Parlimen). */
    private function rollScope(AnalisaComparison $comparison): \Closure
    {
        if ($comparison->level === 'dun') {
            $upper = mb_strtoupper((string) $comparison->dun);

            return fn ($q) => $q->whereRaw('UPPER(kadun) = ?', [$upper]);
        }

        $upper = mb_strtoupper((string) $comparison->parlimen);

        return fn ($q) => $q->whereRaw('UPPER(parlimen) LIKE ?', ['%'.$upper.'%']);
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
            'tajuk' => 'Perbandingan Senario Pilihanraya — '.($facts['kawasan']['nama'] ?? ''),
            'ringkasan_eksekutif' => 'Ringkasan berangka dijana secara automatik. Analisis naratif AI tidak dapat dijana '
                .'buat masa ini (sila semak Tetapan Claude AI). Semua angka diambil terus daripada scoresheet yang dimuat '
                .'naik dan pangkalan data pengundi semasa.',
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
            'kesimpulan' => 'Analisis naratif AI tidak dapat dijana — hanya ringkasan berangka dipaparkan.',
            'rujukan' => [],
        ];
    }
}
