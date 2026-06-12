<?php

namespace App\Services\Pilihanraya;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Server-side aggregation engine for the Pilihanraya war room. Every
 * public method returns compact chart-ready arrays — raw voter rows
 * never leave the database. All results are cached for 10 minutes and
 * auto-busted when canvass data changes (canvassVersion).
 *
 * Geography caveat: the voter roll (DPT) stores area names UPPERCASE
 * while canvass tables store mixed case — SQL comparisons are
 * collation-insensitive but every PHP-side keyBy/lookup must go
 * through nameKey().
 */
class ElectionAnalyticsService
{
    private const CACHE_TTL = 600;

    public const AGE_BANDS = [
        ['label' => '18-20', 'min' => 18, 'max' => 20],
        ['label' => '21-29', 'min' => 21, 'max' => 29],
        ['label' => '30-39', 'min' => 30, 'max' => 39],
        ['label' => '40-49', 'min' => 40, 'max' => 49],
        ['label' => '50-59', 'min' => 50, 'max' => 59],
        ['label' => '60-69', 'min' => 60, 'max' => 69],
        ['label' => '70+',   'min' => 70, 'max' => 200],
    ];

    public function __construct(protected HealthScoreService $health) {}

    /* ----------------------------------------------------------------
     |  Filters & shared plumbing
     * ---------------------------------------------------------------- */

    /**
     * Convert dropdown IDs to the name strings stored on the data tables
     * (same convention as DashboardController). "parlimen" maps to the
     * Bandar master record — Bandar is the parliament level in SISDA.
     */
    public function resolveFilters(array $input): array
    {
        return [
            'negeri' => ! empty($input['negeri_id']) ? Negeri::find($input['negeri_id'])?->nama : null,
            'parlimen' => ! empty($input['parlimen_id']) ? Bandar::find($input['parlimen_id'])?->nama : null,
            'kadun' => ! empty($input['kadun_id']) ? Kadun::find($input['kadun_id'])?->nama : null,
            'tarikh_dari' => $input['tarikh_dari'] ?? null,
            'tarikh_hingga' => $input['tarikh_hingga'] ?? null,
        ];
    }

    /**
     * IDs of all active upload batches — several can be active at once
     * (e.g. one roll per parliament); the voter roll is their union.
     */
    public function activeBatchIds(): array
    {
        return Cache::remember('pilihanraya:active_batches', 60, function () {
            return UploadBatch::activeIds();
        });
    }

    public function filterLists(): array
    {
        return [
            'negeriList' => Negeri::orderBy('nama')->get(['id', 'nama']),
            'parlimenList' => Bandar::orderBy('nama')->get(['id', 'nama', 'negeri_id']),
            'kadunList' => Kadun::orderBy('nama')->get(['id', 'nama', 'bandar_id']),
        ];
    }

    /** Case/whitespace-insensitive key for merging roll and canvass names. */
    public static function nameKey(?string $name): string
    {
        return mb_strtoupper(trim((string) $name));
    }

    /**
     * Cache version derived from canvass freshness. Row count + max id +
     * max updated_at together catch inserts, edits AND deletions.
     */
    private function canvassVersion(): string
    {
        return Cache::remember('pilihanraya:cv', 60, function () {
            $h = HasilCulaan::query()->selectRaw('COUNT(*) AS c, MAX(id) AS mi, MAX(updated_at) AS mu')->first();
            $d = DataPengundi::query()->selectRaw('COUNT(*) AS c, MAX(id) AS mi, MAX(updated_at) AS mu')->first();

            return md5("{$h->c}|{$h->mi}|{$h->mu}|{$d->c}|{$d->mi}|{$d->mu}");
        });
    }

    private function remember(string $method, array $f, \Closure $callback)
    {
        $key = sprintf(
            'pilihanraya:%s:b%s:cv%s:%s',
            $method,
            md5(json_encode($this->activeBatchIds())),
            $this->canvassVersion(),
            md5(json_encode($f))
        );

        return Cache::remember($key, self::CACHE_TTL, $callback);
    }

