<?php

namespace App\Http\Controllers;

use App\Models\HasilCulaan;
use App\Models\DataPengundi;
use App\Models\DaerahMengundi;
use App\Models\EditHistory;
use App\Models\Lokaliti;
use App\Services\VoterColorService;
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

        // Count records per IC for badge display
        $icCounts = HasilCulaan::selectRaw('no_ic, COUNT(*) as count')
            ->whereIn('no_ic', $hasilCulaan->pluck('no_ic'))
            ->groupBy('no_ic')
            ->having('count', '>', 1)
            ->pluck('count', 'no_ic');

        return Inertia::render('Reports/HasilCulaan/Index', [
            'hasilCulaan' => $hasilCulaan,
            'icCounts' => $icCounts,
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
     * Get all Hasil Culaan records for a given IC number.
     */
    public function hasilCulaanByIc(Request $request)
    {
        $request->validate(['ic' => 'required|string|digits:12']);

        $records = HasilCulaan::with('submittedBy:id,name')
            ->where('no_ic', $request->ic)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'nama', 'no_ic', 'umur', 'no_tel', 'bangsa', 'alamat', 'poskod', 'negeri', 'bandar', 'parlimen', 'kadun', 'mpkk', 'daerah_mengundi', 'lokaliti', 'bil_isi_rumah', 'pendapatan_isi_rumah', 'pekerjaan', 'jenis_pekerjaan', 'pemilik_rumah', 'jenis_sumbangan', 'tujuan_sumbangan', 'bantuan_lain', 'jumlah_wang_tunai', 'keahlian_parti', 'kecenderungan_politik', 'submitted_by', 'created_at']);

        return response()->json($records);
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

        $lokalitiList = Lokaliti::orderBy('nama')->get();

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
            'lokalitiList' => $lokalitiList,
        ]);
    }

    /**
     * Store a newly created Hasil Culaan.
     */
    public function hasilCulaanStore(Request $request)
    {
        $user = auth()->user();

        $hasSumbangan = $request->boolean('has_sumbangan');
        $updateStatusPengundi = $request->boolean('update_status_pengundi');

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
            'mpkk' => 'nullable|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'lokaliti' => 'nullable|string|max:255',
            'bil_isi_rumah' => ($hasSumbangan ? 'required|integer|min:1' : 'nullable|integer|min:1'),
            'pendapatan_isi_rumah' => 'nullable|numeric|min:0',
            'pekerjaan' => ($hasSumbangan ? 'required|in:Kerajaan,Swasta,Bekerja Sendiri,Tidak Bekerja' : 'nullable|in:Kerajaan,Swasta,Bekerja Sendiri,Tidak Bekerja'),
            'jenis_pekerjaan' => ($hasSumbangan ? 'required|array|min:1' : 'nullable|array'),
            'jenis_pekerjaan.*' => 'string|max:255',
            'jenis_pekerjaan_lain' => 'nullable|string|max:255',
            'pemilik_rumah' => ($hasSumbangan ? 'required|string|max:255' : 'nullable|string|max:255'),
            'pemilik_rumah_lain' => 'nullable|string|max:255',
            'jenis_sumbangan' => ($hasSumbangan ? 'required|array|min:1' : 'nullable|array'),
            'jenis_sumbangan_lain' => 'nullable|string|max:255',
            'tujuan_sumbangan' => ($hasSumbangan ? 'required|array|min:1' : 'nullable|array'),
            'tujuan_sumbangan_lain' => 'nullable|string|max:255',
            'bantuan_lain' => ($hasSumbangan ? 'required|array|min:1' : 'nullable|array'),
            'bantuan_lain_lain' => 'nullable|string|max:255',
            'zpp_jenis_bantuan' => 'nullable|array',
            'isejahtera_program' => 'nullable|string|max:255',
            'bkb_program' => 'nullable|string|max:255',
            'jumlah_bantuan_tunai' => 'nullable|numeric|min:0',
            'jumlah_wang_tunai' => 'nullable|numeric|min:0',
            'jkm_program' => 'nullable|string|max:255',
            'keahlian_parti' => 'required|string|max:255',
            'kecenderungan_politik' => 'required|string|max:255',
            'status_pengundi' => 'nullable|string|max:255',
            'kad_pengenalan' => 'nullable|image|max:5120', // 5MB max
            'nota' => 'nullable|string',
        ], [
            'jenis_sumbangan.required' => 'Sila pilih sekurang-kurangnya satu Jenis Sumbangan.',
            'tujuan_sumbangan.required' => 'Sila pilih sekurang-kurangnya satu Tujuan Sumbangan.',
            'bantuan_lain.required' => 'Sila pilih sekurang-kurangnya satu Bantuan Lain Yang Diterima.',
            'keahlian_parti.required' => 'Sila pilih Keanggotaan Parti.',
            'kecenderungan_politik.required' => 'Sila pilih Kecenderungan Politik.',
        ]);

        if (! $hasSumbangan) {
            $validated['bil_isi_rumah'] = null;
            $validated['pendapatan_isi_rumah'] = null;
            $validated['pekerjaan'] = null;
            $validated['jenis_pekerjaan'] = [];
            $validated['jenis_pekerjaan_lain'] = null;
            $validated['pemilik_rumah'] = null;
            $validated['pemilik_rumah_lain'] = null;
            $validated['jenis_sumbangan'] = [];
            $validated['jenis_sumbangan_lain'] = null;
            $validated['tujuan_sumbangan'] = [];
            $validated['tujuan_sumbangan_lain'] = null;
            $validated['bantuan_lain'] = [];
            $validated['bantuan_lain_lain'] = null;
            $validated['zpp_jenis_bantuan'] = [];
            $validated['isejahtera_program'] = null;
            $validated['jkm_program'] = null;
            $validated['jumlah_wang_tunai'] = null;
        }

        if (! $updateStatusPengundi) {
            $validated['status_pengundi'] = null;
        }

        // Admin Restriction: Ensure data is created for their Parlimen
        if ($user->isAdmin()) {
            if ($request->bandar !== ($user->bandar->nama ?? '')) {
                // Note: Assuming 'bandar' field in form is string name, and user->bandar is relation
                // Ideally should validate ID, but schema uses string for reports
                // For now, we'll enforce it matches the user's bandar name
                 abort(403, 'You can only create records for your Parlimen (' . ($user->bandar->nama ?? 'Unknown') . ').');
            }
        }

        // Process pemilik_rumah Lain-lain
        if ($validated['pemilik_rumah'] === 'Lain-lain' && !empty($validated['pemilik_rumah_lain'])) {
            $validated['pemilik_rumah'] = $validated['pemilik_rumah_lain'];
        }
        unset($validated['pemilik_rumah_lain']);

        // Process jenis_pekerjaan checkbox array (Kerajaan/Swasta) into comma-separated string
        if (isset($validated['jenis_pekerjaan']) && is_array($validated['jenis_pekerjaan'])) {
            $sektorKerajaan = $validated['jenis_pekerjaan'];
            if (in_array('Lain-lain', $sektorKerajaan) && !empty($validated['jenis_pekerjaan_lain'])) {
                $sektorKerajaan = array_filter($sektorKerajaan, fn($item) => $item !== 'Lain-lain');
                $sektorKerajaan[] = $validated['jenis_pekerjaan_lain'];
            }
            $validated['jenis_pekerjaan'] = implode(', ', $sektorKerajaan);
            $validated['jenis_pekerjaan_lain'] = null;
        }

        // Process checkbox arrays into comma-separated strings
        if (isset($validated['jenis_sumbangan']) && is_array($validated['jenis_sumbangan'])) {
            $jenisSumbangan = $validated['jenis_sumbangan'];
            $hasLainLain = count(array_filter($jenisSumbangan, fn($item) => stripos($item, 'lain') !== false)) > 0;
            if ($hasLainLain && !empty($validated['jenis_sumbangan_lain'])) {
                $jenisSumbangan = array_filter($jenisSumbangan, fn($item) => stripos($item, 'lain') === false);
                $jenisSumbangan[] = $validated['jenis_sumbangan_lain'];
            }
            $validated['jenis_sumbangan'] = implode(', ', $jenisSumbangan);
        }

        if (isset($validated['tujuan_sumbangan']) && is_array($validated['tujuan_sumbangan'])) {
            $tujuanSumbangan = $validated['tujuan_sumbangan'];
            $hasLainLain = count(array_filter($tujuanSumbangan, fn($item) => stripos($item, 'lain') !== false)) > 0;
            if ($hasLainLain && !empty($validated['tujuan_sumbangan_lain'])) {
                $tujuanSumbangan = array_filter($tujuanSumbangan, fn($item) => stripos($item, 'lain') === false);
                $tujuanSumbangan[] = $validated['tujuan_sumbangan_lain'];
            }
            $validated['tujuan_sumbangan'] = implode(', ', $tujuanSumbangan);
        }

        if (isset($validated['bantuan_lain']) && is_array($validated['bantuan_lain'])) {
            $bantuanLain = $validated['bantuan_lain'];
            $hasLainLain = count(array_filter($bantuanLain, fn($item) => stripos($item, 'lain') !== false)) > 0;
            if ($hasLainLain && !empty($validated['bantuan_lain_lain'])) {
                $bantuanLain = array_filter($bantuanLain, fn($item) => stripos($item, 'lain') === false);
                $bantuanLain[] = $validated['bantuan_lain_lain'];
            }
            $validated['bantuan_lain'] = implode(', ', $bantuanLain);
        }

        if (isset($validated['zpp_jenis_bantuan']) && is_array($validated['zpp_jenis_bantuan'])) {
            $validated['zpp_jenis_bantuan'] = implode(', ', $validated['zpp_jenis_bantuan']);
        }

        // Remove the _lain fields as they're not in the database
        unset($validated['jenis_sumbangan_lain'], $validated['tujuan_sumbangan_lain'], $validated['bantuan_lain_lain']);

        // Handle file upload
        if ($request->hasFile('kad_pengenalan')) {
            $path = $request->file('kad_pengenalan')->store('kad-pengenalan', 'public');
            $validated['kad_pengenalan'] = $path;
        }

        $validated['submitted_by'] = auth()->id();
        $validated['voter_color'] = VoterColorService::determine($validated['keahlian_parti'] ?? null, $validated['kecenderungan_politik'] ?? null);

        if ($hasSumbangan) {
            $record = HasilCulaan::create($validated);
            EditHistory::log('hasil_culaan', $record->id, 'created');

            return redirect()->route('reports.hasil-culaan.index')->with('success', 'Rekod Data Sumbangan berjaya ditambah');
        }

        $record = \App\Models\DataPengundi::create([
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
            'mpkk' => $validated['mpkk'] ?? null,
            'daerah_mengundi' => $validated['daerah_mengundi'] ?? null,
            'lokaliti' => $validated['lokaliti'] ?? null,
            'keahlian_parti' => $validated['keahlian_parti'] ?? null,
            'kecenderungan_politik' => $validated['kecenderungan_politik'] ?? null,
            'status_pengundi' => $validated['status_pengundi'] ?? null,
            'hubungan' => null,
            'submitted_by' => auth()->id(),
            'voter_color' => $validated['voter_color'] ?? null,
        ]);
        EditHistory::log('data_pengundi', $record->id, 'created');

        return redirect()->route('reports.data-pengundi.index')->with('success', 'Rekod Data Pengundi berjaya ditambah');
    }

    /**
     * Show the form for editing Hasil Culaan.
     */
    public function hasilCulaanEdit(HasilCulaan $hasilCulaan)
    {
        $user = auth()->user();

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

        $lokalitiList = Lokaliti::orderBy('nama')->get();

        $editHistories = EditHistory::where('model_type', 'hasil_culaan')
            ->where('model_id', $hasilCulaan->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

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
            'lokalitiList' => $lokalitiList,
            'editHistories' => $editHistories,
        ]);
    }

    /**
     * Update the specified Hasil Culaan.
     */
    public function hasilCulaanUpdate(Request $request, HasilCulaan $hasilCulaan)
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
            'mpkk' => 'nullable|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'lokaliti' => 'nullable|string|max:255',
            'bil_isi_rumah' => 'required|integer|min:1',
            'pendapatan_isi_rumah' => 'nullable|numeric|min:0',
            'pekerjaan' => 'required|in:Kerajaan,Swasta,Bekerja Sendiri,Tidak Bekerja',
            'jenis_pekerjaan' => 'required|array|min:1',
            'jenis_pekerjaan.*' => 'string|max:255',
            'jenis_pekerjaan_lain' => 'nullable|string|max:255',
            'pemilik_rumah' => 'required|string|max:255',
            'pemilik_rumah_lain' => 'nullable|string|max:255',
            'jenis_sumbangan' => 'required|array|min:1',
            'jenis_sumbangan_lain' => 'nullable|string|max:255',
            'tujuan_sumbangan' => 'required|array|min:1',
            'tujuan_sumbangan_lain' => 'nullable|string|max:255',
            'bantuan_lain' => 'required|array|min:1',
            'bantuan_lain_lain' => 'nullable|string|max:255',
            'zpp_jenis_bantuan' => 'nullable|array',
            'isejahtera_program' => 'nullable|string|max:255',
            'bkb_program' => 'nullable|string|max:255',
            'jumlah_bantuan_tunai' => 'nullable|numeric|min:0',
            'jumlah_wang_tunai' => 'nullable|numeric|min:0',
            'jkm_program' => 'nullable|string|max:255',
            'keahlian_parti' => 'required|string|max:255',
            'kecenderungan_politik' => 'required|string|max:255',
            'status_pengundi' => 'nullable|string|max:255',
            'kad_pengenalan' => 'nullable|image|max:5120', // 5MB max
            'nota' => 'nullable|string',
        ], [
            'jenis_sumbangan.required' => 'Sila pilih sekurang-kurangnya satu Jenis Sumbangan.',
            'tujuan_sumbangan.required' => 'Sila pilih sekurang-kurangnya satu Tujuan Sumbangan.',
            'bantuan_lain.required' => 'Sila pilih sekurang-kurangnya satu Bantuan Lain Yang Diterima.',
            'keahlian_parti.required' => 'Sila pilih Keanggotaan Parti.',
            'kecenderungan_politik.required' => 'Sila pilih Kecenderungan Politik.',
        ]);

        // Process pemilik_rumah Lain-lain
        if ($validated['pemilik_rumah'] === 'Lain-lain' && !empty($validated['pemilik_rumah_lain'])) {
            $validated['pemilik_rumah'] = $validated['pemilik_rumah_lain'];
        }
        unset($validated['pemilik_rumah_lain']);

        // Process jenis_pekerjaan checkbox array (Kerajaan/Swasta) into comma-separated string
        if (isset($validated['jenis_pekerjaan']) && is_array($validated['jenis_pekerjaan'])) {
            $sektorKerajaan = $validated['jenis_pekerjaan'];
            if (in_array('Lain-lain', $sektorKerajaan) && !empty($validated['jenis_pekerjaan_lain'])) {
                $sektorKerajaan = array_filter($sektorKerajaan, fn($item) => $item !== 'Lain-lain');
                $sektorKerajaan[] = $validated['jenis_pekerjaan_lain'];
            }
            $validated['jenis_pekerjaan'] = implode(', ', $sektorKerajaan);
            $validated['jenis_pekerjaan_lain'] = null;
        }

        // Process checkbox arrays into comma-separated strings
        if (isset($validated['jenis_sumbangan']) && is_array($validated['jenis_sumbangan'])) {
            $jenisSumbangan = $validated['jenis_sumbangan'];
            $hasLainLain = count(array_filter($jenisSumbangan, fn($item) => stripos($item, 'lain') !== false)) > 0;
            if ($hasLainLain && !empty($validated['jenis_sumbangan_lain'])) {
                $jenisSumbangan = array_filter($jenisSumbangan, fn($item) => stripos($item, 'lain') === false);
                $jenisSumbangan[] = $validated['jenis_sumbangan_lain'];
            }
            $validated['jenis_sumbangan'] = implode(', ', $jenisSumbangan);
        }

        if (isset($validated['tujuan_sumbangan']) && is_array($validated['tujuan_sumbangan'])) {
            $tujuanSumbangan = $validated['tujuan_sumbangan'];
            $hasLainLain = count(array_filter($tujuanSumbangan, fn($item) => stripos($item, 'lain') !== false)) > 0;
            if ($hasLainLain && !empty($validated['tujuan_sumbangan_lain'])) {
                $tujuanSumbangan = array_filter($tujuanSumbangan, fn($item) => stripos($item, 'lain') === false);
                $tujuanSumbangan[] = $validated['tujuan_sumbangan_lain'];
            }
            $validated['tujuan_sumbangan'] = implode(', ', $tujuanSumbangan);
        }

        if (isset($validated['bantuan_lain']) && is_array($validated['bantuan_lain'])) {
            $bantuanLain = $validated['bantuan_lain'];
            $hasLainLain = count(array_filter($bantuanLain, fn($item) => stripos($item, 'lain') !== false)) > 0;
            if ($hasLainLain && !empty($validated['bantuan_lain_lain'])) {
                $bantuanLain = array_filter($bantuanLain, fn($item) => stripos($item, 'lain') === false);
                $bantuanLain[] = $validated['bantuan_lain_lain'];
            }
            $validated['bantuan_lain'] = implode(', ', $bantuanLain);
        }

        if (isset($validated['zpp_jenis_bantuan']) && is_array($validated['zpp_jenis_bantuan'])) {
            $validated['zpp_jenis_bantuan'] = implode(', ', $validated['zpp_jenis_bantuan']);
        }

        // Remove the _lain fields as they're not in the database
        unset($validated['jenis_sumbangan_lain'], $validated['tujuan_sumbangan_lain'], $validated['bantuan_lain_lain']);

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

        // Auto-calculate voter color
        $validated['voter_color'] = VoterColorService::determine($validated['keahlian_parti'] ?? null, $validated['kecenderungan_politik'] ?? null);

        // Track changes for edit history
        $changes = [];
        foreach ($validated as $key => $value) {
            $old = $hasilCulaan->getOriginal($key);
            if ($old != $value && $key !== 'kad_pengenalan') {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }

        $hasilCulaan->update($validated);

        if (!empty($changes)) {
            EditHistory::log('hasil_culaan', $hasilCulaan->id, 'updated', $changes);
        }

        return redirect()->route('reports.hasil-culaan.index')->with('success', 'Rekod berjaya dikemaskini');
    }

    /**
     * Remove the specified Hasil Culaan.
     */
    public function hasilCulaanDestroy(HasilCulaan $hasilCulaan)
    {
        $hasilCulaan->delete();

        return redirect()->route('reports.hasil-culaan.index')->with('success', 'Rekod berjaya dipadam');
    }

    /**
     * Toggle deceased status for Hasil Culaan.
     */
    public function hasilCulaanToggleDeceased(HasilCulaan $hasilCulaan)
    {
        $hasilCulaan->update(['is_deceased' => !$hasilCulaan->is_deceased]);
        return response()->json(['is_deceased' => $hasilCulaan->is_deceased]);
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
     * Show the form for editing Data Pengundi.
     */
    public function dataPengundiEdit(DataPengundi $dataPengundi)
    {
        $user = auth()->user();

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

        $lokalitiList = Lokaliti::orderBy('nama')->get();

        $editHistories = EditHistory::where('model_type', 'data_pengundi')
            ->where('model_id', $dataPengundi->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

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
            'lokalitiList' => $lokalitiList,
            'editHistories' => $editHistories,
        ]);
    }

    /**
     * Update the specified Data Pengundi.
     */
    public function dataPengundiUpdate(Request $request, DataPengundi $dataPengundi)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'no_ic' => 'required|string|digits:12|unique:data_pengundi,no_ic,' . $dataPengundi->id,
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
            'mpkk' => 'nullable|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'lokaliti' => 'nullable|string|max:255',
            'keahlian_parti' => 'required|string|max:255',
            'kecenderungan_politik' => 'required|string|max:255',
            'status_pengundi' => 'nullable|string|max:255',
        ], [
            'no_ic.unique' => 'No. Kad Pengenalan ini telah didaftarkan dalam Data Pengundi.',
            'keahlian_parti.required' => 'Sila pilih Keanggotaan Parti.',
            'kecenderungan_politik.required' => 'Sila pilih Kecenderungan Politik.',
        ]);

        $validated['voter_color'] = VoterColorService::determine($validated['keahlian_parti'] ?? null, $validated['kecenderungan_politik'] ?? null);

        $changes = [];
        foreach ($validated as $key => $value) {
            $old = $dataPengundi->getOriginal($key);
            if ($old != $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }

        $dataPengundi->update($validated);

        if (!empty($changes)) {
            EditHistory::log('data_pengundi', $dataPengundi->id, 'updated', $changes);
        }

        return redirect()->route('reports.data-pengundi.index')->with('success', 'Rekod berjaya dikemaskini');
    }

    /**
     * Remove the specified Data Pengundi.
     */
    public function dataPengundiDestroy(DataPengundi $dataPengundi)
    {
        $dataPengundi->delete();

        return redirect()->route('reports.data-pengundi.index')->with('success', 'Rekod berjaya dipadam');
    }

    /**
     * Toggle deceased status for Data Pengundi.
     */
    public function dataPengundiToggleDeceased(DataPengundi $dataPengundi)
    {
        $dataPengundi->update(['is_deceased' => !$dataPengundi->is_deceased]);
        return response()->json(['is_deceased' => $dataPengundi->is_deceased]);
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
            // Find matching Bandar by city name (case-insensitive)
            $bandar = \App\Models\Bandar::whereRaw('LOWER(nama) = ?', [strtolower($postcode->city)])->first();
            
            // Fallback to like if exact match fails
            if (!$bandar) {
                $bandar = \App\Models\Bandar::where('nama', 'like', '%' . $postcode->city . '%')->first();
            }
            
            // Find matching Negeri by state name
            $negeri = \App\Models\Negeri::whereRaw('LOWER(nama) = ?', [strtolower($postcode->state)])->first();
            
            if (!$negeri) {
                $negeri = \App\Models\Negeri::where('nama', 'like', '%' . $postcode->state . '%')->first();
            }
            
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

    public function getMpkkByKadun(Request $request)
    {
        $kadunNama = $request->input('kadun');
        
        if (!$kadunNama) {
            return response()->json([]);
        }

        // Find KADUN
        $kadun = \App\Models\Kadun::whereRaw('LOWER(nama) = ?', [strtolower($kadunNama)])->first();

        if (!$kadun) {
            return response()->json([]);
        }

        // Get MPKK for this KADUN
        $mpkkList = \App\Models\Mpkk::where('kadun_id', $kadun->id)
            ->orderBy('nama')
            ->get();

        return response()->json($mpkkList);
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

        // Primary: query voter database for distinct KADUN values
        $activeBatch = \App\Models\UploadBatch::where('is_active', true)->first();
        if ($activeBatch) {
            $voterKadun = \App\Models\PangkalanDataPengundi::where('upload_batch_id', $activeBatch->id)
                ->whereRaw('LOWER(parlimen) = ?', [strtolower($bandarNama)])
                ->whereNotNull('kadun')
                ->where('kadun', '!=', '')
                ->distinct()
                ->orderBy('kadun')
                ->pluck('kadun');

            if ($voterKadun->isNotEmpty()) {
                return response()->json($voterKadun->map(fn($nama, $i) => (object) ['id' => $i + 1, 'nama' => $nama])->values());
            }
        }

        // Fallback: master data
        $bandar = \App\Models\Bandar::whereRaw('LOWER(nama) = ?', [strtolower($bandarNama)])->first();
        if ($bandar) {
            return response()->json(\App\Models\Kadun::where('bandar_id', $bandar->id)->orderBy('nama')->get());
        }

        return response()->json([]);
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

        // Primary: query voter database for distinct DM values
        $activeBatch = \App\Models\UploadBatch::where('is_active', true)->first();
        if ($activeBatch) {
            $voterDM = \App\Models\PangkalanDataPengundi::where('upload_batch_id', $activeBatch->id)
                ->whereRaw('LOWER(parlimen) = ?', [strtolower($bandarNama)])
                ->whereNotNull('daerah_mengundi')
                ->where('daerah_mengundi', '!=', '')
                ->distinct()
                ->orderBy('daerah_mengundi')
                ->pluck('daerah_mengundi');

            if ($voterDM->isNotEmpty()) {
                return response()->json($voterDM->map(fn($nama, $i) => (object) ['id' => $i + 1, 'nama' => $nama])->values());
            }
        }

        // Fallback: master data
        $bandar = \App\Models\Bandar::whereRaw('LOWER(nama) = ?', [strtolower($bandarNama)])->first();
        if ($bandar) {
            return response()->json(\App\Models\DaerahMengundi::where('bandar_id', $bandar->id)->orderBy('nama')->get());
        }

        return response()->json([]);
    }

    /**
     * Get Lokaliti by Daerah Mengundi name.
     */
    public function getLokalitiBydaerahMengundi(Request $request)
    {
        $dmNama = $request->input('daerah_mengundi');

        if (!$dmNama) {
            return response()->json([]);
        }

        // Primary: query voter database for distinct lokaliti values
        $activeBatch = \App\Models\UploadBatch::where('is_active', true)->first();
        if ($activeBatch) {
            $voterLokaliti = \App\Models\PangkalanDataPengundi::where('upload_batch_id', $activeBatch->id)
                ->whereRaw('LOWER(daerah_mengundi) = ?', [strtolower($dmNama)])
                ->whereNotNull('lokaliti')
                ->where('lokaliti', '!=', '')
                ->distinct()
                ->orderBy('lokaliti')
                ->pluck('lokaliti');

            if ($voterLokaliti->isNotEmpty()) {
                return response()->json($voterLokaliti->map(fn($nama, $i) => (object) ['id' => $i + 1, 'nama' => $nama])->values());
            }
        }

        // Fallback: master data
        $dmIds = \App\Models\DaerahMengundi::whereRaw('LOWER(nama) = ?', [strtolower($dmNama)])->pluck('id');
        if ($dmIds->isNotEmpty()) {
            return response()->json(\App\Models\Lokaliti::whereIn('daerah_mengundi_id', $dmIds)->orderBy('nama')->get());
        }

        return response()->json([]);
    }

    /**
     * Delete an edit history entry (super_admin only).
     */
    public function deleteHistory(EditHistory $editHistory)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403);
        }
        $editHistory->delete();
        return back()->with('success', 'Sejarah berjaya dipadam.');
    }

    /**
     * Check if user can modify Hasil Culaan record.
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
     * Check if user can modify Data Pengundi record.
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
    }
}
