<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\Negeri;
use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Mpkk;
use App\Models\User;
use App\Models\UploadBatch;
use App\Models\PangkalanDataPengundi;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Show simplified dashboard for Admin and Regular users
        if ($user->isAdmin() || $user->isUser()) {
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
        if (!$user->isSuperAdmin()) {
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

        // Calculate metrics
        $totalPengundi = $pengundiQuery->count();
        $totalCulaan = $culaanQuery->count();

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

        // Bangsa distribution (using filtered query)
        $bangsaQuery = clone $pengundiQuery;
        $bangsaStats = $bangsaQuery->select('bangsa', DB::raw('count(*) as jumlah'))
            ->whereNotNull('bangsa')
            ->groupBy('bangsa')
            ->get()
            ->pluck('jumlah', 'bangsa')
            ->toArray();

        $bangsa = [
            'melayu' => $bangsaStats['Melayu'] ?? 0,
            'cina' => $bangsaStats['Cina'] ?? 0,
            'india' => $bangsaStats['India'] ?? 0,
            'lain' => $bangsaStats['Lain-lain'] ?? 0,
        ];

        // Age distribution (using filtered query)
        $umurDistribution = [
            ['range' => '18-25', 'jumlah' => (clone $pengundiQuery)->whereBetween('umur', [18, 25])->count()],
            ['range' => '26-35', 'jumlah' => (clone $pengundiQuery)->whereBetween('umur', [26, 35])->count()],
            ['range' => '36-45', 'jumlah' => (clone $pengundiQuery)->whereBetween('umur', [36, 45])->count()],
            ['range' => '46-55', 'jumlah' => (clone $pengundiQuery)->whereBetween('umur', [46, 55])->count()],
            ['range' => '56-65', 'jumlah' => (clone $pengundiQuery)->whereBetween('umur', [56, 65])->count()],
            ['range' => '65+', 'jumlah' => (clone $pengundiQuery)->where('umur', '>', 65)->count()],
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
                    ->count()
            ];
        }

        // KADUN Statistics (using filtered queries)
        $kadunStatsQuery = clone $pengundiQuery;
        $mpkkStats = $kadunStatsQuery->select('kadun', DB::raw('count(*) as total'))
            ->whereNotNull('kadun')
            ->groupBy('kadun')
            ->orderBy('total', 'desc')
            ->take(5)
            ->get()
            ->map(function($item) use ($negeriNama, $bandarNama, $kadunNama, $tarikhDari, $tarikhHingga, $user) {
                $kadunName = $item->kadun;
                $total = $item->total;
                
                // Build filtered query for this KADUN
                $kadunPengundiQuery = DataPengundi::where('kadun', $kadunName);
                $kadunCulaanQuery = HasilCulaan::where('kadun', $kadunName);
                
                // Apply same filters as main query
                if (!$user->isSuperAdmin()) {
                    if ($user->negeri_id) {
                        $kadunPengundiQuery->where('negeri', $user->negeri->nama ?? '');
                        $kadunCulaanQuery->where('negeri', $user->negeri->nama ?? '');
                    }
                    if ($user->bandar_id) {
                        $kadunPengundiQuery->where('bandar', $user->bandar->nama ?? '');
                        $kadunCulaanQuery->where('bandar', $user->bandar->nama ?? '');
                    }
                }
                
                if ($negeriNama) {
                    $kadunPengundiQuery->where('negeri', $negeriNama);
                    $kadunCulaanQuery->where('negeri', $negeriNama);
                }
                if ($bandarNama) {
                    $kadunPengundiQuery->where('bandar', $bandarNama);
                    $kadunCulaanQuery->where('bandar', $bandarNama);
                }
                if ($tarikhDari) {
                    $kadunPengundiQuery->whereDate('created_at', '>=', $tarikhDari);
                    $kadunCulaanQuery->whereDate('created_at', '>=', $tarikhDari);
                }
                if ($tarikhHingga) {
                    $kadunPengundiQuery->whereDate('created_at', '<=', $tarikhHingga);
                    $kadunCulaanQuery->whereDate('created_at', '<=', $tarikhHingga);
                }
                
                $phCount = (clone $kadunPengundiQuery)
                    ->where('kecenderungan_politik', 'like', '%PH%')
                    ->count();
                $bnCount = (clone $kadunPengundiQuery)
                    ->where('kecenderungan_politik', 'like', '%BN%')
                    ->count();
                $tidakPastiCount = (clone $kadunPengundiQuery)
                    ->where('kecenderungan_politik', 'like', '%TIDAK PASTI%')
                    ->count();

                $culaanCount = $kadunCulaanQuery->count();

                return [
                    'mpkk' => $kadunName,
                    'pengundi' => $total,
                    'culaan' => $culaanCount,
                    'ph' => $total > 0 ? round(($phCount / $total) * 100) : 0,
                    'bn' => $total > 0 ? round(($bnCount / $total) * 100) : 0,
                    'tidakPasti' => $total > 0 ? round(($tidakPastiCount / $total) * 100) : 0,
                ];
            })
            ->toArray();


        // Top Petugas (using filtered queries)
        // Build filter conditions for subqueries
        $pengundiFilterConditions = '';
        $culaanFilterConditions = '';
        $filterParams = [];
        
        if (!$user->isSuperAdmin()) {
            if ($user->negeri_id) {
                $pengundiFilterConditions .= " AND negeri = ?";
                $culaanFilterConditions .= " AND negeri = ?";
                $filterParams[] = $user->negeri->nama ?? '';
                $filterParams[] = $user->negeri->nama ?? '';
            }
            if ($user->bandar_id) {
                $pengundiFilterConditions .= " AND bandar = ?";
                $culaanFilterConditions .= " AND bandar = ?";
                $filterParams[] = $user->bandar->nama ?? '';
                $filterParams[] = $user->bandar->nama ?? '';
            }
            if ($user->kadun_id) {
                $pengundiFilterConditions .= " AND kadun = ?";
                $culaanFilterConditions .= " AND kadun = ?";
                $filterParams[] = $user->kadun->nama ?? '';
                $filterParams[] = $user->kadun->nama ?? '';
            }
        }
        
        if ($negeriNama) {
            $pengundiFilterConditions .= " AND negeri = ?";
            $culaanFilterConditions .= " AND negeri = ?";
            $filterParams[] = $negeriNama;
            $filterParams[] = $negeriNama;
        }
        if ($bandarNama) {
            $pengundiFilterConditions .= " AND bandar = ?";
            $culaanFilterConditions .= " AND bandar = ?";
            $filterParams[] = $bandarNama;
            $filterParams[] = $bandarNama;
        }
        if ($kadunNama) {
            $pengundiFilterConditions .= " AND kadun = ?";
            $culaanFilterConditions .= " AND kadun = ?";
            $filterParams[] = $kadunNama;
            $filterParams[] = $kadunNama;
        }
        if ($tarikhDari) {
            $pengundiFilterConditions .= " AND DATE(created_at) >= ?";
            $culaanFilterConditions .= " AND DATE(created_at) >= ?";
            $filterParams[] = $tarikhDari;
            $filterParams[] = $tarikhDari;
        }
        if ($tarikhHingga) {
            $pengundiFilterConditions .= " AND DATE(created_at) <= ?";
            $culaanFilterConditions .= " AND DATE(created_at) <= ?";
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
            ->map(function($petugasUser) use ($negeriNama, $bandarNama, $kadunNama, $tarikhDari, $tarikhHingga, $user) {
                // Get latest record with same filters
                $latestRecordQuery = DataPengundi::where('submitted_by', $petugasUser->id);
                
                if (!$user->isSuperAdmin()) {
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
                
                if ($negeriNama) $latestRecordQuery->where('negeri', $negeriNama);
                if ($bandarNama) $latestRecordQuery->where('bandar', $bandarNama);
                if ($kadunNama) $latestRecordQuery->where('kadun', $kadunNama);
                if ($tarikhDari) $latestRecordQuery->whereDate('created_at', '>=', $tarikhDari);
                if ($tarikhHingga) $latestRecordQuery->whereDate('created_at', '<=', $tarikhHingga);
                
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
     * Search for records by IC number
     */
    public function searchIC(Request $request)
    {
        $user = auth()->user();
        $icNumber = $request->input('ic');

        if (!$icNumber || strlen($icNumber) < 3) {
            return response()->json([]);
        }

        $results = [];

        // Search in Hasil Culaan
        $hasilCulaanQuery = HasilCulaan::where('no_ic', 'like', "%{$icNumber}%")
            ->with('submittedBy');

        // Apply territory restrictions for Admin and User
        if ($user->isAdmin()) {
            $hasilCulaanQuery->where('bandar', $user->bandar->nama ?? '');
        } elseif ($user->isUser()) {
            $hasilCulaanQuery->where('kadun', $user->kadun->nama ?? '');
        }

        $hasilCulaan = $hasilCulaanQuery->limit(5)->get();

        foreach ($hasilCulaan as $record) {
            $canEdit = $this->canModifyHasilCulaan($record, $user);
            $results[] = [
                'id' => $record->id,
                'type' => 'hasil_culaan',
                'no_ic' => $record->no_ic,
                'nama' => $record->nama,
                'no_tel' => $record->no_tel,
                'bandar' => $record->bandar,
                'kadun' => $record->kadun,
                'can_edit' => $canEdit,
                'edit_url' => $canEdit ? route('reports.hasil-culaan.edit', $record->id) : null,
            ];
        }

        // Search in Data Pengundi
        $dataPengundiQuery = DataPengundi::where('no_ic', 'like', "%{$icNumber}%")
            ->with('submittedBy');

        // Apply territory restrictions for Admin and User
        if ($user->isAdmin()) {
            $dataPengundiQuery->where('bandar', $user->bandar->nama ?? '');
        } elseif ($user->isUser()) {
            $dataPengundiQuery->where('kadun', $user->kadun->nama ?? '');
        }

        $dataPengundi = $dataPengundiQuery->limit(5)->get();

        foreach ($dataPengundi as $record) {
            $canEdit = $this->canModifyDataPengundi($record, $user);
            $results[] = [
                'id' => $record->id,
                'type' => 'data_pengundi',
                'no_ic' => $record->no_ic,
                'nama' => $record->nama,
                'no_tel' => $record->no_tel,
                'bandar' => $record->bandar,
                'kadun' => $record->kadun,
                'can_edit' => $canEdit,
                'edit_url' => $canEdit ? route('reports.data-pengundi.edit', $record->id) : null,
            ];
        }

        // Search in voter database
        $activeBatch = UploadBatch::where('is_active', true)->first();
        if ($activeBatch) {
            $voterResults = PangkalanDataPengundi::where('upload_batch_id', $activeBatch->id)
                ->where('no_ic', 'like', "%{$icNumber}%")
                ->limit(5)
                ->get(['no_ic', 'nama', 'lokaliti', 'kadun', 'parlimen', 'negeri', 'bangsa']);

            foreach ($voterResults as $voter) {
                $results[] = [
                    'id'         => null,
                    'type'       => 'voter_db',
                    'no_ic'      => $voter->no_ic,
                    'nama'       => $voter->nama,
                    'no_tel'     => null,
                    'kadun'      => $voter->kadun,
                    'bandar'     => $voter->parlimen,
                    'can_edit'   => true,
                    'edit_url'   => null,
                    'create_url' => route('reports.data-pengundi.create'),
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * Check if user can modify Hasil Culaan record
     */
    private function canModifyHasilCulaan($hasilCulaan, $user = null)
    {
        $user = $user ?? auth()->user();

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $hasilCulaan->bandar === ($user->bandar->nama ?? '');
        }

        if ($user->isUser()) {
            return $hasilCulaan->submitted_by === $user->id 
                && $hasilCulaan->kadun === ($user->kadun->nama ?? '');
        }

        return false;
    }

    /**
     * Check if user can modify Data Pengundi record
     */
    private function canModifyDataPengundi($dataPengundi, $user = null)
    {
        $user = $user ?? auth()->user();

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $dataPengundi->bandar === ($user->bandar->nama ?? '');
        }

        if ($user->isUser()) {
            return $dataPengundi->submitted_by === $user->id 
                && $dataPengundi->kadun === ($user->kadun->nama ?? '');
        }

        return false;
    }
}
