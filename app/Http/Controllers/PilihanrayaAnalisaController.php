<?php

namespace App\Http\Controllers;

use App\Models\AnalisaComparison;
use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Models\UploadBatch;
use App\Services\Pilihanraya\ElectionAnalyticsService;
use App\Support\Pilihanraya\ScoresheetParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Pilihanraya → Analisa. The Keputusan page is Malaysia-wide: the user picks a
 * kawasan (Negeri → Parlimen → optional DUN), then uploads scoresheets as
 * comparison scenarios for AI analysis. Minima / Kaum Mengikut DM remain the
 * curated Buloh Kasap pages.
 *
 * All routes sit inside the super_admin/admin pilihanraya group.
 */
class PilihanrayaAnalisaController extends Controller
{
    public function keputusan(Request $request, ElectionAnalyticsService $analytics)
    {
        $lists = $analytics->filterLists();

        return Inertia::render('Pilihanraya/Analisa', [
            'geo' => [
                'negeriList' => $lists['negeriList'],
                'parlimenList' => $lists['parlimenList'],
                'kadunList' => $lists['kadunList'],
            ],
            'savedComparisons' => $this->savedComparisons(),
        ]);
    }

    /** Compact list of saved comparisons (with scope) for the page dropdown. */
    private function savedComparisons(): array
    {
        return AnalisaComparison::withCount('scenarios')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'level' => $c->level,
                'negeri' => $c->negeri,
                'parlimen' => $c->parlimen,
                'dun' => $c->dun,
                'bandar_id' => $c->bandar_id,
                'kadun_id' => $c->kadun_id,
                'status' => $c->status,
                'ai_status' => $c->ai_status,
                'scenario_count' => $c->scenarios_count,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ])->all();
    }

    /**
     * "Minima Untuk PH Menang" — a what-if calculator. The three sensitivity
     * tables are computed live in the browser from the assumptions; the only
     * seat-specific facts are the Melayu/Cina/India voter counts, which now come
     * from the live roll (bangsa-first, same as Kaum Mengikut DM) for the chosen
     * seat instead of the hardcoded Buloh Kasap dataset. Turnout / support / 2022
     * figures stay editable assumptions with sensible seeds.
     */
    public function minima(Request $request)
    {
        $kawasanList = $this->kawasanListFromMaster();
        $selected = collect($kawasanList)->firstWhere('id', $request->query('kawasan'))
            ?: ($kawasanList[0] ?? null);

        [, $totals] = $selected
            ? $this->kaumDmForDun($selected['dun'])
            : [[], ['melayu' => 0, 'cina' => 0, 'india' => 0, 'lain' => 0, 'jumlah' => 0]];

        return Inertia::render('Pilihanraya/Minima', [
            'context' => [
                'dun' => $selected['dun'] ?? '—',
                'parlimen' => $selected['parlimen'] ?? '—',
                'negeri' => $selected['negeri'] ?? '—',
                'kawasanList' => $kawasanList,
                'selectedId' => $selected['id'] ?? null,
            ],
            'minima' => [
                'andaian' => [
                    // Seat-specific facts from the roll.
                    'pengundi_melayu' => $totals['melayu'],
                    'pengundi_cina' => $totals['cina'],
                    'pengundi_india' => $totals['india'],
                    // Editable assumptions (generic seeds).
                    'turnout_melayu' => 0.68,
                    'sokongan_ph_cina' => 0.90,
                    'sokongan_ph_india' => 0.40,
                    // Jadual 3 — 2022 result seeds; editable per seat (defaults
                    // are Buloh Kasap's actual 2022 tally, adjust for others).
                    'melayu_ph_2022' => 379,
                    'melayu_bn_2022' => 7933,
                    'undi_pn_2022' => 2999,
                ],
            ],
        ]);
    }

    /**
     * Kaum Mengikut DM — now driven by the live voter roll instead of the
     * curated Buloh Kasap dataset. The Parlimen → DUN picker is built from every
     * seat that actually has roll data; the race split per Daerah Mengundi is
     * ESTIMATED from voter-name patterns (BIN/BINTI = Melayu; A/L·A/P·S/O·D/O =
     * India; ANAK = Lain-lain; the rest = Cina) — the same method the page's
     * footnote already declares.
     */
    public function kaumDm(Request $request)
    {
        $kawasanList = $this->kawasanListFromMaster();
        $selected = collect($kawasanList)->firstWhere('id', $request->query('kawasan'))
            ?: ($kawasanList[0] ?? null);

        [$rows, $totals] = $selected
            ? $this->kaumDmForDun($selected['dun'])
            : [[], ['melayu' => 0, 'cina' => 0, 'india' => 0, 'lain' => 0, 'jumlah' => 0]];

        return Inertia::render('Pilihanraya/KaumDm', [
            'context' => [
                'dun' => $selected['dun'] ?? '—',
                'parlimen' => $selected['parlimen'] ?? '—',
                'negeri' => $selected['negeri'] ?? '—',
                'kawasanList' => $kawasanList,
                'selectedId' => $selected['id'] ?? null,
            ],
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    /** Roll predicate: active upload batches OR any DPT upload (matches the roll used elsewhere). */
    private function rollSource($query)
    {
        $activeIds = UploadBatch::activeIds();

        return $query->where('is_deceased', false)
            ->where(fn ($w) => $w->whereIn('upload_batch_id', $activeIds ?: [-1])->orWhereNotNull('dpt_upload_id'));
    }

    /**
     * The Parlimen → DUN picker, built from the clean MASTER hierarchy
     * (Negeri → Bandar = Parlimen → Kadun = DUN) — the same source the War Room
     * uses. Deriving it from the raw roll columns is wrong: a mis-imported batch
     * (e.g. an OKU list whose `parlimen` column holds polling-station names)
     * would pollute the dropdown. The roll is matched to the selected DUN by
     * name in kaumDmForDun(), so a scrambled batch simply never matches.
     */
    private function kawasanListFromMaster(): array
    {
        return Cache::remember('kaumdm:kawasan_master', 300, function () {
            $bandars = Bandar::get(['id', 'nama', 'negeri_id'])->keyBy('id');
            $negeris = \App\Models\Negeri::get(['id', 'nama'])->keyBy('id');

            return Kadun::orderBy('nama')->get(['id', 'nama', 'bandar_id'])
                ->map(function ($k) use ($bandars, $negeris) {
                    $b = $bandars[$k->bandar_id] ?? null;
                    $n = $b ? ($negeris[$b->negeri_id] ?? null) : null;

                    return [
                        'id' => (string) $k->id,
                        'kod' => $k->nama,
                        'dun' => $k->nama,
                        'parlimen' => $b?->nama ?? '—',
                        'negeri' => $n?->nama ?? '—',
                        'label' => $k->nama,
                    ];
                })
                ->sortBy(fn ($k) => $k['parlimen'].'|'.$k['dun'])
                ->values()->all();
        });
    }

    /**
     * Race split per Daerah Mengundi for one DUN, estimated from name patterns.
     * @return array{0:array<int,array<string,mixed>>, 1:array<string,int>}
     */
    private function kaumDmForDun(string $dun): array
    {
        $upperDun = mb_strtoupper(trim($dun));
        $cacheKey = 'kaumdm:rows:'.md5(implode(',', UploadBatch::activeIds()).'|'.$upperDun);

        return Cache::remember($cacheKey, 300, function () use ($upperDun) {
            // Prefer the roll's actual Race column (`bangsa`, from DPPR exports).
            // Only when it is blank do we fall back to the name-pattern estimate
            // (BIN/BINTI/BT/BTE = Melayu; A/L·A/P·S/O·D/O = India; ANAK = Lain;
            // else = Cina). $class resolves each voter to M/C/I/L.
            $b = "UPPER(TRIM(COALESCE(bangsa, '')))";
            $pad = "CONCAT(' ', UPPER(nama), ' ')";
            $nMelayu = "({$pad} LIKE '% BIN %' OR {$pad} LIKE '% BINTI %' OR {$pad} LIKE '% BT %' OR {$pad} LIKE '% BTE %')";
            $nIndia = "(UPPER(nama) LIKE '%A/L%' OR UPPER(nama) LIKE '%A/P%' OR UPPER(nama) LIKE '%S/O%' OR UPPER(nama) LIKE '%D/O%')";
            $nLain = "({$pad} LIKE '% ANAK %')";

            $class = "CASE
                WHEN {$b} IN ('MELAYU', 'MALAY') THEN 'M'
                WHEN {$b} IN ('CINA', 'CHINESE') THEN 'C'
                WHEN {$b} IN ('INDIA', 'INDIAN') THEN 'I'
                WHEN {$b} <> '' THEN 'L'
                WHEN {$nMelayu} THEN 'M'
                WHEN {$nIndia} THEN 'I'
                WHEN {$nLain} THEN 'L'
                ELSE 'C'
            END";

            $grouped = $this->rollSource(DB::table('pangkalan_data_pengundi'))
                ->whereRaw('UPPER(kadun) = ?', [$upperDun])
                ->selectRaw("
                    COALESCE(NULLIF(TRIM(daerah_mengundi), ''), '(Tiada DM)') AS dm,
                    SUM(CASE WHEN ({$class}) = 'M' THEN 1 ELSE 0 END) AS melayu,
                    SUM(CASE WHEN ({$class}) = 'I' THEN 1 ELSE 0 END) AS india,
                    SUM(CASE WHEN ({$class}) = 'L' THEN 1 ELSE 0 END) AS lain,
                    COUNT(*) AS jumlah
                ")
                ->groupBy('dm')
                ->orderByDesc('jumlah')
                ->get();

            $rows = $grouped->values()->map(function ($r, $i) {
                $melayu = (int) $r->melayu;
                $india = (int) $r->india;
                $lain = (int) $r->lain;
                $jumlah = (int) $r->jumlah;

                return [
                    'bil' => $i + 1,
                    'dm' => $r->dm,
                    'melayu' => $melayu,
                    'cina' => max(0, $jumlah - $melayu - $india - $lain), // Cina = residual
                    'india' => $india,
                    'lain' => $lain,
                    'jumlah' => $jumlah,
                ];
            })->all();

            $totals = [
                'melayu' => array_sum(array_column($rows, 'melayu')),
                'cina' => array_sum(array_column($rows, 'cina')),
                'india' => array_sum(array_column($rows, 'india')),
                'lain' => array_sum(array_column($rows, 'lain')),
                'jumlah' => array_sum(array_column($rows, 'jumlah')),
            ];

            return [$rows, $totals];
        });
    }

    /**
     * Parse an uploaded scoresheet (xlsx/xls/csv) and return both the faithful
     * raw grid and, when the expected columns are recognised, a normalised
     * result set.
     */
    public function upload(Request $request)
    {
        $request->validate([
            // 'txt' included because a .csv is frequently mime-guessed as text/plain.
            'fail' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ]);

        return response()->json(ScoresheetParser::parse($request->file('fail')));
    }

    /**
     * Keanggotaan (membership) card — member counts for the selected kawasan
     * (DUN or whole Parlimen), broken down by DM / Lokaliti, matched against the
     * DPPR roll (matched_* columns) and culaan (is_dicula). Scope comes from the
     * page's Negeri → Parlimen → DUN picker (bandar_id / kadun_id / level).
     */
    public function keanggotaanCard(Request $request)
    {
        $level = $request->query('level') === 'parlimen' ? 'parlimen' : 'dun';
        $bandar = Bandar::find($request->query('bandar_id'));
        $kadun = $level === 'dun' ? Kadun::find($request->query('kadun_id')) : null;
        $batchIds = KeanggotaanBatch::activeIds();

        $scope = ['level' => $level, 'parlimen' => $bandar?->nama, 'dun' => $kadun?->nama];

        if (! $bandar || ($level === 'dun' && ! $kadun) || empty($batchIds)) {
            return response()->json([
                'no_batch' => true,
                'scope' => $scope,
                'summary' => null,
                'byDm' => [],
                'byLokaliti' => [],
                'ageBands' => [],
            ]);
        }

        // Scope predicates by level: DUN keys on kadun name, Parlimen on the
        // parliament name (roll + members carry denormalised name strings).
        if ($level === 'dun') {
            $upper = mb_strtoupper($kadun->nama);
            $memberScope = fn ($q) => $q->whereRaw('UPPER(matched_kadun) = ?', [$upper]);
            $rollScope = fn ($q) => $q->whereRaw('UPPER(kadun) = ?', [$upper]);
            $luarCol = 'dun';
        } else {
            $upper = mb_strtoupper($bandar->nama);
            $memberScope = fn ($q) => $q->whereRaw('UPPER(matched_parlimen) LIKE ?', ['%'.$upper.'%']);
            $rollScope = fn ($q) => $q->whereRaw('UPPER(parlimen) LIKE ?', ['%'.$upper.'%']);
            $luarCol = 'cabang';
        }

        $cacheKey = 'analisa:keanggotaan:'.md5($level.'|'.($kadun?->id ?? $bandar->id).'|'.implode(',', $batchIds));

        return response()->json(Cache::remember($cacheKey, 60, function () use ($scope, $batchIds, $memberScope, $rollScope, $luarCol, $upper) {
            $member = fn () => $memberScope(Keanggotaan::whereIn('batch_id', $batchIds));

            $agg = (clone $member())->selectRaw('COUNT(*) AS anggota, COALESCE(SUM(is_dicula), 0) AS dicula')->first();
            $anggota = (int) ($agg->anggota ?? 0);
            $dicula = (int) ($agg->dicula ?? 0);

            // Members matched to this kawasan by name but not found in the roll.
            $luar = Keanggotaan::whereIn('batch_id', $batchIds)
                ->whereRaw("UPPER(COALESCE({$luarCol}, '')) LIKE ?", ['%'.$upper.'%'])
                ->whereIn('status_kawasan', ['luar_kawasan', 'tiada_dppr'])
                ->count();

            // DPPR roll (active batches OR DPT uploads), grouped by DM.
            $activeRollIds = UploadBatch::activeIds();
            $rollRows = $rollScope(DB::table('pangkalan_data_pengundi')->where('is_deceased', false))
                ->where(function ($w) use ($activeRollIds) {
                    $w->whereIn('upload_batch_id', $activeRollIds ?: [-1])->orWhereNotNull('dpt_upload_id');
                })
                ->selectRaw('daerah_mengundi, COUNT(*) AS jumlah')
                ->groupBy('daerah_mengundi')
                ->get();
            $rollTotal = (int) $rollRows->sum('jumlah');
            $rollByDm = $rollRows->keyBy(fn ($r) => ElectionAnalyticsService::nameKey($r->daerah_mengundi));

            $dmRows = (clone $member())
                ->selectRaw('matched_daerah_mengundi AS dm, COUNT(*) AS anggota, COALESCE(SUM(is_dicula), 0) AS dicula')
                ->whereNotNull('matched_daerah_mengundi')
                ->where('matched_daerah_mengundi', '!=', '')
                ->groupBy('matched_daerah_mengundi')
                ->orderByDesc('anggota')
                ->get()
                ->map(function ($r) use ($rollByDm) {
                    $roll = (int) ($rollByDm[ElectionAnalyticsService::nameKey($r->dm)]->jumlah ?? 0);

                    return [
                        'nama' => $r->dm,
                        'anggota' => (int) $r->anggota,
                        'dicula' => (int) $r->dicula,
                        'roll' => $roll,
                        'pct_penetrasi' => $roll > 0 ? round((int) $r->anggota / $roll * 100, 1) : null,
                    ];
                })->all();

            $lokRows = (clone $member())
                ->selectRaw('matched_daerah_mengundi AS dm, matched_lokaliti AS nama, COUNT(*) AS anggota, COALESCE(SUM(is_dicula), 0) AS dicula')
                ->whereNotNull('matched_lokaliti')
                ->where('matched_lokaliti', '!=', '')
                ->groupBy('matched_daerah_mengundi', 'matched_lokaliti')
                ->orderByDesc('anggota')
                ->limit(30)
                ->get()
                ->map(fn ($r) => [
                    'dm' => $r->dm,
                    'nama' => $r->nama,
                    'anggota' => (int) $r->anggota,
                    'dicula' => (int) $r->dicula,
                ])->all();

            $bandCase = collect(ElectionAnalyticsService::AGE_BANDS)
                ->map(fn ($b, $i) => "COALESCE(SUM(umur BETWEEN {$b['min']} AND {$b['max']}), 0) AS band_{$i}")
                ->implode(', ');
            $bandRow = (clone $member())->selectRaw($bandCase)->first();
            $ageBands = collect(ElectionAnalyticsService::AGE_BANDS)
                ->map(fn ($b, $i) => ['label' => $b['label'], 'anggota' => (int) ($bandRow->{"band_{$i}"} ?? 0)])
                ->all();

            return [
                'no_batch' => false,
                'scope' => $scope,
                'summary' => [
                    'anggota' => $anggota,
                    'dicula' => $dicula,
                    'pct_dicula' => $anggota > 0 ? round($dicula / $anggota * 100, 1) : 0,
                    'luar_kawasan' => $luar,
                    'roll' => $rollTotal,
                    'pct_penetrasi' => $rollTotal > 0 ? round($anggota / $rollTotal * 100, 1) : null,
                ],
                'byDm' => $dmRows,
                'byLokaliti' => $lokRows,
                'ageBands' => $ageBands,
            ];
        }));
    }
}
