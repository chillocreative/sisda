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

        // Political tendency percentages (using string column)
        $totalWithTendency = DataPengundi::whereNotNull('kecenderungan_politik')
            ->where('kecenderungan_politik', '!=', '')
            ->count();
        
        $phCount = DataPengundi::where('kecenderungan_politik', 'like', '%PH%')->count();
        $bnCount = DataPengundi::where('kecenderungan_politik', 'like', '%BN%')->count();
        $pnCount = DataPengundi::where('kecenderungan_politik', 'like', '%PN%')->count();
        $tidakPastiCount = DataPengundi::where('kecenderungan_politik', 'like', '%TIDAK PASTI%')->count();

        $sokongan = [
            'ph' => $totalWithTendency > 0 ? round(($phCount / $totalWithTendency) * 100) : 0,
            'bn' => $totalWithTendency > 0 ? round(($bnCount / $totalWithTendency) * 100) : 0,
            'pn' => $totalWithTendency > 0 ? round(($pnCount / $totalWithTendency) * 100) : 0,
            'tidakPasti' => $totalWithTendency > 0 ? round(($tidakPastiCount / $totalWithTendency) * 100) : 0,
        ];

        // Bangsa distribution
        $bangsaStats = DataPengundi::select('bangsa', DB::raw('count(*) as jumlah'))
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

        // Age distribution
        $umurDistribution = [
            ['range' => '18-25', 'jumlah' => DataPengundi::whereBetween('umur', [18, 25])->count()],
            ['range' => '26-35', 'jumlah' => DataPengundi::whereBetween('umur', [26, 35])->count()],
            ['range' => '36-45', 'jumlah' => DataPengundi::whereBetween('umur', [36, 45])->count()],
            ['range' => '46-55', 'jumlah' => DataPengundi::whereBetween('umur', [46, 55])->count()],
            ['range' => '56-65', 'jumlah' => DataPengundi::whereBetween('umur', [56, 65])->count()],
            ['range' => '65+', 'jumlah' => DataPengundi::where('umur', '>', 65)->count()],
        ];

        // Monthly trend (last 6 months)
        $trendBulanan = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $trendBulanan[] = [
                'bulan' => $date->format('M'),
                'jumlah' => DataPengundi::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count()
            ];
        }

        // KADUN Statistics (instead of MPKK)
        $mpkkStats = DataPengundi::select('kadun', DB::raw('count(*) as total'))
            ->whereNotNull('kadun')
            ->groupBy('kadun')
            ->orderBy('total', 'desc')
            ->take(5)
            ->get()
            ->map(function($item) {
                $kadunName = $item->kadun;
                $total = $item->total;
                
                $phCount = DataPengundi::where('kadun', $kadunName)
                    ->where('kecenderungan_politik', 'like', '%PH%')
                    ->count();
                $bnCount = DataPengundi::where('kadun', $kadunName)
                    ->where('kecenderungan_politik', 'like', '%BN%')
                    ->count();
                $tidakPastiCount = DataPengundi::where('kadun', $kadunName)
                    ->where('kecenderungan_politik', 'like', '%TIDAK PASTI%')
                    ->count();

                $culaanCount = HasilCulaan::where('kadun', $kadunName)->count();

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

        // Top Petugas
        $petugasStats = User::select('users.*')
            ->selectRaw('(SELECT COUNT(*) FROM data_pengundi WHERE submitted_by = users.id) as pengundi_count')
            ->selectRaw('(SELECT COUNT(*) FROM hasil_culaan WHERE submitted_by = users.id) as culaan_count')
            ->havingRaw('(pengundi_count + culaan_count) > 0')
            ->orderByRaw('(pengundi_count + culaan_count) DESC')
            ->take(5)
            ->get()
            ->map(function($user) {
                $latestRecord = DataPengundi::where('submitted_by', $user->id)
                    ->latest()
                    ->first();

                return [
                    'nama' => $user->name,
                    'jumlah' => $user->pengundi_count + $user->culaan_count,
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
