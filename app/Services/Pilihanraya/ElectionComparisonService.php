<?php

namespace App\Services\Pilihanraya;

use App\Models\AnalisaComparison;
use App\Models\Bandar;
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
        .'numeric ground truth. '
        .'Jika `pemilih_berdaftar` sesuatu senario bernilai null dalam `fakta`, ini bermakna angka itu TIDAK TERSEDIA '
        .'dalam scoresheet rasmi — JANGAN sekali-kali membuat sebarang dakwaan tentang peratus keluar mengundi atau '
        .'perubahan bilangan pengundi berdaftar bagi senario tersebut. '
        // Senario keputusan rasmi SPR yang lama tiada pecahan per parti.
        // Tanpa arahan ini model akan membaca senarai parti yang kosong
        // sebagai "tiada undi" dan menulis kejatuhan yang tidak pernah berlaku.
        .'Jika `parti` sesuatu senario ialah senarai KOSONG, ini bermakna pecahan undi setiap parti bagi pilihan raya '
        .'itu TIDAK DIKETAHUI — ia BUKAN sifar undi. Jangan sekali-kali mendakwa mana-mana parti mendapat sifar undi, '
        .'hilang undi, atau tidak bertanding berdasarkan senarai kosong itu. Rujuk angka yang MEMANG diketahui bagi '
        .'senario tersebut (pemenang, majoriti, keluar mengundi, pengundi berdaftar). Begitu juga apabila '
        .'`ayunan_undi` bernilai null: ayunan itu tidak diketahui, bukan sifar. ';

    public function __construct(
        protected ClaudeService $claude,
        protected ElectionAnalyticsService $analytics,
        protected SeatBaselineService $baselines,
    ) {}

    /* ----------------------------------------------------------------
     |  Public entry point
     * ---------------------------------------------------------------- */

    public function analyze(AnalisaComparison $comparison, int $userId): array
    {
        try {
            return $this->runAnalysis($comparison, $userId);
        } catch (\Throwable $e) {
            // Any failure (incl. a slow/half-finished AI step) still yields the
            // deterministic report so the user gets a result, never a hard error.
            Log::error('Analisa comparison crashed — deterministic fallback', ['error' => $e->getMessage()]);
            $facts = $this->buildFactPayload($comparison);
            $fallback = $this->fallbackReport($facts);
            $comparison->fill([
                'fact_payload' => $facts,
                'ai_result' => $fallback,
                'ai_status' => 'fallback',
                'ai_model' => ClaudeSetting::current()?->model,
                'ai_generated_at' => now(),
                'web_search_count' => 0,
                'status' => 'analyzed',
            ])->save();

            return ['status' => 'fallback', 'result' => $fallback, 'facts' => $facts, 'generated_at' => $comparison->ai_generated_at->toIso8601String()];
        }
    }

    private function runAnalysis(AnalisaComparison $comparison, int $userId): array
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
            .'dan (5) kesimpulan. Setiap angka mesti diambil terus daripada fakta yang diberi. '
            // Sejarah rasmi kini tersedia secara tempatan. Tanpa ayat ini AI
            // masih akan mencari di web untuk keputusan lampau kerusi ini —
            // angka yang tidak boleh disemak, sedangkan angka SPR yang
            // berwibawa sudah ada dalam payload.
            .'Medan `rasmi` mengandungi keputusan RASMI SPR bagi kerusi ini daripada electiondata.my '
            .'(pemenang, majoriti, keluar mengundi, pengundi berdaftar bagi setiap pilihan raya lampau). '
            .'Gunakan `rasmi` sebagai sumber sejarah kerusi ini — JANGAN cari di web untuk keputusan lampau kerusi ini. '
            .'Perhatikan `rasmi` menyimpan ringkasan pemenang sahaja: ia TIADA pecahan undi setiap parti, '
            .'jadi jangan sesekali mengira peratus undi parti daripadanya.';

        // Bounded so the whole request finishes well inside the PHP/proxy
        // timeout: ≤3 searches, 70s per HTTP call, ~140s total wall-clock for
        // the search loop. If it runs out, we format the partial narrative.
        $p1 = $this->claude->chatWithWebSearch($p1system, $factsJson, 6000, 70, 'analisa_comparison', 3, 140);
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

            $p2 = $this->claude->chat($p2system, $p2user, 6000, 80, 'analisa_comparison_format');
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
            'rasmi' => $this->officialHistory($comparison),
        ];
    }

    /**
     * Sejarah keputusan RASMI SPR bagi kerusi ini (electiondata.my).
     *
     * Sebelum ini AI mendapatkan konteks sejarah melalui carian web — angka
     * yang tidak boleh disemak dan bercanggah dengan peraturan projek bahawa
     * setiap angka dikira di pelayan. Ini memberikannya angka rasmi tempatan.
     *
     * Ringkasan pemenang sahaja: pecahan undi setiap calon hanya disegerakkan
     * bagi pilihan raya TERKINI setiap kerusi (lihat SyncElectionDataCommand),
     * jadi jangan sesekali menganggap `undi` per parti wujud di sini.
     *
     * Setiap angka kekal null apabila tidak diketahui. Tiada `?? 0` — kerusi
     * tanpa angka bukan kerusi dengan sifar undi.
     *
     * Awam semata-mata supaya ia BOLEH DIUJI. buildFactPayload() memanggil
     * currentRoll(), yang menggunakan REGEXP/TIMESTAMPDIFF khusus MySQL dan
     * tidak boleh berjalan pada SQLite CI — jadi menguji melalui pintu depan
     * meninggalkan kaedah ini tanpa liputan langsung, dan di sinilah penapis
     * "pilihan raya belum berlaku" berada.
     *
     * @return array<int, array<string, mixed>>
     */
    public function officialHistory(AnalisaComparison $comparison): array
    {
        $kawasan = $comparison->level === 'parlimen'
            ? Bandar::find($comparison->bandar_id)
            : Kadun::find($comparison->kadun_id);

        $seat = $kawasan ? $this->baselines->seatFor($kawasan) : null;

        if (! $seat) {
            return [];
        }

        return $seat->results()
            // Pilihan raya AKAN DATANG dipulangkan API sebagai stub: party null,
            // setiap angka null. Ia diisih PALING ATAS kerana tarikhnya paling
            // lewat, jadi tanpa tapisan ini ia menjadi baris pertama "sejarah"
            // yang diberikan kepada model naratif — menjemput cerita tentang
            // pilihan raya yang belum berlaku. ElectionSeat::latestCompletedResult()
            // sudah menapisnya dengan cara yang sama.
            ->whereNotNull('party')
            ->orderByDesc('tarikh')
            ->get()
            ->map(fn ($r) => [
                'pilihanraya' => $r->election_name,
                'tarikh' => $r->tarikh?->format('Y-m-d'),
                'parti_menang' => $r->party,
                'gabungan' => $r->coalition,
                'calon' => $r->candidate,
                'majoriti' => $r->majority,
                'majoriti_perc' => $r->majority_perc !== null ? (float) $r->majority_perc : null,
                'pengundi_berdaftar' => $r->voters_total,
                'keluar_mengundi' => $r->voter_turnout,
                'keluar_mengundi_perc' => $r->voter_turnout_perc !== null ? (float) $r->voter_turnout_perc : null,
                'undi_ditolak' => $r->votes_rejected,
            ])
            ->all();
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

        // Unknown is NOT zero: an official SPR scoresheet genuinely omits the
        // registered-voter count (column (A) is ballots in the box, not
        // registrations), and Borang14ScenarioMapper::totalPemilih() returns
        // null in that case. Coercing null to 0 here previously told the AI
        // "registered voters: 0", which fabricated claims like a 100% drop —
        // see deltas() below where that number actually gets produced.
        //
        // A `pemilih` of <= 0 is treated the SAME way, even though the value
        // is technically "known" (not missing): tiada mana-mana kawasan
        // mengundi sebenar mempunyai SIFAR pengundi berdaftar, jadi angka
        // sifar (atau negatif) bukan fakta — ia tanda angka sebenar tidak
        // diketahui. Ini menutup laluan terakhir untuk dakwaan "-100%" palsu
        // jika AI menghantar `pemilih: 0` walaupun prompt mengarahkan `null`
        // bagi angka yang tidak diketahui — sanitize() sepatutnya tidak
        // sekali-kali mempercayai 0 sebagai angka pengundi berdaftar sebenar.
        $pemilihRaw = $t['pemilih'] ?? null;
        $pemilih = ($pemilihRaw !== null && (float) $pemilihRaw > 0) ? (float) $pemilihRaw : null;
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
                // Sama seperti jumlah pemilih senario di atas: pemilih <= 0 bagi
                // satu-satu kawasan mengundi bukan fakta sebenar — tiada kawasan
                // mengundi sebenar berdaftar sifar — jadi ia dilayan sebagai
                // tidak diketahui (null), bukan angka sahih.
                'pemilih' => (isset($r['pemilih']) && (int) $r['pemilih'] > 0) ? (int) $r['pemilih'] : null,
                'keluar' => (int) ($r['keluar'] ?? 0),
                'undi' => collect($r['undi'] ?? [])->map(fn ($v) => (int) $v)->all(),
            ])->values()->all();

        return [
            'label' => $scenario->label,
            'tarikh' => $scenario->election_date?->format('Y-m-d'),
            'tahun' => $scenario->election_date?->format('Y'),
            'parti' => $parties,
            'pemilih_berdaftar' => $pemilih !== null ? (int) $pemilih : null,
            'undi_keluar' => (int) $keluar,
            'peratus_keluar' => ($pemilih !== null && $pemilih > 0) ? round($keluar / $pemilih * 100, 1) : null,
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
            // Unknown pemilih_berdaftar on EITHER side means the delta is
            // unknown too — never subtract against a coerced 0. This is
            // exactly where the fabricated "-100%" turnout claim used to be
            // produced when an unknown scenario's pemilih had been coerced
            // to 0 upstream. scenarioSummary() now also collapses any
            // pemilih <= 0 into null (sifar bukan angka sebenar), so by the
            // time a summary reaches here, pemilih_berdaftar is guaranteed to
            // be either null or a genuine positive count — never a stored 0.
            $dPemilih = ($a['pemilih_berdaftar'] !== null && $b['pemilih_berdaftar'] !== null)
                ? $b['pemilih_berdaftar'] - $a['pemilih_berdaftar']
                : null;

            // Senario ringkasan-sahaja (keputusan rasmi SPR lama) tiada pecahan
            // per parti langsung. `parti` KOSONG bermakna TIDAK DIKETAHUI, bukan
            // sifar undi — dan `?? 0` di bawah akan menjadikan setiap parti
            // dalam senario yang satu lagi "jatuh ke sifar", mencetak ayunan
            // yang direka sepenuhnya bersebelahan angka rasmi SPR.
            //
            // Ayunan hanya bermakna apabila KEDUA-DUA belah tahu pecahannya.
            $adaPecahan = ! empty($a['parti']) && ! empty($b['parti']);

            $ayun = null;
            if ($adaPecahan) {
                $parties = array_values(array_unique(array_merge($a['parti'], $b['parti'])));
                $ayun = [];
                foreach ($parties as $p) {
                    // Di sini `?? 0` SAH: kedua-dua senario tahu pecahannya,
                    // jadi parti yang tiada memang tidak bertanding.
                    $ayun[$p] = round(($b['peratus_undi'][$p] ?? 0) - ($a['peratus_undi'][$p] ?? 0), 1);
                }
            }

            // Peratusan hanya memerlukan $dPemilih !== null: sebaik sahaja
            // scenarioSummary() memastikan pemilih_berdaftar bukan-null
            // semestinya > 0 (sifar dilayan sebagai tidak diketahui di atas),
            // pembahagi di sini tidak boleh lagi menjadi sifar — pengawal
            // "> 0" yang lama (untuk elak bahagi-dengan-sifar terhadap
            // pemilih_berdaftar=0 yang "diketahui") sudah tidak boleh dicapai
            // dan telah dibuang.
            $out[] = [
                'dari' => $a['label'],
                'ke' => $b['label'],
                'perubahan_pemilih' => $dPemilih,
                'perubahan_pemilih_pct' => $dPemilih !== null
                    ? round($dPemilih / $a['pemilih_berdaftar'] * 100, 1) : null,
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
            // Kedua-dua terbitan DPT (anggaran Lokaliti dan struktur sebenar
            // DPPR/DPI) ditanda 'dpt_estimate' di sini: naratif perbandingan
            // membezakan angka BERGAZET daripada apa-apa yang kami terbitkan
            // sendiri daripada roll, dan struktur sebenar pun bukan gazet.
            if (Borang14Reference::daripadaDpt($ref)) {
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
            if ($d['perubahan_pemilih'] === null) {
                $growthBullets[] = "Daripada {$d['dari']} ke {$d['ke']}: bilangan pengundi berdaftar tidak diketahui "
                    .'bagi sekurang-kurangnya satu senario (tiada dalam scoresheet rasmi).';

                continue;
            }
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
                    .($s['peratus_keluar'] !== null ? $s['peratus_keluar'].'%' : 'tiada data').'.',
            ])->take(3)->values()->all(),
            'faktor_perubahan' => [],
            'kesimpulan' => 'Analisis naratif AI tidak dapat dijana — hanya ringkasan berangka dipaparkan.',
            'rujukan' => [],
        ];
    }
}
