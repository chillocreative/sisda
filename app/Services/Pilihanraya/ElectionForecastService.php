<?php

namespace App\Services\Pilihanraya;

use App\Models\PilihanrayaForecast;
use App\Services\ClaudeService;
use Illuminate\Support\Facades\Log;

/**
 * Claude orchestration for the Pusat Simulasi. Follows the
 * UserLogAlertService pattern: aggregate-only payloads (zero PII),
 * strict JSON schemas in the system prompt, server-side validation of
 * the reply, and a deterministic fallback so the UI always renders
 * even when the AI is disabled or errors.
 *
 * The fallback logistic math (k=12, coverage/0.30 confidence shrink)
 * must stay in sync with resources/js/Pages/Pilihanraya/simulation/whatIfModel.js.
 */
class ElectionForecastService
{
    private const CONFIDENCE_LEVELS = ['rendah', 'sederhana', 'tinggi'];

    private const PERSONA = 'You are a senior election analyst and campaign strategist for SISDA, '
        .'a Malaysian voter-intelligence system. You receive ONLY aggregate canvassing statistics '
        .'(no personal data). In the data: putih = pro-PH voters, hitam = opposition-leaning voters, '
        .'kelabu = fence-sitters (atas pagar). Seat health scores 0-100 are precomputed '
        .'(>=75 Selamat, 65-74 Cenderung Kuat, 55-64 Cenderung, 45-54 Berayun, 35-44 Kritikal, <35 Risiko Kalah) '
        .'— do not recompute them; reason on top of them. Be conservative when liputan (coverage) is below 30%. '
        .'CRITICAL LANGUAGE RULE: every piece of human-readable text you output (narrative, tajuk, '
        .'kandungan, bullet_points, kategori, catatan, kesimpulan, tindakan_segera, answer, '
        .'recommendations, summary, expected_impact, recommended_action, anggaran_perubahan, dll.) '
        .'MUST be written entirely in formal Bahasa Malaysia. Do NOT use any English words or sentences. '
        .'NEVER write raw data field names from the JSON payload in your prose — keys such as '
        .'putih_pct, hitam_pct, kelabu_pct, tren_putih_30h, liputan_pct, roll_total are internal '
        .'identifiers, not words; always describe them in natural Bahasa Malaysia instead '
        .'(e.g. "peratusan sokongan putih", "kadar liputan culaan", "tren sokongan 30 hari"). ';

    public function __construct(
        protected ElectionAnalyticsService $analytics,
        protected ElectionEarlyWarningService $earlyWarning,
        protected ClaudeService $claude,
    ) {}

    /* ----------------------------------------------------------------
     |  Public entry points
     * ---------------------------------------------------------------- */

    public function forecast(array $f, int $userId): array
    {
        $payload = $this->buildIntelPayload($f);

        $system = self::PERSONA
            .'Respond ONLY with a JSON object, no prose, matching exactly this schema: '
            .'{"ph_win_probability":0-100,"opposition_win_probability":0-100,"swing_probability":0-100,'
            .'"risk_score":0-100,"expected_majority":int,"confidence":"rendah|sederhana|tinggi",'
            .'"seat_projections":[{"kerusi":"string","jenis":"kadun","ph_probability":0-100,"kategori":"string","catatan":"string"}],'
            .'"narrative":"string"}. '
            .'ph_win_probability + opposition_win_probability must equal 100. '
            .'Include at most 20 seat_projections, prioritising swing and critical seats. '
            .'Write "narrative", "kategori" and "catatan" in Bahasa Malaysia.';

        $result = $this->claude->chat($system, json_encode($payload, JSON_UNESCAPED_UNICODE), 4096, 120);

        $parsed = $result['ok'] ? $this->validateForecast($this->claude->extractJson($result['content']), $payload) : null;

        if ($parsed) {
            $record = $this->persist('forecast', $f, $payload, $parsed, 'ok', $userId);

            return ['status' => 'ok', 'result' => $parsed, 'id' => $record->id, 'generated_at' => $record->created_at->toIso8601String()];
        }

        Log::warning('Pilihanraya forecast fell back to deterministic model', ['error' => $result['error'] ?? 'parse_failed']);
        $fallback = $this->fallbackForecast($payload);
        $record = $this->persist('forecast', $f, $payload, $fallback, 'fallback', $userId);

        return ['status' => 'fallback', 'result' => $fallback, 'id' => $record->id, 'generated_at' => $record->created_at->toIso8601String()];
    }

