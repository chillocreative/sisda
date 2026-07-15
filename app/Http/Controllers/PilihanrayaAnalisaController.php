<?php

namespace App\Http\Controllers;

use App\Models\AnalisaComparison;
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
 * Pilihanraya → Analisa. Serves the Buloh Kasap analytical pages (Keputusan
 * 2022, Minima, Kaum Mengikut DM) and parses an uploaded scoresheet on demand.
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

    public function keputusan(Request $request)
    {
        return Inertia::render('Pilihanraya/Analisa', [
            'context' => $this->context(),
            'rows' => JohorElectionData::keputusan2022(),
            'totals' => JohorElectionData::keputusan2022Totals(),
            'savedComparisons' => $this->savedComparisons(),
        ]);
    }

    /** Compact list of saved comparisons for the page's "open saved" dropdown. */
    private function savedComparisons(): array
    {
        return AnalisaComparison::withCount('scenarios')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'dun' => $c->dun,
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
     * result set that the Keputusan table + charts can render directly.
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
     * Keanggotaan (membership) card for the Analisa page — member counts for the
     * selected DUN, broken down by DM / Lokaliti, matched against the DPPR roll
     * (matched_* columns filled by MemberMatchService) and culaan (is_dicula).
     * Follows the page's Parlimen→DUN filter via the `dun` query param.
     */
    public function keanggotaanCard(Request $request)
    {
        $dun = trim((string) $request->query('dun', ''));
        $kadunName = $this->stripCode($dun);
        $batchIds = KeanggotaanBatch::activeIds();

        if ($dun === '' || empty($batchIds)) {
            return response()->json([
                'no_batch' => true,
                'scope' => ['dun' => $dun],
                'summary' => null,
                'byDm' => [],
                'byLokaliti' => [],
                'ageBands' => [],
            ]);
        }

        $cacheKey = 'analisa:keanggotaan:'.md5($dun.'|'.implode(',', $batchIds));

        return response()->json(Cache::remember($cacheKey, 60, function () use ($dun, $kadunName, $batchIds) {
            $upperKadun = mb_strtoupper($kadunName);
            $member = fn () => Keanggotaan::whereIn('batch_id', $batchIds)
                ->whereRaw('UPPER(matched_kadun) = ?', [$upperKadun]);

            // Summary — matched members in this DUN.
            $agg = (clone $member())->selectRaw('
                COUNT(*) AS anggota,
                COALESCE(SUM(is_dicula), 0) AS dicula
            ')->first();
            $anggota = (int) ($agg->anggota ?? 0);
            $dicula = (int) ($agg->dicula ?? 0);

            // Members whose file DUN matches but who are not found in the DPPR roll.
            $luar = Keanggotaan::whereIn('batch_id', $batchIds)
                ->whereRaw('UPPER(COALESCE(dun, "")) LIKE ?', ['%'.$upperKadun.'%'])
                ->whereIn('status_kawasan', ['luar_kawasan', 'tiada_dppr'])
                ->count();

            // DPPR roll count for this DUN (active batches OR DPT uploads).
            $activeRollIds = UploadBatch::activeIds();
            $rollRows = DB::table('pangkalan_data_pengundi')
                ->where('is_deceased', false)
                ->whereRaw('UPPER(kadun) = ?', [$upperKadun])
                ->where(function ($w) use ($activeRollIds) {
                    $w->whereIn('upload_batch_id', $activeRollIds ?: [-1])->orWhereNotNull('dpt_upload_id');
                })
                ->selectRaw('daerah_mengundi, COUNT(*) AS jumlah')
                ->groupBy('daerah_mengundi')
                ->get();
            $rollTotal = (int) $rollRows->sum('jumlah');
            $rollByDm = $rollRows->keyBy(fn ($r) => ElectionAnalyticsService::nameKey($r->daerah_mengundi));

            // By Daerah Mengundi.
            $dmRows = (clone $member())
                ->selectRaw('matched_daerah_mengundi AS dm,
                    COUNT(*) AS anggota,
                    COALESCE(SUM(is_dicula), 0) AS dicula')
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

            // By Lokaliti (top 30).
            $lokRows = (clone $member())
                ->selectRaw('matched_daerah_mengundi AS dm, matched_lokaliti AS nama,
                    COUNT(*) AS anggota,
                    COALESCE(SUM(is_dicula), 0) AS dicula')
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

            // Age bands over the members' umur.
            $bandCase = collect(ElectionAnalyticsService::AGE_BANDS)
                ->map(fn ($b, $i) => "COALESCE(SUM(umur BETWEEN {$b['min']} AND {$b['max']}), 0) AS band_{$i}")
                ->implode(', ');
            $bandRow = (clone $member())->selectRaw($bandCase)->first();
            $ageBands = collect(ElectionAnalyticsService::AGE_BANDS)
                ->map(fn ($b, $i) => ['label' => $b['label'], 'anggota' => (int) ($bandRow->{"band_{$i}"} ?? 0)])
                ->all();

            return [
                'no_batch' => false,
                'scope' => ['dun' => $dun],
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

    /** Strip a leading DUN code ("N01 BULOH KASAP" → "BULOH KASAP"). */
    private function stripCode(string $dun): string
    {
        return trim(preg_replace('/^[A-Z]\d+\s+/i', '', $dun));
    }
}
