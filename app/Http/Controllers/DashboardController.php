<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Mpkk;
use App\Models\Negeri;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use App\Models\User;
use App\Services\VoterDataMasker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Show simplified dashboard for Admin, Super User and Regular users
        if ($user->isAdmin() || $user->isSuperUser() || $user->isUser()) {
            return Inertia::render('Dashboard/UserDashboard');
        }

        // Full dashboard for Super Admin
        // Get filter parameters
        $negeriId = $request->input('negeri_id');
        $bandarId = $request->input('bandar_id');
        $kadunId = $request->input('kadun_id');
        $mpkkId = $request->input('mpkk_id');
        $tarikhDari = $request->input('tarikh_dari');
        $tarikhHingga = $request->input('tarikh_hingga');

        // Convert IDs to names (since database stores names as strings)
        $negeriNama = $negeriId ? Negeri::find($negeriId)?->nama : null;
        $bandarNama = $bandarId ? Bandar::find($bandarId)?->nama : null;
        $kadunNama = $kadunId ? Kadun::find($kadunId)?->nama : null;
        $mpkkNama = $mpkkId ? Mpkk::find($mpkkId)?->nama : null;

        // Base queries
        $pengundiQuery = DataPengundi::query();
        $culaanQuery = HasilCulaan::query();

        // Apply territory filtering for Admin and User (Super Admin sees all)
        if (! $user->isSuperAdmin()) {
            if ($user->negeri_id) {
                $pengundiQuery->where('negeri', $user->negeri->nama ?? '');
                $culaanQuery->where('negeri', $user->negeri->nama ?? '');
            }
            if ($user->bandar_id) {
                $pengundiQuery->where('bandar', $user->bandar->nama ?? '');
                $culaanQuery->where('bandar', $user->bandar->nama ?? '');
            }
            if ($user->kadun_id) {
                $pengundiQuery->where('kadun', $user->kadun->nama ?? '');
                $culaanQuery->where('kadun', $user->kadun->nama ?? '');
            }
        }

        // Apply additional filters from request
        if ($negeriNama) {
            $pengundiQuery->where('negeri', $negeriNama);
            $culaanQuery->where('negeri', $negeriNama);
        }
        if ($bandarNama) {
            $pengundiQuery->where('bandar', $bandarNama);
            $culaanQuery->where('bandar', $bandarNama);
        }
        if ($kadunNama) {
            $pengundiQuery->where('kadun', $kadunNama);
            $culaanQuery->where('kadun', $kadunNama);
        }
        if ($tarikhDari) {
            $pengundiQuery->whereDate('created_at', '>=', $tarikhDari);
            $culaanQuery->whereDate('created_at', '>=', $tarikhDari);
        }
        if ($tarikhHingga) {
            $pengundiQuery->whereDate('created_at', '<=', $tarikhHingga);
            $culaanQuery->whereDate('created_at', '<=', $tarikhHingga);
        }

        // Voter-roll base query: the authoritative registered-voter list (active
        // DPPR batches + DPT rows, excluding deceased), scoped to the same
        // territory filters (the roll uses `parlimen` for bandar, matched
        // case-insensitively). Date filters are canvass-activity only, so they
        // are deliberately NOT applied to the roll.
        $rollBase = function () use ($user, $negeriNama, $bandarNama, $kadunNama) {
            $activeIds = UploadBatch::activeIds();
            $hasDpt = Schema::hasColumn('pangkalan_data_pengundi', 'dpt_upload_id');
            $q = PangkalanDataPengundi::where('is_deceased', false)
                ->where(function ($w) use ($activeIds, $hasDpt) {
                    $w->whereIn('upload_batch_id', $activeIds ?: [-1]);
                    if ($hasDpt) {
                        $w->orWhereNotNull('dpt_upload_id');
                    }
                });

            if (! $user->isSuperAdmin()) {
                if ($user->negeri_id) {
                    $q->whereRaw('UPPER(negeri) = ?', [strtoupper((string) ($user->negeri->nama ?? ''))]);
                }
                if ($user->bandar_id) {
                    $q->whereRaw('UPPER(parlimen) = ?', [strtoupper((string) ($user->bandar->nama ?? ''))]);
                }
                if ($user->kadun_id) {
                    $q->whereRaw('UPPER(kadun) = ?', [strtoupper((string) ($user->kadun->nama ?? ''))]);
                }
            }
            if ($negeriNama) {
                $q->whereRaw('UPPER(negeri) = ?', [strtoupper($negeriNama)]);
            }
            if ($bandarNama) {
                $q->whereRaw('UPPER(parlimen) = ?', [strtoupper($bandarNama)]);
            }
            if ($kadunNama) {
                $q->whereRaw('UPPER(kadun) = ?', [strtoupper($kadunNama)]);
            }

            return $q;
        };

        // Headline = real registered voters from the roll; culaan stays canvass.
        $totalPengundi = $rollBase()->count();
        $totalCulaan = (clone $culaanQuery)->where('is_deceased', false)->count();
        $deceasedPengundi = (clone $pengundiQuery)->where('is_deceased', true)->count();
        $deceasedCulaan = (clone $culaanQuery)->where('is_deceased', true)->count();

        // Create filtered query for political tendency
        $tendencyQuery = clone $pengundiQuery;
        $totalWithTendency = $tendencyQuery->whereNotNull('kecenderungan_politik')
            ->where('kecenderungan_politik', '!=', '')
            ->count();

        // Political tendency percentages (using filtered queries)
        $phQuery = clone $pengundiQuery;
        $phCount = $phQuery->where('kecenderungan_politik', 'like', '%PH%')->count();

        $bnQuery = clone $pengundiQuery;
        $bnCount = $bnQuery->where('kecenderungan_politik', 'like', '%BN%')->count();

        $pnQuery = clone $pengundiQuery;
        $pnCount = $pnQuery->where('kecenderungan_politik', 'like', '%PN%')->count();

        $tidakPastiQuery = clone $pengundiQuery;
        $tidakPastiCount = $tidakPastiQuery->where('kecenderungan_politik', 'like', '%TIDAK PASTI%')->count();

        $sokongan = [
            'ph' => $totalWithTendency > 0 ? round(($phCount / $totalWithTendency) * 100) : 0,
            'bn' => $totalWithTendency > 0 ? round(($bnCount / $totalWithTendency) * 100) : 0,
            'pn' => $totalWithTendency > 0 ? round(($pnCount / $totalWithTendency) * 100) : 0,
            'tidakPasti' => $totalWithTendency > 0 ? round(($tidakPastiCount / $totalWithTendency) * 100) : 0,
        ];

        // Bangsa distribution from the voter roll (case-insensitive buckets;
        // everything outside Melayu/Cina/India falls into "lain").
        $bangsaStats = $rollBase()
            ->whereNotNull('bangsa')->where('bangsa', '!=', '')
            ->selectRaw('UPPER(bangsa) as b, count(*) as jumlah')
            ->groupBy(DB::raw('UPPER(bangsa)'))
            ->pluck('jumlah', 'b');
        $melayu = (int) ($bangsaStats['MELAYU'] ?? 0);
        $cina = (int) ($bangsaStats['CINA'] ?? 0);
        $india = (int) ($bangsaStats['INDIA'] ?? 0);
        $bangsa = [
            'melayu' => $melayu,
            'cina' => $cina,
            'india' => $india,
            'lain' => max(0, (int) $bangsaStats->sum() - $melayu - $cina - $india),
        ];

        // Age distribution from the voter roll's birth year (the roll has
        // tahun_lahir, not umur). Only well-formed 4-digit years are counted.
        $ageBand = function ($lo, $hi) use ($rollBase) {
            $q = $rollBase()->whereRaw("tahun_lahir REGEXP '^[0-9]{4}$'");
            $age = '(YEAR(CURDATE()) - CAST(tahun_lahir AS UNSIGNED))';

            return $hi === null
                ? $q->whereRaw("{$age} > ?", [$lo])->count()
                : $q->whereRaw("{$age} BETWEEN ? AND ?", [$lo, $hi])->count();
        };
        $umurDistribution = [
            ['range' => '18-25', 'jumlah' => $ageBand(18, 25)],
            ['range' => '26-35', 'jumlah' => $ageBand(26, 35)],
            ['range' => '36-45', 'jumlah' => $ageBand(36, 45)],
            ['range' => '46-55', 'jumlah' => $ageBand(46, 55)],
            ['range' => '56-65', 'jumlah' => $ageBand(56, 65)],
            ['range' => '65+', 'jumlah' => $ageBand(65, null)],
        ];

        // Monthly trend (last 6 months) - using filtered query
        $trendBulanan = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthQuery = clone $pengundiQuery;
            $trendBulanan[] = [
                'bulan' => $date->format('M'),
                'jumlah' => $monthQuery->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        }

        // KADUN statistics: the top KADUNs by registered voters (from the roll).
        // pengundi = roll count; culaan + sentiment %% come from the canvass
        // ($pengundiQuery / $culaanQuery already carry the territory/date filters).
        $mpkkStats = $rollBase()
            ->select('kadun', DB::raw('count(*) as total'))
            ->whereNotNull('kadun')->where('kadun', '!=', '')
            ->groupBy('kadun')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(function ($item) use ($pengundiQuery, $culaanQuery) {
                $kadunName = $item->kadun;
                $rollTotal = (int) $item->total;

                $canvass = (clone $pengundiQuery)->whereRaw('UPPER(kadun) = ?', [strtoupper((string) $kadunName)]);
                $canvassTotal = (clone $canvass)->where('is_deceased', false)->count();
                $phCount = (clone $canvass)->where('kecenderungan_politik', 'like', '%PH%')->count();
                $bnCount = (clone $canvass)->where('kecenderungan_politik', 'like', '%BN%')->count();
                $tidakPastiCount = (clone $canvass)->where('kecenderungan_politik', 'like', '%TIDAK PASTI%')->count();
                $culaanCount = (clone $culaanQuery)->whereRaw('UPPER(kadun) = ?', [strtoupper((string) $kadunName)])
                    ->where('is_deceased', false)->count();

                return [
                    'mpkk' => $kadunName,
                    'pengundi' => $rollTotal,
                    'culaan' => $culaanCount,
                    'ph' => $canvassTotal > 0 ? round(($phCount / $canvassTotal) * 100) : 0,
                    'bn' => $canvassTotal > 0 ? round(($bnCount / $canvassTotal) * 100) : 0,
                    'tidakPasti' => $canvassTotal > 0 ? round(($tidakPastiCount / $canvassTotal) * 100) : 0,
                ];
            })
            ->toArray();

        // Top Petugas (using filtered queries)
        // Build filter conditions for subqueries
        $pengundiFilterConditions = '';
        $culaanFilterConditions = '';
        $filterParams = [];

        if (! $user->isSuperAdmin()) {
            if ($user->negeri_id) {
                $pengundiFilterConditions .= ' AND negeri = ?';
                $culaanFilterConditions .= ' AND negeri = ?';
                $filterParams[] = $user->negeri->nama ?? '';
                $filterParams[] = $user->negeri->nama ?? '';
            }
            if ($user->bandar_id) {
                $pengundiFilterConditions .= ' AND bandar = ?';
                $culaanFilterConditions .= ' AND bandar = ?';
                $filterParams[] = $user->bandar->nama ?? '';
                $filterParams[] = $user->bandar->nama ?? '';
            }
            if ($user->kadun_id) {
                $pengundiFilterConditions .= ' AND kadun = ?';
                $culaanFilterConditions .= ' AND kadun = ?';
                $filterParams[] = $user->kadun->nama ?? '';
                $filterParams[] = $user->kadun->nama ?? '';
            }
        }

        if ($negeriNama) {
            $pengundiFilterConditions .= ' AND negeri = ?';
            $culaanFilterConditions .= ' AND negeri = ?';
            $filterParams[] = $negeriNama;
            $filterParams[] = $negeriNama;
        }
        if ($bandarNama) {
            $pengundiFilterConditions .= ' AND bandar = ?';
            $culaanFilterConditions .= ' AND bandar = ?';
            $filterParams[] = $bandarNama;
            $filterParams[] = $bandarNama;
        }
        if ($kadunNama) {
            $pengundiFilterConditions .= ' AND kadun = ?';
            $culaanFilterConditions .= ' AND kadun = ?';
            $filterParams[] = $kadunNama;
            $filterParams[] = $kadunNama;
        }
        if ($tarikhDari) {
            $pengundiFilterConditions .= ' AND DATE(created_at) >= ?';
            $culaanFilterConditions .= ' AND DATE(created_at) >= ?';
            $filterParams[] = $tarikhDari;
            $filterParams[] = $tarikhDari;
        }
        if ($tarikhHingga) {
            $pengundiFilterConditions .= ' AND DATE(created_at) <= ?';
            $culaanFilterConditions .= ' AND DATE(created_at) <= ?';
            $filterParams[] = $tarikhHingga;
            $filterParams[] = $tarikhHingga;
        }

        $petugasStats = User::select('users.*')
            ->selectRaw("(SELECT COUNT(*) FROM data_pengundi WHERE submitted_by = users.id {$pengundiFilterConditions}) as pengundi_count", $filterParams)
            ->selectRaw("(SELECT COUNT(*) FROM hasil_culaan WHERE submitted_by = users.id {$culaanFilterConditions}) as culaan_count")
            ->havingRaw('(pengundi_count + culaan_count) > 0')
            ->orderByRaw('(pengundi_count + culaan_count) DESC')
            ->take(5)
            ->get()
            ->map(function ($petugasUser) use ($negeriNama, $bandarNama, $kadunNama, $tarikhDari, $tarikhHingga, $user) {
                // Get latest record with same filters
                $latestRecordQuery = DataPengundi::where('submitted_by', $petugasUser->id);

                if (! $user->isSuperAdmin()) {
                    if ($user->negeri_id) {
                        $latestRecordQuery->where('negeri', $user->negeri->nama ?? '');
                    }
                    if ($user->bandar_id) {
                        $latestRecordQuery->where('bandar', $user->bandar->nama ?? '');
                    }
                    if ($user->kadun_id) {
                        $latestRecordQuery->where('kadun', $user->kadun->nama ?? '');
                    }
                }

                if ($negeriNama) {
                    $latestRecordQuery->where('negeri', $negeriNama);
                }
                if ($bandarNama) {
                    $latestRecordQuery->where('bandar', $bandarNama);
                }
                if ($kadunNama) {
                    $latestRecordQuery->where('kadun', $kadunNama);
                }
                if ($tarikhDari) {
                    $latestRecordQuery->whereDate('created_at', '>=', $tarikhDari);
                }
                if ($tarikhHingga) {
                    $latestRecordQuery->whereDate('created_at', '<=', $tarikhHingga);
                }

                $latestRecord = $latestRecordQuery->latest()->first();

                return [
                    'nama' => $petugasUser->name,
                    'jumlah' => $petugasUser->pengundi_count + $petugasUser->culaan_count,
                    'kawasan' => $latestRecord ? $latestRecord->kadun : 'N/A',
                    'tarikh' => $latestRecord ? $latestRecord->created_at->format('Y-m-d') : 'N/A',
                ];
            })
            ->toArray();

        // Get filter options
        $negeriList = Negeri::orderBy('nama')->get();
        $bandarList = Bandar::orderBy('nama')->get();
        $kadunList = Kadun::orderBy('nama')->get();
        $mpkkList = Mpkk::orderBy('nama')->get();

        return Inertia::render('Dashboard/Index', [
            'totalPengundi' => $totalPengundi,
            'totalCulaan' => $totalCulaan,
            'sokongan' => $sokongan,
            'bangsa' => $bangsa,
            'umurDistribution' => $umurDistribution,
            'trendBulanan' => $trendBulanan,
            'mpkkStats' => $mpkkStats,
            'petugasStats' => $petugasStats,
            'negeriList' => $negeriList,
            'bandarList' => $bandarList,
            'kadunList' => $kadunList,
            'mpkkList' => $mpkkList,
        ]);
    }

    /**
     * Search for records by IC number or name.
     *
     * Accepts `q` (preferred) or legacy `ic` query string. When the term is
     * purely numeric, it matches IC only (preserves the old DOB-prefix
     * padding heuristic for DPPR). Otherwise it matches both IC and name so
     * the dashboard dropdown surfaces results regardless of which field the
     * user typed into.
     */
    public function searchIC(Request $request)
    {
        $user = auth()->user();
        $term = trim((string) ($request->input('q') ?? $request->input('ic') ?? ''));

        if ($term === '' || strlen($term) < 3) {
            return response()->json([]);
        }

        $isNumeric = ctype_digit($term);
        $like = '%'.$term.'%';

        // Non-super_admin viewers may only see records inside their own parlimen.
        // A user with no bandar assigned therefore sees nothing.
        $parlimenScope = null;
        if (! $user->isSuperAdmin()) {
            $parlimenScope = $user->bandar?->nama;
            if (! $parlimenScope) {
                return response()->json([]);
            }
        }

        $results = [];

        // Data Sumbangan (Hasil Culaan) is intentionally excluded from
        // the dashboard suggestive search — only DPT, DPPR, and Data
        // Pengundi appear in the dropdown. Sumbangan history is still
        // accessible via the edit page and the Sejarah Bantuan card.

        // Search in Data Pengundi (IC always; name when query is non-numeric)
        $dataPengundiQuery = DataPengundi::query()
            ->where(function ($q) use ($like, $isNumeric) {
                $q->where('no_ic', 'like', $like);
                if (! $isNumeric) {
                    $q->orWhere('nama', 'like', $like);
                }
            })
            ->with('submittedBy');

        if ($parlimenScope !== null) {
            $dataPengundiQuery->whereRaw('UPPER(bandar) = ?', [strtoupper($parlimenScope)]);
        }

        $dataPengundi = $dataPengundiQuery->limit(10)->get();

        foreach ($dataPengundi as $record) {
            $canEdit = $this->canModifyDataPengundi($record, $user);
            $locked = VoterDataMasker::isLocked($record) && ! VoterDataMasker::canUnmask($user);
            $results[] = [
                'id' => $record->id,
                'type' => 'data_pengundi',
                'no_ic' => $locked ? VoterDataMasker::MASK : $record->no_ic,
                'nama' => $record->nama,
                'no_tel' => $locked ? VoterDataMasker::MASK : $record->no_tel,
                'bandar' => $locked ? VoterDataMasker::MASK : $record->bandar,
                'kadun' => $record->kadun,
                'can_edit' => $canEdit,
                'is_locked' => $locked,
                'is_deceased' => (bool) $record->is_deceased,
                'edit_url' => $canEdit ? route('reports.data-pengundi.edit', ['dataPengundi' => $record->id, 'source' => 'dashboard']) : null,
                'updated_at' => optional($record->updated_at)->format('d/m/Y h:i A'),
            ];
        }

        // Search in ALL voter database records (upload batch + DPT)
        // Deduplicate by no_ic + nama to avoid showing the same person multiple times
        $voterQuery = PangkalanDataPengundi::where(function ($q) use ($term, $like, $isNumeric) {
            $q->where('no_ic', 'like', $like);
            if (! $isNumeric) {
                $q->orWhere('nama', 'like', $like);
            }
            if ($isNumeric && strlen($term) >= 6 && strlen($term) <= 8) {
                $q->orWhere('no_ic', $term.'0000');
            }
        });

        if ($parlimenScope !== null) {
            $voterQuery->whereRaw('UPPER(parlimen) = ?', [strtoupper($parlimenScope)]);
        }

        $voterResults = $voterQuery
            ->limit(20)
            ->get()
            ->unique(fn ($v) => $v->no_ic.'|'.$v->nama);

        // Use the real (unmasked) IC from the Eloquent records for dedup;
        // $results may hold masked values ('****') for locked rows, which
        // would never match a PangkalanDataPengundi entry's real IC and
        // would let the same person appear twice (once as Data Pengundi,
        // once as DPPR/DPT).
        $existingIcs = $dataPengundi->pluck('no_ic')->toArray();

        foreach ($voterResults as $voter) {
            // Skip if this IC+name already in results from Hasil Culaan or Data Pengundi
            if (in_array($voter->no_ic, $existingIcs)) {
                continue;
            }

            $isDpt = ! empty($voter->dpt_upload_id);
            $results[] = [
                'id' => null,
                'type' => $isDpt ? 'dpt' : 'voter_db',
                'no_ic' => $voter->no_ic,
                'nama' => $voter->nama,
                'no_tel' => null,
                'kadun' => $voter->kadun ?? null,
                'bandar' => $voter->parlimen ?? null,
                'daerah_mengundi' => $voter->daerah_mengundi ?? null,
                'lokaliti' => $voter->lokaliti ?? null,
                'can_edit' => true,
                'is_deceased' => (bool) ($voter->is_deceased ?? false),
                'edit_url' => null,
                'create_url' => route('reports.hasil-culaan.create'),
            ];
        }

        // Cross-source deceased flag: if any record (DataPengundi, HasilCulaan,
        // or PangkalanDataPengundi) for the same IC is marked deceased, mark
        // every result row for that IC as deceased — covers the case where
        // parlimen scoping hides the canonical row from this user.
        $candidateIcs = collect()
            ->merge($dataPengundi->pluck('no_ic'))
            ->merge($voterResults->pluck('no_ic'))
            ->filter()
            ->unique()
            ->values();

        if ($candidateIcs->isNotEmpty()) {
            $deceasedIcs = collect()
                ->merge(DataPengundi::whereIn('no_ic', $candidateIcs)->where('is_deceased', true)->pluck('no_ic'))
                ->merge(HasilCulaan::whereIn('no_ic', $candidateIcs)->where('is_deceased', true)->pluck('no_ic'))
                ->merge(PangkalanDataPengundi::whereIn('no_ic', $candidateIcs)->where('is_deceased', true)->pluck('no_ic'))
                ->unique()
                ->flip();

            // Use the real IC for masked rows: data_pengundi rows mask no_ic
            // when the row is locked, but the real IC came from $dataPengundi.
            $realIcByDpId = $dataPengundi->pluck('no_ic', 'id');
            foreach ($results as &$row) {
                $ic = $row['no_ic'];
                if ($ic === VoterDataMasker::MASK && ! empty($row['id'])) {
                    $ic = $realIcByDpId[$row['id']] ?? null;
                }
                if ($ic && $deceasedIcs->has($ic)) {
                    $row['is_deceased'] = true;
                }
            }
            unset($row);
        }

        return response()->json($results);
    }

    /**
     * Check if user can modify Hasil Culaan record
     */
    private function canModifyHasilCulaan($hasilCulaan, $user = null)
    {
        $user = $user ?? auth()->user();

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        // User can edit if: same parlimen OR they submitted the record
        return $hasilCulaan->bandar === ($user->bandar->nama ?? '')
            || $hasilCulaan->submitted_by === $user->id;
    }

    /**
     * Check if user can modify Data Pengundi record
     */
    private function canModifyDataPengundi($dataPengundi, $user = null)
    {
        $user = $user ?? auth()->user();

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        // User can edit if: same parlimen OR they submitted the record
        return $dataPengundi->bandar === ($user->bandar->nama ?? '')
            || $dataPengundi->submitted_by === $user->id;

        return false;
    }
}