    public function warGame(array $f, string $question, array $sliders, int $userId): array
    {
        $payload = [
            'data' => $this->buildIntelPayload($f),
            'soalan' => $question,
            'senario_semasa' => $sliders,
        ];

        $system = self::PERSONA
            .'The user poses a hypothetical campaign scenario ("soalan"), optionally with current '
            .'what-if slider settings ("senario_semasa"). Use only the supplied aggregates. '
            .'Respond ONLY with JSON: {"answer":"string",'
            .'"affected_seats":[{"kerusi":"string","impak":"positif|negatif|neutral","anggaran_perubahan":"string"}],'
            .'"recommendations":["string"],"confidence":"rendah|sederhana|tinggi"}. '
            .'All text in Bahasa Malaysia. Max 10 affected_seats, max 6 recommendations.';

        $result = $this->claude->chat($system, json_encode($payload, JSON_UNESCAPED_UNICODE), 4096, 120);

        if ($result['ok']) {
            $parsed = $this->claude->extractJson($result['content']);
            if ($parsed && isset($parsed['answer'])) {
                $parsed = [
                    'answer' => (string) $parsed['answer'],
                    'affected_seats' => $this->cleanSeatList($parsed['affected_seats'] ?? [], $payload['data'], 10),
                    'recommendations' => array_slice(array_map('strval', (array) ($parsed['recommendations'] ?? [])), 0, 6),
                    'confidence' => $this->cleanConfidence($parsed['confidence'] ?? null),
                ];
                $this->persist('war_game', $f, $payload, $parsed, 'ok', $userId);

                return ['status' => 'ok', 'result' => $parsed];
            }
        }

        $this->persist('war_game', $f, $payload, null, 'failed', $userId);

        return [
            'status' => 'fallback',
            'result' => [
                'answer' => 'AI tidak tersedia buat masa ini. Sila gunakan panel What-If untuk simulasi deterministik, atau cuba semula sebentar lagi.',
                'affected_seats' => [],
                'recommendations' => [],
                'confidence' => 'rendah',
            ],
        ];
    }

    public function resourceAllocation(array $f, int $userId): array
    {
        $payload = $this->buildIntelPayload($f);

        $system = self::PERSONA
            .'Recommend campaign resource allocation across the seats in the data. '
            .'Respond ONLY with JSON: {"allocations":[{"kawasan":"string","jenis":"kadun",'
            .'"priority_score":0-100,"expected_impact":"string","recommended_action":"string"}],"summary":"string"}. '
            .'Rank by marginal value: prioritise Berayun and Kritikal seats with high kelabu counts and adequate '
            .'coverage; flag low-coverage seats for canvassing (culaan) rather than persuasion. '
            .'Max 15 allocations. All text in Bahasa Malaysia.';

        $result = $this->claude->chat($system, json_encode($payload, JSON_UNESCAPED_UNICODE), 4096, 120);

        if ($result['ok']) {
            $parsed = $this->claude->extractJson($result['content']);
            if ($parsed && isset($parsed['allocations'])) {
                $validNames = collect($payload['kerusi'])->pluck('kerusi')->flip();
                $allocations = collect((array) $parsed['allocations'])
                    ->filter(fn ($a) => isset($a['kawasan']) && $validNames->has($a['kawasan']))
                    ->map(fn ($a) => [
                        'kawasan' => (string) $a['kawasan'],
                        'jenis' => 'kadun',
                        'priority_score' => max(0, min(100, (int) ($a['priority_score'] ?? 0))),
                        'expected_impact' => (string) ($a['expected_impact'] ?? ''),
                        'recommended_action' => (string) ($a['recommended_action'] ?? ''),
                    ])
                    ->sortByDesc('priority_score')
                    ->take(15)
                    ->values()
                    ->all();

                $parsed = ['allocations' => $allocations, 'summary' => (string) ($parsed['summary'] ?? '')];
                $this->persist('resources', $f, $payload, $parsed, 'ok', $userId);

                return ['status' => 'ok', 'result' => $parsed];
            }
        }

        // Deterministic fallback: rank by marginal value heuristic
        $allocations = collect($payload['kerusi'])
            ->map(function ($seat) {
                $needsCanvass = $seat['liputan'] < 10;
                $swingValue = max(0, 50 - abs($seat['skor'] - 50));
                $priority = (int) round($swingValue + min(30, $seat['kelabu'] / 50) + ($needsCanvass ? 10 : 0));

                return [
                    'kawasan' => $seat['kerusi'],
                    'jenis' => 'kadun',
                    'priority_score' => min(100, $priority),
                    'expected_impact' => $needsCanvass
                        ? 'Data culaan terlalu nipis untuk pemujukan berkesan.'
                        : "Kira-kira {$seat['kelabu']} pengundi atas pagar boleh dipujuk.",
                    'recommended_action' => $needsCanvass
                        ? 'Utamakan operasi culaan untuk membina pangkalan data.'
                        : 'Kerahkan jentera pemujukan dan program komuniti.',
                ];
            })
            ->sortByDesc('priority_score')
            ->take(15)
            ->values()
            ->all();

        $fallback = ['allocations' => $allocations, 'summary' => 'Unjuran deterministik (AI tidak tersedia): keutamaan dikira daripada skor kerusi, bilangan atas pagar dan liputan culaan.'];
        $this->persist('resources', $f, $payload, $fallback, 'fallback', $userId);

        return ['status' => 'fallback', 'result' => $fallback];
    }

