<?php

namespace App\Http\Controllers;

use App\Models\HasilCulaan;
use App\Models\DataPengundi;
use App\Models\DaerahMengundi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Exports\HasilCulaanExport;
use App\Exports\DataPengundiExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    /**
     * Display the reports dashboard.
     */
    public function index()
    {
        $stats = [
            'hasil_culaan' => HasilCulaan::count(),
            'data_pengundi' => DataPengundi::count(),
        ];

        return Inertia::render('Reports/Index', [
            'stats' => $stats,
        ]);
    }

    /**
     * Display Hasil Culaan list.
     */
    public function hasilCulaanIndex(Request $request)
    {
        $user = auth()->user();
        $query = HasilCulaan::with('submittedBy');

        // User Restriction: Only view data in their KADUN
        if ($user->isUser()) {
            $query->where('kadun', $user->kadun->nama ?? '');
        }
        // Admin Restriction: Only view data in their Parlimen (Bandar)
        elseif ($user->isAdmin()) {
            $query->where('bandar', $user->bandar->nama ?? '');
        }

        // Date range filter
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('no_ic', 'like', "%{$search}%")
                  ->orWhere('no_tel', 'like', "%{$search}%");
            });
        }

        $hasilCulaan = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Reports/HasilCulaan/Index', [
            'hasilCulaan' => $hasilCulaan,
            'filters' => $request->only(['date_from', 'date_to', 'search']),
            'currentUserId' => $user->id,
        ]);
    }

    /**
     * Export Hasil Culaan to Excel.
     */
    public function exportHasilCulaan(Request $request)
    {
        $user = auth()->user();
        $query = HasilCulaan::query();

        // Admin Restriction: Only export data in their Parlimen (Bandar)
        if ($user->isAdmin()) {
            $query->where('bandar', $user->bandar->nama ?? '');
        }

        // Apply same filters as index
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('no_ic', 'like', "%{$search}%")
                  ->orWhere('no_tel', 'like', "%{$search}%");
            });
        }

        return Excel::download(new HasilCulaanExport($query), 'hasil-culaan-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Show the form for creating a new Hasil Culaan.
     */
    public function hasilCulaanCreate()
    {
        $bangsaList = \App\Models\Bangsa::all();
        $negeriList = \App\Models\Negeri::orderBy('nama')->get();
        $bandarList = \App\Models\Bandar::orderBy('nama')->get();
        $kadunList = \App\Models\Kadun::orderBy('nama')->get();
        $jenisSumbanganList = \App\Models\JenisSumbangan::all();
        $tujuanSumbanganList = \App\Models\TujuanSumbangan::all();
        $bantuanLainList = \App\Models\BantuanLain::all();
        $keahlianPartiList = \App\Models\KeahlianParti::all();
        $kecenderunganPolitikList = \App\Models\KecenderunganPolitik::all();

        $user = auth()->user();
        $daerahMengundiQuery = DaerahMengundi::orderBy('nama');
        if ($user->bandar_id) {
            $daerahMengundiQuery->where('bandar_id', $user->bandar_id);
        }
        $daerahMengundiList = $daerahMengundiQuery->get();

        $parlimenList = \App\Models\Bandar::orderBy('nama')->get();

        return Inertia::render('Reports/HasilCulaan/Create', [
            'bangsaList' => $bangsaList,
            'negeriList' => $negeriList,
            'bandarList' => $bandarList,
            'parlimenList' => $parlimenList,
            'kadunList' => $kadunList,
            'daerahMengundiList' => $daerahMengundiList,
            'jenisSumbanganList' => $jenisSumbanganList,
            'tujuanSumbanganList' => $tujuanSumbanganList,
            'bantuanLainList' => $bantuanLainList,
            'keahlianPartiList' => $keahlianPartiList,
            'kecenderunganPolitikList' => $kecenderunganPolitikList,
        ]);
    }

    /**
     * Store a newly created Hasil Culaan.
     */
    public function hasilCulaanStore(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'no_ic' => 'required|string|digits:12',
            'umur' => 'required|integer|min:1|max:150',
            'no_tel' => 'required|string|max:255',
            'bangsa' => 'required|string|max:255',
            'alamat' => 'required|string',
            'poskod' => 'required|string|max:255',
            'negeri' => 'required|string|max:255',
            'bandar' => 'required|string|max:255',
            'parlimen' => 'required|string|max:255',
            'kadun' => 'required|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'bil_isi_rumah' => 'required|integer|min:1',
            'pendapatan_isi_rumah' => 'required|numeric|min:0',
            'pekerjaan' => 'required|string|max:255',
            'pemilik_rumah' => 'required|string|max:255',
            'jenis_sumbangan' => 'nullable|string|max:255',
            'tujuan_sumbangan' => 'nullable|string|max:255',
            'bantuan_lain' => 'nullable|string|max:255',
            'keahlian_parti' => 'nullable|string|max:255',
            'kecenderungan_politik' => 'nullable|string|max:255',
            'kad_pengenalan' => 'nullable|image|max:5120', // 5MB max
            'nota' => 'nullable|string',
        ]);

        // Admin Restriction: Ensure data is created for their Parlimen
        if ($user->isAdmin()) {
            if ($request->bandar !== ($user->bandar->nama ?? '')) {
                // Note: Assuming 'bandar' field in form is string name, and user->bandar is relation
                // Ideally should validate ID, but schema uses string for reports
                // For now, we'll enforce it matches the user's bandar name
                 abort(403, 'You can only create records for your Parlimen (' . ($user->bandar->nama ?? 'Unknown') . ').');
            }
        }

        // Handle file upload
        if ($request->hasFile('kad_pengenalan')) {
            $path = $request->file('kad_pengenalan')->store('kad-pengenalan', 'public');
            $validated['kad_pengenalan'] = $path;
        }

        $validated['submitted_by'] = auth()->id();

        HasilCulaan::create($validated);

        // Auto-copy matching data to Data Pengundi
        // Check if this IC number already exists in Data Pengundi
        $existingPengundi = \App\Models\DataPengundi::where('no_ic', $validated['no_ic'])->first();

        if (!$existingPengundi) {
            // Create new Data Pengundi record with matching fields
            \App\Models\DataPengundi::create([
                'nama' => $validated['nama'],
                'no_ic' => $validated['no_ic'],
                'umur' => $validated['umur'],
                'no_tel' => $validated['no_tel'],
                'bangsa' => $validated['bangsa'],
                'alamat' => $validated['alamat'],
                'poskod' => $validated['poskod'],
                'negeri' => $validated['negeri'],
                'bandar' => $validated['bandar'],
                'parlimen' => $validated['parlimen'], 
                'kadun' => $validated['kadun'],
                'daerah_mengundi' => $validated['daerah_mengundi'] ?? null,
                'keahlian_parti' => $validated['keahlian_parti'] ?? null,
                'kecenderungan_politik' => $validated['kecenderungan_politik'] ?? null,
                'hubungan' => null, // Will be updated manually by user
                'submitted_by' => auth()->id(),
            ]);
        }

        return redirect()->route('reports.hasil-culaan.index')->with('success', 'Rekod berjaya ditambah dan data pengundi telah dikemaskini');
    }

    /**
     * Show the form for editing Hasil Culaan.
     */
    public function hasilCulaanEdit(HasilCulaan $hasilCulaan)
    {
        $user = auth()->user();

        // Check if user can modify this record
        if (!$this->canModifyHasilCulaan($hasilCulaan, $user)) {
            abort(403, 'Akses ditolak. Anda tidak dibenarkan mengubah rekod ini.');
        }

        $bangsaList = \App\Models\Bangsa::all();
        $negeriList = \App\Models\Negeri::orderBy('nama')->get();
        $bandarList = \App\Models\Bandar::orderBy('nama')->get();
        $kadunList = \App\Models\Kadun::orderBy('nama')->get();
        $jenisSumbanganList = \App\Models\JenisSumbangan::all();
        $tujuanSumbanganList = \App\Models\TujuanSumbangan::all();
        $bantuanLainList = \App\Models\BantuanLain::all();
        $keahlianPartiList = \App\Models\KeahlianParti::all();
        $kecenderunganPolitikList = \App\Models\KecenderunganPolitik::all();

        $daerahMengundiQuery = DaerahMengundi::orderBy('nama');
        if ($user->bandar_id) {
            $daerahMengundiQuery->where('bandar_id', $user->bandar_id);
        }
        $daerahMengundiList = $daerahMengundiQuery->get();

        $parlimenList = \App\Models\Bandar::orderBy('nama')->get();

        return Inertia::render('Reports/HasilCulaan/Edit', [
            'hasilCulaan' => $hasilCulaan,
            'bangsaList' => $bangsaList,
            'negeriList' => $negeriList,
            'bandarList' => $bandarList,
            'parlimenList' => $parlimenList,
            'kadunList' => $kadunList,
            'daerahMengundiList' => $daerahMengundiList,
            'jenisSumbanganList' => $jenisSumbanganList,
            'tujuanSumbanganList' => $tujuanSumbanganList,
            'bantuanLainList' => $bantuanLainList,
            'keahlianPartiList' => $keahlianPartiList,
            'kecenderunganPolitikList' => $kecenderunganPolitikList,
        ]);
    }

    /**
     * Update the specified Hasil Culaan.
     */
    public function hasilCulaanUpdate(Request $request, HasilCulaan $hasilCulaan)
    {
        $user = auth()->user();

        // Check if user can modify this record
        if (!$this->canModifyHasilCulaan($hasilCulaan, $user)) {
            abort(403, 'Akses ditolak. Anda tidak dibenarkan mengubah rekod ini.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'no_ic' => 'required|string|digits:12',
            'umur' => 'required|integer|min:1|max:150',
            'no_tel' => 'required|string|max:255',
            'bangsa' => 'required|string|max:255',
            'alamat' => 'required|string',
            'poskod' => 'required|string|max:255',
            'negeri' => 'required|string|max:255',
            'bandar' => 'required|string|max:255',
            'parlimen' => 'required|string|max:255',
            'kadun' => 'required|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'bil_isi_rumah' => 'required|integer|min:1',
            'pendapatan_isi_rumah' => 'required|numeric|min:0',
            'pekerjaan' => 'required|string|max:255',
            'pemilik_rumah' => 'required|string|max:255',
            'jenis_sumbangan' => 'nullable|string|max:255',
            'tujuan_sumbangan' => 'nullable|string|max:255',
            'bantuan_lain' => 'nullable|string|max:255',
            'keahlian_parti' => 'nullable|string|max:255',
            'kecenderungan_politik' => 'nullable|string|max:255',
            'kad_pengenalan' => 'nullable|image|max:5120', // 5MB max
            'nota' => 'nullable|string',
        ]);

        // Admin/User Restriction: Ensure data remains in their territory
        if ($user->isAdmin() && $request->bandar !== ($user->bandar->nama ?? '')) {
            abort(403, 'Anda tidak boleh memindahkan rekod ke luar kawasan anda.');
        }
        if ($user->isUser() && $request->kadun !== ($user->kadun->nama ?? '')) {
            abort(403, 'Anda tidak boleh memindahkan rekod ke luar kawasan anda.');
        }

        // Handle file upload
        if ($request->hasFile('kad_pengenalan')) {
            // Delete old file if exists
            if ($hasilCulaan->kad_pengenalan) {
                \Storage::disk('public')->delete($hasilCulaan->kad_pengenalan);
            }
            
            $path = $request->file('kad_pengenalan')->store('kad-pengenalan', 'public');
            $validated['kad_pengenalan'] = $path;
        } else {
            // Keep the existing file path if no new file is uploaded
            unset($validated['kad_pengenalan']);
        }

        $hasilCulaan->update($validated);

        return redirect()->route('reports.hasil-culaan.index')->with('success', 'Rekod berjaya dikemaskini');
    }

    /**
     * Remove the specified Hasil Culaan.
     */
    public function hasilCulaanDestroy(HasilCulaan $hasilCulaan)
    {
        $user = auth()->user();

        // Check if user can modify this record
        if (!$this->canModifyHasilCulaan($hasilCulaan, $user)) {
            abort(403, 'Akses ditolak. Anda tidak dibenarkan memadam rekod ini.');
        }

        $hasilCulaan->delete();

        return redirect()->route('reports.hasil-culaan.index')->with('success', 'Rekod berjaya dipadam');
    }

    /**
     * Remove multiple Hasil Culaan records.
     */
    public function hasilCulaanBulkDelete(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:hasil_culaan,id',
        ]);

        // Admin Restriction: Filter out records not in their Parlimen
        if ($user->isAdmin()) {
            $ids = HasilCulaan::whereIn('id', $validated['ids'])
                ->where('bandar', $user->bandar->nama ?? '')
                ->pluck('id')
                ->toArray();
            
            if (empty($ids)) {
                 return redirect()->back()->with('error', 'Tiada rekod yang sah untuk dipadam.');
            }
             HasilCulaan::whereIn('id', $ids)->delete();
        } else {
             HasilCulaan::whereIn('id', $validated['ids'])->delete();
        }

        return redirect()->route('reports.hasil-culaan.index')->with('success', 'Rekod terpilih berjaya dipadam');
    }

    /**
     * Display Data Pengundi list.
     */
    public function dataPengundiIndex(Request $request)
    {
        $user = auth()->user();
        $query = DataPengundi::with('submittedBy');

        // User Restriction: Only view data in their KADUN
        if ($user->isUser()) {
            $query->where('kadun', $user->kadun->nama ?? '');
        }
        // Admin Restriction: Only view data in their Parlimen (Bandar)
        elseif ($user->isAdmin()) {
            $query->where('bandar', $user->bandar->nama ?? '');
        }

        // Date range filter
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('no_ic', 'like', "%{$search}%")
                  ->orWhere('no_tel', 'like', "%{$search}%");
            });
        }

        $dataPengundi = $query->orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Reports/DataPengundi/Index', [
            'dataPengundi' => $dataPengundi,
            'filters' => $request->only(['date_from', 'date_to', 'search']),
            'currentUserId' => $user->id,
        ]);
    }

    /**
     * Export Data Pengundi to Excel.
     */
    public function exportDataPengundi(Request $request)
    {
        $user = auth()->user();
        $query = DataPengundi::query();

        // Admin Restriction: Only export data in their Parlimen (Bandar)
        if ($user->isAdmin()) {
            $query->where('bandar', $user->bandar->nama ?? '');
        }

        // Apply same filters as index
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('no_ic', 'like', "%{$search}%")
                  ->orWhere('no_tel', 'like', "%{$search}%");
            });
        }

        return Excel::download(new DataPengundiExport($query), 'data-pengundi-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Show the form for creating a new Data Pengundi.
     */
    public function dataPengundiCreate()
    {
        $bangsaList = \App\Models\Bangsa::all();

        $negeriList = \App\Models\Negeri::orderBy('nama')->get();
        $bandarList = \App\Models\Bandar::orderBy('nama')->get();
        $kadunList = \App\Models\Kadun::orderBy('nama')->get();
        $keahlianPartiList = \App\Models\KeahlianParti::all();
        $kecenderunganPolitikList = \App\Models\KecenderunganPolitik::all();

        $user = auth()->user();
        $daerahMengundiQuery = DaerahMengundi::orderBy('nama');
        if ($user->bandar_id) {
            $daerahMengundiQuery->where('bandar_id', $user->bandar_id);
        }
        $daerahMengundiList = $daerahMengundiQuery->get();

        $parlimenList = \App\Models\Bandar::orderBy('nama')->get();

        return Inertia::render('Reports/DataPengundi/Create', [
            'bangsaList' => $bangsaList,
            'negeriList' => $negeriList,
            'bandarList' => $bandarList,
            'parlimenList' => $parlimenList,
            'kadunList' => $kadunList,
            'daerahMengundiList' => $daerahMengundiList,
            'keahlianPartiList' => $keahlianPartiList,
            'kecenderunganPolitikList' => $kecenderunganPolitikList,
        ]);
    }

    /**
     * Store a newly created Data Pengundi.
     */
    public function dataPengundiStore(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'no_ic' => 'required|string|digits:12',
            'umur' => 'required|integer|min:1|max:150',
            'no_tel' => 'required|string|max:255',
            'bangsa' => 'required|string|max:255',
            'hubungan' => 'nullable|string|max:255',
            'alamat' => 'required|string',
            'poskod' => 'required|string|max:255',
            'negeri' => 'required|string|max:255',
            'bandar' => 'required|string|max:255',
            'parlimen' => 'required|string|max:255',
            'kadun' => 'required|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'keahlian_parti' => 'nullable|string|max:255',
            'kecenderungan_politik' => 'nullable|string|max:255',
        ]);

        // Admin Restriction: Ensure data is created for their Parlimen
        if ($user->isAdmin()) {
            if ($request->bandar !== ($user->bandar->nama ?? '')) {
                 abort(403, 'You can only create records for your Parlimen (' . ($user->bandar->nama ?? 'Unknown') . ').');
            }
        }

        $validated['submitted_by'] = auth()->id();

        DataPengundi::create($validated);

        return redirect()->route('reports.data-pengundi.index')->with('success', 'Rekod berjaya ditambah');
    }

    /**
     * Show the form for editing Data Pengundi.
     */
    public function dataPengundiEdit(DataPengundi $dataPengundi)
    {
        $user = auth()->user();

        // Admin Restriction: Can only edit records in their Parlimen
        if ($user->isAdmin()) {
             if ($dataPengundi->bandar !== ($user->bandar->nama ?? '')) {
                abort(403, 'You can only edit records in your Parlimen.');
            }
        }

        $bangsaList = \App\Models\Bangsa::all();

        $negeriList = \App\Models\Negeri::orderBy('nama')->get();
        $bandarList = \App\Models\Bandar::orderBy('nama')->get();
        $kadunList = \App\Models\Kadun::orderBy('nama')->get();
        $keahlianPartiList = \App\Models\KeahlianParti::all();
        $kecenderunganPolitikList = \App\Models\KecenderunganPolitik::all();

        $daerahMengundiQuery = DaerahMengundi::orderBy('nama');
        if ($user->bandar_id) {
            $daerahMengundiQuery->where('bandar_id', $user->bandar_id);
        }
        $daerahMengundiList = $daerahMengundiQuery->get();

        $parlimenList = \App\Models\Bandar::orderBy('nama')->get();

        return Inertia::render('Reports/DataPengundi/Edit', [
            'dataPengundi' => $dataPengundi,
            'bangsaList' => $bangsaList,
            'negeriList' => $negeriList,
            'bandarList' => $bandarList,
            'parlimenList' => $parlimenList,
            'kadunList' => $kadunList,
            'daerahMengundiList' => $daerahMengundiList,
            'keahlianPartiList' => $keahlianPartiList,
            'kecenderunganPolitikList' => $kecenderunganPolitikList,
        ]);
    }

    /**
     * Update the specified Data Pengundi.
     */
    public function dataPengundiUpdate(Request $request, DataPengundi $dataPengundi)
    {
        $user = auth()->user();

        // Admin Restriction: Can only update records in their Parlimen
        if ($user->isAdmin()) {
             if ($dataPengundi->bandar !== ($user->bandar->nama ?? '')) {
                abort(403, 'You can only update records in your Parlimen.');
            }
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'no_ic' => 'required|string|digits:12',
            'umur' => 'required|integer|min:1|max:150',
            'no_tel' => 'required|string|max:255',
            'bangsa' => 'required|string|max:255',
            'hubungan' => 'nullable|string|max:255',
            'alamat' => 'required|string',
            'poskod' => 'required|string|max:255',
            'negeri' => 'required|string|max:255',
            'bandar' => 'required|string|max:255',
            'parlimen' => 'required|string|max:255',
            'kadun' => 'required|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'keahlian_parti' => 'nullable|string|max:255',
            'kecenderungan_politik' => 'nullable|string|max:255',
        ]);

        // Admin Restriction: Ensure data remains in their Parlimen
        if ($user->isAdmin()) {
            if ($request->bandar !== ($user->bandar->nama ?? '')) {
                 abort(403, 'You cannot move records outside your Parlimen.');
            }
        }

        $dataPengundi->update($validated);

        return redirect()->route('reports.data-pengundi.index')->with('success', 'Rekod berjaya dikemaskini');
    }

    /**
     * Remove the specified Data Pengundi.
     */
    public function dataPengundiDestroy(DataPengundi $dataPengundi)
    {
        $user = auth()->user();

        // Admin Restriction: Can only delete records in their Parlimen
        if ($user->isAdmin()) {
             if ($dataPengundi->bandar !== ($user->bandar->nama ?? '')) {
                abort(403, 'You can only delete records in your Parlimen.');
            }
        }

        $dataPengundi->delete();

        return redirect()->route('reports.data-pengundi.index')->with('success', 'Rekod berjaya dipadam');
    }

    /**
     * Remove multiple Data Pengundi records.
     */
    public function dataPengundiBulkDelete(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:data_pengundi,id',
        ]);

        // Admin Restriction: Filter out records not in their Parlimen
        if ($user->isAdmin()) {
            $ids = DataPengundi::whereIn('id', $validated['ids'])
                ->where('bandar', $user->bandar->nama ?? '')
                ->pluck('id')
                ->toArray();
            
            if (empty($ids)) {
                 return redirect()->back()->with('error', 'Tiada rekod yang sah untuk dipadam.');
            }
             DataPengundi::whereIn('id', $ids)->delete();
        } else {
             DataPengundi::whereIn('id', $validated['ids'])->delete();
        }

        return redirect()->route('reports.data-pengundi.index')->with('success', 'Rekod terpilih berjaya dipadam');
    }

    /**
     * Search postcodes.
     */
    public function searchPostcode(Request $request)
    {
        $query = $request->input('query');
        
        if (strlen($query) < 3) {
            return response()->json([]);
        }

        $postcodes = \App\Models\Postcode::where('postcode', 'like', $query . '%')
            ->select('postcode', 'city', 'state')
            ->limit(20)
            ->get();

        return response()->json($postcodes);
    }

    /**
     * Search postcodes with detailed information.
     */
    public function searchPostcodeWithDetails(Request $request)
    {
        $query = $request->input('query');
        
        if (strlen($query) < 3) {
            return response()->json([]);
        }

        $postcodes = \App\Models\Postcode::where('postcode', 'like', $query . '%')
            ->select('postcode', 'city', 'state')
            ->limit(20)
            ->get();

        // Enrich with Bandar and Negeri data
        $enrichedPostcodes = $postcodes->map(function($postcode) {
            // Find matching Bandar by city name
            $bandar = \App\Models\Bandar::where('nama', 'like', '%' . $postcode->city . '%')->first();
            
            // Find matching Negeri by state name
            $negeri = \App\Models\Negeri::where('nama', 'like', '%' . $postcode->state . '%')->first();
            
            return [
                'postcode' => $postcode->postcode,
                'city' => $postcode->city,
                'state' => $postcode->state,
                'bandar_id' => $bandar->id ?? null,
                'bandar_nama' => $bandar->nama ?? $postcode->city,
                'negeri_id' => $negeri->id ?? null,
                'negeri_nama' => $negeri->nama ?? $postcode->state,
                'kod_parlimen' => $bandar->kod_parlimen ?? null,
            ];
        });

        return response()->json($enrichedPostcodes);
    }

    /**
     * Get Parlimen by Negeri name.
     */
    public function getParlimenByNegeri(Request $request)
    {
        $negeriNama = $request->input('negeri');
        
        if (!$negeriNama) {
            return response()->json([]);
        }

        // Find Negeri
        $negeri = \App\Models\Negeri::where('nama', $negeriNama)->first();
        
        if (!$negeri) {
            return response()->json([]);
        }

        // Get Parlimen (Bandar model) for this Negeri
        $parlimenList = \App\Models\Bandar::where('negeri_id', $negeri->id)
            ->orderBy('nama')
            ->get();

        return response()->json($parlimenList);
    }

    /**
     * Get KADUN by Bandar name.
     */
    public function getKadunByBandar(Request $request)
    {
        $bandarNama = $request->input('bandar');
        
        if (!$bandarNama) {
            return response()->json([]);
        }

        // Find Bandar
        $bandar = \App\Models\Bandar::where('nama', $bandarNama)->first();
        
        if (!$bandar) {
            return response()->json([]);
        }

        // Get KADUN for this Bandar
        $kadunList = \App\Models\Kadun::where('bandar_id', $bandar->id)
            ->orderBy('nama')
            ->get();

        return response()->json($kadunList);
    }

    /**
     * Get Daerah Mengundi by Bandar name.
     */
    public function getDaerahMengundiByBandar(Request $request)
    {
        $bandarNama = $request->input('bandar');
        
        if (!$bandarNama) {
            return response()->json([]);
        }

        // Find Bandar
        $bandar = \App\Models\Bandar::where('nama', $bandarNama)->first();
        
        if (!$bandar) {
            return response()->json([]);
        }

        // Get Daerah Mengundi for this Bandar
        $daerahMengundiList = \App\Models\DaerahMengundi::where('bandar_id', $bandar->id)
            ->orderBy('nama')
            ->get();

        return response()->json($daerahMengundiList);
    }

    /**
     * Check if user can modify Hasil Culaan record.
     */
    private function canModifyHasilCulaan($hasilCulaan, $user = null)
    {
        $user = $user ?? auth()->user();

        // Super Admin can modify everything
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can modify records in their territory (Bandar)
        if ($user->isAdmin()) {
            return $hasilCulaan->bandar === ($user->bandar->nama ?? '');
        }

        // Regular users can only modify their own submissions within their territory
        if ($user->isUser()) {
            return $hasilCulaan->submitted_by === $user->id 
                && $hasilCulaan->kadun === ($user->kadun->nama ?? '');
        }

        return false;
    }

    /**
     * Check if user can modify Data Pengundi record.
     */
    private function canModifyDataPengundi($dataPengundi, $user = null)
    {
        $user = $user ?? auth()->user();

        // Super Admin can modify everything
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can modify records in their territory (Bandar)
        if ($user->isAdmin()) {
            return $dataPengundi->bandar_id === $user->bandar_id;
        }

        // Regular users can only modify their own submissions within their territory
        if ($user->isUser()) {
            return $dataPengundi->submitted_by === $user->id 
                && $dataPengundi->kadun_id === $user->kadun_id;
        }

        return false;
    }
}