    /**
     * Filter conditions applied INSIDE each union branch, on the base
     * tables. Filtering before the window-function dedup is both the
     * correct semantic (a voter's latest record WITHIN the selected
     * window counts, instead of the voter vanishing when their newest
     * record falls outside it) and lets the base-table indexes prune
     * rows before materialisation.
     */
    private function baseCanvassConditions(array $f, bool $withDates): array
    {
        $sql = '';
        $bindings = [];

        if (! empty($f['negeri'])) {
            $sql .= ' AND negeri = ?';
            $bindings[] = $f['negeri'];
        }
        if (! empty($f['parlimen'])) {
            $sql .= " AND COALESCE(NULLIF(parlimen, ''), bandar) = ?";
            $bindings[] = $f['parlimen'];
        }
        if (! empty($f['kadun'])) {
            $sql .= ' AND kadun = ?';
            $bindings[] = $f['kadun'];
        }

        if ($withDates) {
            if (! empty($f['tarikh_dari'])) {
                $sql .= ' AND DATE(created_at) >= ?';
                $bindings[] = $f['tarikh_dari'];
            }
            if (! empty($f['tarikh_hingga'])) {
                $sql .= ' AND DATE(created_at) <= ?';
                $bindings[] = $f['tarikh_hingga'];
            }
        }

        return [$sql, $bindings];
    }

    /**
     * Canonical canvass union: every hasil_culaan + data_pengundi record
     * normalised to one column set, filtered per branch. `parlimen`
     * falls back to `bandar` (older rows stored the parliament name
     * there). Returns [sql, bindings].
     */
    private function canvassUnionSql(array $f = [], bool $withDates = true): array
    {
        [$cond, $bindings] = $this->baseCanvassConditions($f, $withDates);

        $sql = "
            SELECT no_ic, voter_color, bangsa, umur,
                   kadun, COALESCE(NULLIF(parlimen, ''), bandar) AS parlimen,
                   negeri, created_at, id
              FROM hasil_culaan
             WHERE is_deceased = 0 AND no_ic IS NOT NULL AND no_ic <> ''{$cond}
             UNION ALL
            SELECT no_ic, voter_color, bangsa, umur,
                   kadun, COALESCE(NULLIF(parlimen, ''), bandar) AS parlimen,
                   negeri, created_at, id
              FROM data_pengundi
             WHERE is_deceased = 0 AND no_ic IS NOT NULL AND no_ic <> ''{$cond}
        ";

        return [$sql, array_merge($bindings, $bindings)];
    }

    /**
     * Filtered union deduped by IC, keeping the most recent record —
     * the freshest sentiment signal per voter. Returns [sql, bindings].
     */
    private function canvassDedupedSql(array $f = [], bool $withDates = true): array
    {
        [$union, $bindings] = $this->canvassUnionSql($f, $withDates);

        $sql = '
            SELECT * FROM (
                SELECT u.*, ROW_NUMBER() OVER (PARTITION BY no_ic ORDER BY created_at DESC, id DESC) AS rn
                  FROM ('.$union.') u
            ) d WHERE d.rn = 1
        ';

        return [$sql, $bindings];
    }

    /**
     * The voter-color rollup fragment. Single source for the kelabu
     * default: NULL or empty voter_color counts as kelabu (matches
     * VoterColorService's undecided default).
     */
    private function colorSumsSql(string $alias = 'c'): string
    {
        return "COALESCE(SUM({$alias}.voter_color = 'putih'), 0) AS putih,
                COALESCE(SUM({$alias}.voter_color = 'hitam'), 0) AS hitam,
                COALESCE(SUM({$alias}.voter_color = 'kelabu' OR {$alias}.voter_color IS NULL OR {$alias}.voter_color = ''), 0) AS kelabu";
    }

    private function rollQuery(array $f)
    {
        $query = PangkalanDataPengundi::query()
            ->whereIn('upload_batch_id', $this->activeBatchIds() ?: [-1])
            ->where('is_deceased', false);

        foreach (['negeri', 'parlimen', 'kadun'] as $col) {
            if (! empty($f[$col])) {
                $query->where($col, $f[$col]);
            }
        }

        return $query;
    }

    /** SQL expression: voter age derived from tahun_lahir (string column). */
    private function rollAgeExpr(): string
    {
        return '(YEAR(CURDATE()) - CAST(tahun_lahir AS UNSIGNED))';
    }

    private function rollAgeGuard(): string
    {
        return "tahun_lahir REGEXP '^[0-9]{4}$'";
    }