    public function briefing(string $level, ?string $scopeId, int $userId): array
    {
        $f = $this->analytics->resolveFilters(match ($level) {
            'negeri' => ['negeri_id' => $scopeId],
            'parlimen' => ['parlimen_id' => $scopeId],
            'kadun' => ['kadun_id' => $scopeId],
            default => [],
        });

        $payload = $this->buildIntelPayload($f);
        $payload['skop'] = ['peringkat' => $level, 'nama' => $f['negeri'] ?? $f['parlimen'] ?? $f['kadun'] ?? 'Nasional'];

        $system = self::PERSONA
            .'Produce an executive election briefing for party leadership covering the supplied scope. '
            .'Respond ONLY with JSON: {"tajuk":"string","tarikh":"string",'
            .'"seksyen":[{"tajuk":"string","kandungan":"string","bullet_points":["string"]}],'
            .'"kesimpulan":"string","tindakan_segera":["string"]}. '
            .'Exactly 4-6 seksyen covering: keadaan keseluruhan, kerusi berisiko, peluang, '
            .'liputan culaan, dan demografi. Formal Bahasa Malaysia.';

        $result = $this->claude->chat($system, json_encode($payload, JSON_UNESCAPED_UNICODE), 6000, 120);

        if ($result['ok']) {
            $parsed = self::sanitizeBriefing($this->claude->extractJson($result['content']));
            if ($parsed) {
                $this->persist('briefing', $f, $payload, $parsed, 'ok', $userId, $level, $payload['skop']['nama']);

                return ['status' => 'ok', 'result' => $parsed, 'seatScores' => $payload['kerusi']];
            }
        }

        $fallback = $this->fallbackBriefing($payload);
        $this->persist('briefing', $f, $payload, $fallback, 'fallback', $userId, $level, $payload['skop']['nama']);

        return ['status' => 'fallback', 'result' => $fallback, 'seatScores' => $payload['kerusi']];
    }

    public function latestForecast(): ?PilihanrayaForecast
    {
        return PilihanrayaForecast::where('type', 'forecast')
            ->where('status', '!=', 'failed')
            ->latest()
            ->first();
    }

    /* ----------------------------------------------------------------
     |  Payload & validation
     * ---------------------------------------------------------------- */

