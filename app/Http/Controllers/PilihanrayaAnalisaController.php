<?php

namespace App\Http\Controllers;

use App\Models\AnalisaComparison;
use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Models\UploadBatch;
use App\Services\Pilihanraya\ElectionAnalyticsService;
use App\Support\Pilihanraya\JohorElectionData;
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
    private function context(): array
    {
        return [
            'dun' => JohorElectionData::DUN,
            'parlimen' => JohorElectionData::PARLIMEN,
            'negeri' => JohorElectionData::NEGERI,
            'kawasanList' => JohorElectionData::kawasanList(),
        ];
    }

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

    public function minima(Request $request)
    {
        return Inertia::render('Pilihanraya/Minima', [
            'context' => $this->context(),
            'minima' => JohorElectionData::minima(),
        ]);
    }

    public function kaumDm(Request $request)
    {
        return Inertia::render('Pilihanraya/KaumDm', [
            'context' => $this->context(),
            'rows' => JohorElectionData::kaumDm(),
            'totals' => JohorElectionData::kaumDmTotals(),
        ]);
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