    /** Normalised gender: jantina column with IC 12th-digit fallback. */
    private function genderExpr(): string
    {
        return "CASE
            WHEN UPPER(TRIM(jantina)) IN ('L', 'LELAKI') THEN 'L'
            WHEN UPPER(TRIM(jantina)) IN ('P', 'PEREMPUAN') THEN 'P'
            WHEN no_ic REGEXP '^[0-9]{12}$' THEN IF(MOD(CAST(SUBSTRING(no_ic, 12, 1) AS UNSIGNED), 2) = 1, 'L', 'P')
            ELSE NULL
        END";
    }

    /** Bucket free-text bangsa into Melayu / Cina / India / Lain-lain. */
    private function bangsaBucket(?string $bangsa): string
    {
        $b = strtoupper(trim((string) $bangsa));

        return match (true) {
            str_contains($b, 'MELAYU') => 'Melayu',
            str_contains($b, 'CINA') || str_contains($b, 'CHINESE') => 'Cina',
            str_contains($b, 'INDIA') => 'India',
            default => 'Lain-lain',
        };
    }

    /* ----------------------------------------------------------------
     |  War Room tab data
     * ---------------------------------------------------------------- */

    public function overview(array $f): array
    {
        return $this->remember('overview', $f, function () use ($f) {
            $hasBatch = $this->activeBatchIds() !== [];

            $rollTotal = $hasBatch ? (clone $this->rollQuery($f))->count() : 0;

            [$dedupSql, $dedupBindings] = $this->canvassDedupedSql($f);
            $colors = DB::selectOne("
                SELECT COUNT(*) AS canvassed,
                       {$this->colorSumsSql()},
                       MAX(c.created_at) AS last_canvass_at
                  FROM ({$dedupSql}) c
            ", $dedupBindings);

            $covered = 0;
            if ($hasBatch && $rollTotal > 0) {
                $rollSql = $this->rollQuery($f)->select('no_ic')->toSql();
                $rollBindings = $this->rollQuery($f)->getBindings();
                $covered = DB::selectOne("
                    SELECT COUNT(DISTINCT p.no_ic) AS covered
                      FROM ({$rollSql}) p
                      JOIN (SELECT no_ic FROM hasil_culaan WHERE is_deceased = 0 AND no_ic <> ''
                            UNION
                            SELECT no_ic FROM data_pengundi WHERE is_deceased = 0 AND no_ic <> '') c
                        ON c.no_ic = p.no_ic
                ", $rollBindings)->covered ?? 0;
            }

            $canvassed = (int) ($colors->canvassed ?? 0);

            // 30-day growth of canvass effort (geo filters only — the
            // window is fixed regardless of the user's date range)
            [$unionSql, $unionBindings] = $this->canvassUnionSql($f, false);
            $growth = DB::selectOne("
                SELECT COALESCE(SUM(c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS recent,
                       COALESCE(SUM(c.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                           AND c.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)), 0) AS prior
                  FROM ({$unionSql}) c
            ", $unionBindings);

            $seatCounts = ['parlimen' => 0, 'kadun' => 0, 'daerah_mengundi' => 0, 'lokaliti' => 0];
            if ($hasBatch) {
                $row = (clone $this->rollQuery($f))->selectRaw("
                    COUNT(DISTINCT NULLIF(parlimen, '')) AS parlimen,
                    COUNT(DISTINCT NULLIF(kadun, '')) AS kadun,
                    COUNT(DISTINCT NULLIF(daerah_mengundi, '')) AS daerah_mengundi,
                    COUNT(DISTINCT NULLIF(lokaliti, '')) AS lokaliti
                ")->first();
                $seatCounts = [
                    'parlimen' => (int) $row->parlimen,
                    'kadun' => (int) $row->kadun,
                    'daerah_mengundi' => (int) $row->daerah_mengundi,
                    'lokaliti' => (int) $row->lokaliti,
                ];
            }

            $pct = fn ($n) => $canvassed > 0 ? round(($n / $canvassed) * 100, 1) : 0;

            return [
                'empty_roll' => ! $hasBatch || $rollTotal === 0,
                'roll_total' => $rollTotal,
                'canvassed' => $canvassed,
                'covered' => (int) $covered,
                'coverage_pct' => $rollTotal > 0 ? round(($covered / $rollTotal) * 100, 1) : 0,
                'putih' => (int) $colors->putih,
                'hitam' => (int) $colors->hitam,
                'kelabu' => (int) $colors->kelabu,
                'putih_pct' => $pct((int) $colors->putih),
                'hitam_pct' => $pct((int) $colors->hitam),
                'kelabu_pct' => $pct((int) $colors->kelabu),
                'growth_recent_30d' => (int) ($growth->recent ?? 0),
                'growth_prior_30d' => (int) ($growth->prior ?? 0),
                'seats' => $seatCounts,
                'last_canvass_at' => $colors->last_canvass_at,
            ];
        });
    }

    public function composition(array $f): array
    {
        return $this->remember('composition', $f, function () use ($f) {
            $hasBatch = $this->activeBatchIds() !== [];

            $ageBands = [];
            $genderPyramid = [];
            $race = ['Melayu' => 0, 'Cina' => 0, 'India' => 0, 'Lain-lain' => 0];
            $gender = ['L' => 0, 'P' => 0];

            if ($hasBatch) {
                $ageExpr = $this->rollAgeExpr();
                $bandSelects = [];
                foreach (self::AGE_BANDS as $i => $band) {
                    $bandSelects[] = "COALESCE(SUM({$this->rollAgeGuard()} AND {$ageExpr} BETWEEN {$band['min']} AND {$band['max']}), 0) AS band_{$i}";
                }
                $bandRow = (clone $this->rollQuery($f))
                    ->selectRaw(implode(', ', $bandSelects))
                    ->first();

                foreach (self::AGE_BANDS as $i => $band) {
                    $ageBands[] = ['band' => $band['label'], 'jumlah' => (int) $bandRow->{"band_{$i}"}];
                }

                // Overall gender totals — independent of age validity so
                // voters with missing tahun_lahir still count.
                $genderRows = (clone $this->rollQuery($f))
                    ->selectRaw("{$this->genderExpr()} AS g, COUNT(*) AS jumlah")
                    ->groupBy('g')
                    ->havingRaw('g IS NOT NULL')
                    ->get();
                foreach ($genderRows as $row) {
                    $gender[$row->g] = (int) $row->jumlah;
                }

                // Gender pyramid: band × gender in one grouped query
                $pyramidRows = (clone $this->rollQuery($f))
                    ->selectRaw('
                        CASE '.collect(self::AGE_BANDS)->map(
                        fn ($b) => "WHEN {$this->rollAgeGuard()} AND {$ageExpr} BETWEEN {$b['min']} AND {$b['max']} THEN '{$b['label']}'"
                    )->implode(' ')." ELSE NULL END AS band,
                        {$this->genderExpr()} AS g,
                        COUNT(*) AS jumlah
                    ")
                    ->groupBy('band', 'g')
                    ->havingRaw('band IS NOT NULL AND g IS NOT NULL')
                    ->get();

                $byBand = [];
                foreach ($pyramidRows as $row) {
                    $byBand[$row->band][$row->g] = (int) $row->jumlah;
                }
                foreach (self::AGE_BANDS as $band) {
                    $genderPyramid[] = [
                        'band' => $band['label'],
                        // L negated so recharts renders a left-facing pyramid bar
                        'lelaki' => -($byBand[$band['label']]['L'] ?? 0),
                        'perempuan' => $byBand[$band['label']]['P'] ?? 0,
                    ];
                }

                $raceRows = (clone $this->rollQuery($f))
                    ->select('bangsa', DB::raw('COUNT(*) AS jumlah'))
                    ->groupBy('bangsa')
                    ->get();
                foreach ($raceRows as $row) {
                    $race[$this->bangsaBucket($row->bangsa)] += (int) $row->jumlah;
                }
            }

            // Canvass-side: age band × voter color (sentiment by generation)
            $bandCase = 'CASE '.collect(self::AGE_BANDS)->map(
                fn ($b) => "WHEN c.umur BETWEEN {$b['min']} AND {$b['max']} THEN '{$b['label']}'"
            )->implode(' ').' ELSE NULL END';

            [$dedupSql, $dedupBindings] = $this->canvassDedupedSql($f);
            $ageColorRows = DB::select("
                SELECT {$bandCase} AS band,
                       {$this->colorSumsSql()}
                  FROM ({$dedupSql}) c
                 GROUP BY band
                HAVING band IS NOT NULL
            ", $dedupBindings);

            $ageColorMap = collect($ageColorRows)->keyBy('band');
            $canvassAgeColor = collect(self::AGE_BANDS)->map(fn ($b) => [
                'band' => $b['label'],
                'putih' => (int) ($ageColorMap[$b['label']]->putih ?? 0),
                'hitam' => (int) ($ageColorMap[$b['label']]->hitam ?? 0),
                'kelabu' => (int) ($ageColorMap[$b['label']]->kelabu ?? 0),
            ])->values()->all();

            return [
                'ageBands' => $ageBands,
                'race' => collect($race)->map(fn ($v, $k) => ['bangsa' => $k, 'jumlah' => $v])->values()->all(),
                'gender' => [
                    ['jantina' => 'Lelaki', 'jumlah' => $gender['L']],
                    ['jantina' => 'Perempuan', 'jumlah' => $gender['P']],
                ],
                'genderPyramid' => $genderPyramid,
                'canvassAgeColor' => $canvassAgeColor,
            ];
        });
    }

    public function sentiment(array $f): array
    {
        return $this->remember('sentiment', $f, function () use ($f) {
            $overview = $this->overview($f);

            // Weekly trend over raw canvass records (not deduped — history
            // should reflect what was recorded each week). Geo filters
            // only; the 12-week window is fixed.
            [$unionSql, $unionBindings] = $this->canvassUnionSql($f, false);
            $trendRows = DB::select("
                SELECT YEARWEEK(c.created_at, 3) AS minggu,
                       MIN(DATE(c.created_at)) AS tarikh,
                       COUNT(*) AS jumlah,
                       {$this->colorSumsSql()}
                  FROM ({$unionSql}) c
                 WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                 GROUP BY minggu
                 ORDER BY minggu
            ", $unionBindings);

            $weeklyTrend = collect($trendRows)->map(function ($row) {
                $total = max(1, (int) $row->jumlah);

                return [
                    'minggu' => date('d M', strtotime($row->tarikh)),
                    'putih' => (int) $row->putih,
                    'hitam' => (int) $row->hitam,
                    'kelabu' => (int) $row->kelabu,
                    'putih_pct' => round(($row->putih / $total) * 100, 1),
                    'hitam_pct' => round(($row->hitam / $total) * 100, 1),
                    'kelabu_pct' => round(($row->kelabu / $total) * 100, 1),
                ];
            })->values()->all();

            $kadunRows = $this->seatRollup($f, 'kadun');

            return [
                'donut' => [
                    ['name' => 'PH (Putih)', 'key' => 'putih', 'value' => $overview['putih']],
                    ['name' => 'Pembangkang (Hitam)', 'key' => 'hitam', 'value' => $overview['hitam']],
                    ['name' => 'Atas Pagar (Kelabu)', 'key' => 'kelabu', 'value' => $overview['kelabu']],
                ],
                'totals' => $overview,
                'weeklyTrend' => $weeklyTrend,
                'kadunHeatRows' => $kadunRows,
            ];
        });
    }

    public function seatScores(array $f): array
    {
        return $this->remember('seatScores', $f, function () use ($f) {
            return [
                'parlimen' => $this->seatRollup($f, 'parlimen'),
                'kadun' => $this->seatRollup($f, 'kadun'),
            ];
        });
    }

    public function battlefield(array $f): array
    {
        return $this->remember('battlefield', $f, function () use ($f) {
            $kadunRows = collect($this->seatRollup($f, 'kadun'));

            // Youth (18-29) roll share per kadun — keyed via nameKey()
            // because DPT names are uppercase but seat names may come
            // from mixed-case canvass rows.
            $youthByKadun = [];
            if ($this->activeBatchIds() !== []) {
                $ageExpr = $this->rollAgeExpr();
                $youthRows = (clone $this->rollQuery($f))
                    ->selectRaw("kadun, COUNT(*) AS total,
                        COALESCE(SUM({$this->rollAgeGuard()} AND {$ageExpr} BETWEEN 18 AND 29), 0) AS youth")
                    ->whereNotNull('kadun')->where('kadun', '!=', '')
                    ->groupBy('kadun')
                    ->get();
                foreach ($youthRows as $row) {
                    $youthByKadun[$this->nameKey($row->kadun)] = $row->total > 0
                        ? round(($row->youth / $row->total) * 100, 1) : 0;
                }
            }

            $withYouth = $kadunRows->map(function ($row) use ($youthByKadun) {
                $row['youth_pct'] = $youthByKadun[$this->nameKey($row['name'])] ?? 0;

                return $row;
            });

            $scored = $withYouth->filter(fn ($r) => ! $r['low_data']);

            return [
                'topSwing' => $scored->sortBy(fn ($r) => abs($r['score'] - 50))->take(10)->values()->all(),
                'vulnerable' => $scored->whereBetween('score', [35, 54])->sortBy('score')->take(10)->values()->all(),
                'fenceSitters' => $withYouth->sortByDesc('kelabu')->take(10)->values()->all(),
                'youthSeats' => $withYouth->sortByDesc('youth_pct')->take(10)->values()->all(),
                'lowCoverage' => $withYouth->where('roll_total', '>', 0)->sortBy('coverage_pct')->take(10)->values()->all(),
            ];
        });
    }

    /**
     * Two-window (recent 30d vs prior 30d) sentiment shares per kadun —
     * feeds the early-warning detector and the AI forecast payload.
     * Result keys are nameKey()-normalised; each window carries the
     * display name.
     */
    public function trendByKadun(array $f, int $days = 30): array
    {
        return $this->remember("trendByKadun{$days}", $f, function () use ($f, $days) {
            [$unionSql, $unionBindings] = $this->canvassUnionSql($f, false);
            $rows = DB::select("
                SELECT c.kadun,
                       (c.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)) AS recent,
                       COUNT(*) AS jumlah,
                       {$this->colorSumsSql()},
                       MAX(c.created_at) AS last_at
                  FROM ({$unionSql}) c
                 WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL ".($days * 2)." DAY)
                       AND c.kadun IS NOT NULL AND c.kadun <> ''
                 GROUP BY c.kadun, recent
            ", $unionBindings);

            $result = [];
            foreach ($rows as $row) {
                $key = $this->nameKey($row->kadun);
                $window = $row->recent ? 'recent' : 'prior';
                $total = max(1, (int) $row->jumlah);
                $result[$key]['name'] = $result[$key]['name'] ?? $row->kadun;
                $result[$key][$window] = [
                    'jumlah' => (int) $row->jumlah,
                    'putih_pct' => round(($row->putih / $total) * 100, 1),
                    'hitam_pct' => round(($row->hitam / $total) * 100, 1),
                    'kelabu_pct' => round(($row->kelabu / $total) * 100, 1),
                    'last_at' => $row->last_at,
                ];
            }

            return $result;
        });
    }

    /* ----------------------------------------------------------------
     |  What-If baseline
     * ---------------------------------------------------------------- */

    /**
     * Per-seat baseline payload for the client-side what-if model:
     * overall shares + ethnic and age sub-shares with their roll weights.
     */
    public function baseline(array $f): array
    {
        return $this->remember('baseline', $f, function () use ($f) {
            $seats = collect($this->seatRollup($f, 'kadun'))
                ->filter(fn ($r) => $r['canvassed'] > 0);

            [$dedupSql, $dedupBindings] = $this->canvassDedupedSql($f);

            // Canvass: kadun × bangsa bucket × color
            $ethnicRows = DB::select("
                SELECT c.kadun, c.bangsa,
                       COUNT(*) AS jumlah,
                       {$this->colorSumsSql()}
                  FROM ({$dedupSql}) c
                 WHERE c.kadun IS NOT NULL AND c.kadun <> ''
                 GROUP BY c.kadun, c.bangsa
            ", $dedupBindings);

            // Canvass: kadun × 3 broad age bands × color
            $ageCase = "CASE WHEN c.umur BETWEEN 18 AND 29 THEN 'b18_29'
                             WHEN c.umur BETWEEN 30 AND 49 THEN 'b30_49'
                             WHEN c.umur >= 50 THEN 'b50plus' ELSE NULL END";
            $ageRows = DB::select("
                SELECT c.kadun, {$ageCase} AS band,
                       COUNT(*) AS jumlah,
                       {$this->colorSumsSql()}
                  FROM ({$dedupSql}) c
                 WHERE c.kadun IS NOT NULL AND c.kadun <> ''
                 GROUP BY c.kadun, band
                HAVING band IS NOT NULL
            ", $dedupBindings);

            // Roll weights: ethnic + age band shares per kadun.
            // ALL maps below are keyed via nameKey() so uppercase DPT
            // names merge with mixed-case canvass names.
            $rollEthnic = [];
            $rollAge = [];
            if ($this->activeBatchIds() !== []) {
                $rows = (clone $this->rollQuery($f))
                    ->selectRaw('kadun, bangsa, COUNT(*) AS jumlah')
                    ->whereNotNull('kadun')->where('kadun', '!=', '')
                    ->groupBy('kadun', 'bangsa')
                    ->get();
                foreach ($rows as $row) {
                    $key = $this->nameKey($row->kadun);
                    $bucket = strtolower($this->bangsaBucket($row->bangsa));
                    $bucket = $bucket === 'lain-lain' ? 'lain' : $bucket;
                    $rollEthnic[$key][$bucket] = ($rollEthnic[$key][$bucket] ?? 0) + (int) $row->jumlah;
                }

                $ageExpr = $this->rollAgeExpr();
                $rollAgeRows = (clone $this->rollQuery($f))
                    ->selectRaw("kadun,
                        CASE WHEN {$this->rollAgeGuard()} AND {$ageExpr} BETWEEN 18 AND 29 THEN 'b18_29'
                             WHEN {$this->rollAgeGuard()} AND {$ageExpr} BETWEEN 30 AND 49 THEN 'b30_49'
                             WHEN {$this->rollAgeGuard()} AND {$ageExpr} >= 50 THEN 'b50plus'
                             ELSE NULL END AS band,
                        COUNT(*) AS jumlah")
                    ->whereNotNull('kadun')->where('kadun', '!=', '')
                    ->groupBy('kadun', 'band')
                    ->havingRaw('band IS NOT NULL')
                    ->get();
                foreach ($rollAgeRows as $row) {
                    $rollAge[$this->nameKey($row->kadun)][$row->band] = (int) $row->jumlah;
                }
            }

            $ethnicByKadun = [];
            foreach ($ethnicRows as $row) {
                $key = $this->nameKey($row->kadun);
                $bucket = strtolower($this->bangsaBucket($row->bangsa));
                $bucket = $bucket === 'lain-lain' ? 'lain' : $bucket;
                $agg = $ethnicByKadun[$key][$bucket] ?? ['jumlah' => 0, 'putih' => 0, 'hitam' => 0, 'kelabu' => 0];
                $agg['jumlah'] += (int) $row->jumlah;
                $agg['putih'] += (int) $row->putih;
                $agg['hitam'] += (int) $row->hitam;
                $agg['kelabu'] += (int) $row->kelabu;
                $ethnicByKadun[$key][$bucket] = $agg;
            }

            $ageByKadun = [];
            foreach ($ageRows as $row) {
                $ageByKadun[$this->nameKey($row->kadun)][$row->band] = [
                    'jumlah' => (int) $row->jumlah,
                    'putih' => (int) $row->putih,
                    'hitam' => (int) $row->hitam,
                    'kelabu' => (int) $row->kelabu,
                ];
            }

            $shareTriple = function (array $agg): array {
                $total = max(1, $agg['jumlah']);

                return [
                    'putih' => round($agg['putih'] / $total, 4),
                    'hitam' => round($agg['hitam'] / $total, 4),
                    'kelabu' => round($agg['kelabu'] / $total, 4),
                ];
            };

            $payload = $seats->map(function ($seat) use ($ethnicByKadun, $ageByKadun, $rollEthnic, $rollAge, $shareTriple) {
                $key = $this->nameKey($seat['name']);

                $byEthnic = [];
                $rollEthnicTotal = max(1, array_sum($rollEthnic[$key] ?? []));
                foreach (['melayu', 'cina', 'india', 'lain'] as $bucket) {
                    $agg = $ethnicByKadun[$key][$bucket] ?? null;
                    $byEthnic[$bucket] = [
                        'rollShare' => round(($rollEthnic[$key][$bucket] ?? 0) / $rollEthnicTotal, 4),
                        'shares' => $agg ? $shareTriple($agg) : null,
                        'canvassed' => $agg['jumlah'] ?? 0,
                    ];
                }

                $byAge = [];
                $rollAgeTotal = max(1, array_sum($rollAge[$key] ?? []));
                foreach (['b18_29', 'b30_49', 'b50plus'] as $band) {
                    $agg = $ageByKadun[$key][$band] ?? null;
                    $byAge[$band] = [
                        'rollShare' => round(($rollAge[$key][$band] ?? 0) / $rollAgeTotal, 4),
                        'shares' => $agg ? $shareTriple($agg) : null,
                        'canvassed' => $agg['jumlah'] ?? 0,
                    ];
                }

                return [
                    'name' => $seat['name'],
                    'parlimen' => $seat['parlimen'] ?? null,
                    'rollTotal' => $seat['roll_total'],
                    'canvassed' => $seat['canvassed'],
                    'coverage' => round(($seat['coverage_pct'] ?? 0) / 100, 4),
                    'shares' => [
                        'putih' => round(($seat['putih_pct'] ?? 0) / 100, 4),
                        'hitam' => round(($seat['hitam_pct'] ?? 0) / 100, 4),
                        'kelabu' => round(($seat['kelabu_pct'] ?? 0) / 100, 4),
                    ],
                    'score' => $seat['score'],
                    'category' => $seat['category'],
                    'lowData' => $seat['low_data'],
                    'byEthnic' => $byEthnic,
                    'byAge' => $byAge,
                ];
            })->values()->all();

            return [
                'seats' => $payload,
                'generatedAt' => now()->toIso8601String(),
            ];
        });
    }

    /* ----------------------------------------------------------------
     |  Shared seat rollup (Query A + Query B merged in PHP)
     * ---------------------------------------------------------------- */

    /**
     * Per-seat rollup grouped by `kadun` or `parlimen`. Exactly two
     * aggregate queries regardless of seat count. Roll and canvass rows
     * merge case-insensitively; the roll's (canonical DPT) name wins
     * for display.
     */
    private function seatRollup(array $f, string $groupBy): array
    {
        $hasBatch = $this->activeBatchIds() !== [];

        // Query A — roll totals + coverage, grouped by the ROLL's geography
        $rollRows = collect();
        if ($hasBatch) {
            $rollBuilder = $this->rollQuery($f)
                ->whereNotNull($groupBy)->where($groupBy, '!=', '');
            $selectCols = $groupBy === 'parlimen' ? ['no_ic', 'parlimen'] : ['no_ic', $groupBy, 'parlimen'];
            $rollSql = $rollBuilder->select($selectCols)->toSql();
            $rollBindings = $rollBuilder->getBindings();

            $rollRows = collect(DB::select("
                SELECT p.{$groupBy} AS name,
                       MAX(p.parlimen) AS parlimen,
                       COUNT(*) AS roll_total,
                       COALESCE(SUM(c.no_ic IS NOT NULL), 0) AS covered
                  FROM ({$rollSql}) p
                  LEFT JOIN (SELECT no_ic FROM hasil_culaan WHERE is_deceased = 0 AND no_ic <> ''
                             UNION
                             SELECT no_ic FROM data_pengundi WHERE is_deceased = 0 AND no_ic <> '') c
                    ON c.no_ic = p.no_ic
                 GROUP BY p.{$groupBy}
            ", $rollBindings));
        }

        // Query B — sentiment rollup over the deduped canvass union
        [$dedupSql, $dedupBindings] = $this->canvassDedupedSql($f);
        $colorRows = collect(DB::select("
            SELECT c.{$groupBy} AS name,
                   MAX(c.parlimen) AS parlimen,
                   COUNT(*) AS canvassed,
                   {$this->colorSumsSql()}
              FROM ({$dedupSql}) c
             WHERE c.{$groupBy} IS NOT NULL AND c.{$groupBy} <> ''
             GROUP BY c.{$groupBy}
        ", $dedupBindings));

        $rollByName = $rollRows->keyBy(fn ($r) => $this->nameKey($r->name));
        $colorByName = $colorRows->keyBy(fn ($r) => $this->nameKey($r->name));
        $keys = $rollByName->keys()->merge($colorByName->keys())->unique();

        return $keys->map(function ($key) use ($rollByName, $colorByName, $groupBy) {
            $roll = $rollByName->get($key);
            $color = $colorByName->get($key);

            return $this->health->decorate([
                'name' => $roll->name ?? $color->name,
                'type' => $groupBy,
                'parlimen' => $roll->parlimen ?? $color->parlimen ?? null,
                'roll_total' => (int) ($roll->roll_total ?? 0),
                'covered' => (int) ($roll->covered ?? 0),
                'canvassed' => (int) ($color->canvassed ?? 0),
                'putih' => (int) ($color->putih ?? 0),
                'hitam' => (int) ($color->hitam ?? 0),
                'kelabu' => (int) ($color->kelabu ?? 0),
            ]);
        })->sortByDesc('roll_total')->values()->all();
    }
}