    /**
     * Aggregate-only intel payload — area names, counts, shares and
     * scores only. No ICs, no voter or staff names (same strict PII
     * policy as UserLogAlertService).
     */
    private function buildIntelPayload(array $f, int $maxSeats = 30): array
    {
        $overview = $this->analytics->overview($f);
        $sentiment = $this->analytics->sentiment($f);
        $composition = $this->analytics->composition($f);
        $trends = $this->analytics->trendByKadun($f);
        $alerts = $this->earlyWarning->scan($f);

        $seats = collect($this->analytics->seatScores($f)['kadun'])
            ->sortBy(fn ($r) => abs($r['score'] - 50))
            ->take($maxSeats)
            ->map(function ($seat) use ($trends) {
                $trend = $trends[ElectionAnalyticsService::nameKey($seat['name'])] ?? null;
                $delta = ($trend && isset($trend['recent'], $trend['prior']))
                    ? round($trend['recent']['putih_pct'] - $trend['prior']['putih_pct'], 1)
                    : null;

                return [
                    'kerusi' => $seat['name'],
                    'jenis' => 'kadun',
                    'daftar' => $seat['roll_total'],
                    'culaan' => $seat['canvassed'],
                    'liputan' => $seat['coverage_pct'],
                    'putih' => $seat['putih'],
                    'hitam' => $seat['hitam'],
                    'kelabu' => $seat['kelabu'],
                    'skor' => $seat['score'],
                    'kategori' => $seat['category'],
                    'tren_putih_30h' => $delta,
                ];
            })
            ->values()
            ->all();

        return [
            'keseluruhan' => [
                'pengundi_berdaftar' => $overview['roll_total'],
                'diculaan' => $overview['canvassed'],
                'liputan_pct' => $overview['coverage_pct'],
                'putih' => $overview['putih'],
                'hitam' => $overview['hitam'],
                'kelabu' => $overview['kelabu'],
            ],
            'kerusi' => $seats,
            'demografi' => [
                'umur' => $composition['ageBands'],
                'bangsa' => $composition['race'],
                'jantina' => $composition['gender'],
            ],
            'tren_mingguan' => array_slice($sentiment['weeklyTrend'], -8),
            'amaran' => [
                'jumlah' => $alerts->count(),
                'mengikut_severiti' => $alerts->countBy('severity'),
                'teratas' => $alerts->take(5)->map(fn ($a) => [
                    'kod' => $a['rule_code'],
                    'kawasan' => $a['kawasan'],
                    'mesej' => $a['message'],
                ])->values()->all(),
            ],
        ];
    }

    private function validateForecast(?array $json, array $payload): ?array
    {
        if (! $json || ! isset($json['ph_win_probability'])) {
            return null;
        }

        $clamp = fn ($v) => max(0, min(100, (float) $v));

        $ph = $clamp($json['ph_win_probability']);
        $validNames = collect($payload['kerusi'])->pluck('kerusi')->flip();

        return [
            'ph_win_probability' => round($ph, 1),
            'opposition_win_probability' => round(100 - $ph, 1),
            'swing_probability' => round($clamp($json['swing_probability'] ?? 0), 1),
            'risk_score' => round($clamp($json['risk_score'] ?? 0), 1),
            'expected_majority' => (int) ($json['expected_majority'] ?? 0),
            'confidence' => $this->cleanConfidence($json['confidence'] ?? null),
            'seat_projections' => collect((array) ($json['seat_projections'] ?? []))
                ->filter(fn ($s) => isset($s['kerusi']) && $validNames->has($s['kerusi']))
                ->map(fn ($s) => [
                    'kerusi' => (string) $s['kerusi'],
                    'jenis' => 'kadun',
                    'ph_probability' => round($clamp($s['ph_probability'] ?? 50), 1),
                    'kategori' => (string) ($s['kategori'] ?? ''),
                    'catatan' => (string) ($s['catatan'] ?? ''),
                ])
                ->take(20)
                ->values()
                ->all(),
            'narrative' => (string) ($json['narrative'] ?? ''),
        ];
    }

    private function cleanConfidence($value): string
    {
        return in_array($value, self::CONFIDENCE_LEVELS, true) ? $value : 'sederhana';
    }

    /**
     * Force a briefing payload (AI output or client re-post) into the
     * exact shape the viewer, blade view and Excel sheets render —
     * strings stay strings, lists stay sequential arrays. Returns null
     * when there is nothing renderable.
     */
    public static function sanitizeBriefing(?array $briefing): ?array
    {
        if (! $briefing) {
            return null;
        }

        $str = fn ($v) => is_scalar($v) ? (string) $v : '';
        $strList = fn ($v) => collect(is_array($v) ? $v : [])
            ->map($str)->filter(fn ($s) => $s !== '')->values()->all();

        $seksyen = collect(is_array($briefing['seksyen'] ?? null) ? $briefing['seksyen'] : [])
            ->filter(fn ($s) => is_array($s))
            ->map(fn ($s) => [
                'tajuk' => $str($s['tajuk'] ?? ''),
                'kandungan' => $str($s['kandungan'] ?? ''),
                'bullet_points' => $strList($s['bullet_points'] ?? []),
            ])
            ->values()
            ->all();

        if (empty($seksyen)) {
            return null;
        }

        return [
            'tajuk' => $str($briefing['tajuk'] ?? '') ?: 'Taklimat Eksekutif Pilihanraya',
            'tarikh' => $str($briefing['tarikh'] ?? '') ?: now()->format('d/m/Y'),
            'seksyen' => $seksyen,
            'kesimpulan' => $str($briefing['kesimpulan'] ?? ''),
            'tindakan_segera' => $strList($briefing['tindakan_segera'] ?? []),
        ];
    }

    private function cleanSeatList($seats, array $intelPayload, int $max): array
    {
        $validNames = collect($intelPayload['kerusi'] ?? [])->pluck('kerusi')->flip();

        return collect((array) $seats)
            ->filter(fn ($s) => isset($s['kerusi']) && $validNames->has($s['kerusi']))
            ->map(fn ($s) => [
                'kerusi' => (string) $s['kerusi'],
                'impak' => in_array($s['impak'] ?? null, ['positif', 'negatif', 'neutral'], true) ? $s['impak'] : 'neutral',
                'anggaran_perubahan' => (string) ($s['anggaran_perubahan'] ?? ''),
            ])
            ->take($max)
            ->values()
            ->all();
    }

    /* ----------------------------------------------------------------
     |  Deterministic fallbacks
     * ---------------------------------------------------------------- */

    /**
     * Logistic seat-probability model — identical math to the client
     * what-if model (k=12, coverage/0.30 confidence shrink). Keep both
     * in sync.
     */
    private function fallbackForecast(array $payload): array
    {
        $seats = collect($payload['kerusi']);

        // No scoreable seats — report uncertainty, not a fabricated rout.
        if ($seats->isEmpty()) {
            return [
                'ph_win_probability' => 50.0,
                'opposition_win_probability' => 50.0,
                'swing_probability' => 0,
                'risk_score' => 50.0,
                'expected_majority' => 0,
                'confidence' => 'rendah',
                'seat_projections' => [],
                'narrative' => 'Tiada data culaan mencukupi untuk unjuran — muat naik pangkalan data pengundi dan jalankan culaan terlebih dahulu.',
            ];
        }

        $projections = $seats->map(function ($seat) {
            $canvassed = max(1, $seat['culaan']);
            $margin = ($seat['putih'] - $seat['hitam']) / $canvassed;
            $pRaw = 1 / (1 + exp(-12 * $margin));
            $confidence = min(1, ($seat['liputan'] / 100) / 0.30);
            $p = 0.5 + ($pRaw - 0.5) * $confidence;

            return [
                'kerusi' => $seat['kerusi'],
                'jenis' => 'kadun',
                'ph_probability' => round($p * 100, 1),
                'kategori' => $seat['kategori'],
                'catatan' => 'Unjuran deterministik daripada skor kesihatan kerusi.',
            ];
        });

        $total = $projections->count();
        $expectedWins = $projections->sum(fn ($p) => $p['ph_probability'] / 100);
        $wins = $projections->filter(fn ($p) => $p['ph_probability'] > 50)->count();
        $phProb = round(($expectedWins / $total) * 100, 1);
        $swingSeats = $seats->filter(fn ($s) => $s['skor'] >= 45 && $s['skor'] <= 54)->count();

        return [
            'ph_win_probability' => $phProb,
            'opposition_win_probability' => round(100 - $phProb, 1),
            'swing_probability' => $total > 0 ? round(($swingSeats / $total) * 100, 1) : 0,
            'risk_score' => round(100 - $phProb, 1),
            'expected_majority' => (int) round($wins - ($total - $wins)),
            'confidence' => ($payload['keseluruhan']['liputan_pct'] ?? 0) >= 30 ? 'sederhana' : 'rendah',
            'seat_projections' => $projections->sortBy(fn ($p) => abs($p['ph_probability'] - 50))->take(20)->values()->all(),
            'narrative' => 'AI tidak tersedia — unjuran deterministik dipaparkan, dikira daripada skor kesihatan kerusi '
                .'dengan model logistik dan pelarasan keyakinan mengikut liputan culaan.',
        ];
    }

    private function fallbackBriefing(array $payload): array
    {
        $k = $payload['keseluruhan'];
        $risky = collect($payload['kerusi'])->whereIn('kategori', ['Kritikal', 'Risiko Kalah'])->pluck('kerusi')->take(10);
        $swing = collect($payload['kerusi'])->where('kategori', 'Berayun')->pluck('kerusi')->take(10);

        return [
            'tajuk' => 'Taklimat Eksekutif Pilihanraya — '.($payload['skop']['nama'] ?? 'Nasional'),
            'tarikh' => now()->format('d/m/Y'),
            'seksyen' => [
                [
                    'tajuk' => 'Keadaan Keseluruhan',
                    'kandungan' => "Daripada {$k['pengundi_berdaftar']} pengundi berdaftar, {$k['diculaan']} telah diculaan ({$k['liputan_pct']}% liputan). "
                        ."Pecahan sentimen: {$k['putih']} putih, {$k['hitam']} hitam, {$k['kelabu']} kelabu.",
                    'bullet_points' => [],
                ],
                [
                    'tajuk' => 'Kerusi Berisiko',
                    'kandungan' => $risky->isEmpty() ? 'Tiada kerusi dalam kategori kritikal berdasarkan data semasa.' : 'Kerusi dalam kategori Kritikal / Risiko Kalah:',
                    'bullet_points' => $risky->values()->all(),
                ],
                [
                    'tajuk' => 'Kerusi Berayun',
                    'kandungan' => $swing->isEmpty() ? 'Tiada kerusi berayun dikesan.' : 'Kerusi berayun yang menentukan keputusan:',
                    'bullet_points' => $swing->values()->all(),
                ],
                [
                    'tajuk' => 'Liputan Culaan',
                    'kandungan' => "Liputan keseluruhan {$k['liputan_pct']}%. Liputan bawah 30% mengurangkan kebolehpercayaan unjuran.",
                    'bullet_points' => [],
                ],
            ],
            'kesimpulan' => 'Taklimat ini dijana secara deterministik kerana AI tidak tersedia. Sila aktifkan Tetapan Claude AI untuk analisis penuh.',
            'tindakan_segera' => ['Tingkatkan liputan culaan di kawasan bawah 30%.', 'Fokuskan jentera pada kerusi berayun.'],
        ];
    }

    private function persist(string $type, array $f, array $payload, ?array $result, string $status, int $userId, string $scopeLevel = 'national', ?string $scopeName = null): PilihanrayaForecast
    {
        if ($scopeLevel === 'national') {
            if (! empty($f['kadun'])) {
                $scopeLevel = 'kadun';
                $scopeName = $f['kadun'];
            } elseif (! empty($f['parlimen'])) {
                $scopeLevel = 'parlimen';
                $scopeName = $f['parlimen'];
            } elseif (! empty($f['negeri'])) {
                $scopeLevel = 'negeri';
                $scopeName = $f['negeri'];
            }
        }

        return PilihanrayaForecast::create([
            'type' => $type,
            'scope_level' => $scopeLevel,
            'scope_name' => $scopeName,
            'payload' => $payload,
            'result' => $result,
            'status' => $status,
            'created_by' => $userId,
        ]);
    }
}
