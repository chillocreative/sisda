<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Negeri;
use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Mpkk;
use App\Models\DaerahMengundi;
use App\Models\TujuanSumbangan;
use App\Models\JenisSumbangan;
use App\Models\BantuanLain;
use App\Models\KeahlianParti;
use App\Models\KecenderunganPolitik;
use App\Models\Hubungan;
use App\Models\Bangsa;
use App\Models\Lokaliti;

class MasterDataController extends Controller
{
    /**
     * Display the master data dashboard.
     */
    public function index()
    {
        $categories = [
            [
                'name' => 'Negeri',
                'count' => Negeri::count(),
                'icon' => 'MapPin',
                'color' => 'emerald',
                'description' => 'Negeri di Malaysia',
                'route' => 'master-data.negeri.index'
            ],
            [
                'name' => 'Bandar',
                'count' => Bandar::count(),
                'icon' => 'Building2',
                'color' => 'sky',
                'description' => 'Bandar dan daerah',
                'route' => 'master-data.bandar.index'
            ],
            [
                'name' => 'Parlimen',
                'count' => Bandar::count(), // Using Bandar count as they share the table
                'icon' => 'Landmark',
                'color' => 'violet',
                'description' => 'Kawasan Parlimen',
                'route' => 'master-data.parlimen.index'
            ],
            [
                'name' => 'KADUN',
                'count' => Kadun::count(),
                'icon' => 'Vote',
                'color' => 'amber',
                'description' => 'Kawasan Dewan Undangan Negeri',
                'route' => 'master-data.kadun.index'
            ],
            [
                'name' => 'MPKK',
                'count' => Mpkk::count(),
                'icon' => 'Users2',
                'color' => 'rose',
                'description' => 'Majlis Pembangunan dan Keselamatan Kampung',
                'route' => 'master-data.mpkk.index'
            ],
            [
                'name' => 'Daerah Mengundi',
                'count' => DaerahMengundi::count(),
                'icon' => 'MapPin', // Using MapPin as placeholder, or maybe another icon like 'LocateFixed' if available, but MapPin is safe
                'color' => 'slate',
                'description' => 'Kawasan Daerah Mengundi',
                'route' => 'master-data.daerah-mengundi.index'
            ],
            [
                'name' => 'Tujuan Sumbangan',
                'count' => TujuanSumbangan::count(),
                'icon' => 'Gift',
                'color' => 'cyan',
                'description' => 'Kategori tujuan sumbangan',
                'route' => 'master-data.tujuan-sumbangan.index'
            ],
            [
                'name' => 'Jenis Sumbangan',
                'count' => JenisSumbangan::count(),
                'icon' => 'Package',
                'color' => 'indigo',
                'description' => 'Jenis-jenis sumbangan',
                'route' => 'master-data.jenis-sumbangan.index'
            ],
            [
                'name' => 'Bantuan Lain',
                'count' => BantuanLain::count(),
                'icon' => 'HandHeart',
                'color' => 'pink',
                'description' => 'Kategori bantuan lain',
                'route' => 'master-data.bantuan-lain.index'
            ],
            [
                'name' => 'Keahlian Parti',
                'count' => KeahlianParti::count(),
                'icon' => 'Flag',
                'color' => 'orange',
                'description' => 'Parti politik',
                'route' => 'master-data.keahlian-parti.index'
            ],
            [
                'name' => 'Kecenderungan Politik',
                'count' => KecenderunganPolitik::count(),
                'icon' => 'TrendingUp',
                'color' => 'teal',
                'description' => 'Kecenderungan politik',
                'route' => 'master-data.kecenderungan-politik.index'
            ],

            [
                'name' => 'Bangsa',
                'count' => Bangsa::count(),
                'icon' => 'Users2',
                'color' => 'blue',
                'description' => 'Senarai Bangsa',
                'route' => 'master-data.bangsa.index'
            ],
        ];

        return Inertia::render('MasterData/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Display a listing of Negeri.
     */
    public function negeriIndex(Request $request)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $query = Negeri::query();

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('nama', 'like', "%{$search}%");
        }

        $negeri = $query->orderBy('nama', 'asc')->paginate(15);

        return Inertia::render('MasterData/Negeri/Index', [
            'negeri' => $negeri,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Negeri.
     */
    public function negeriStore(Request $request)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:negeri,nama',
        ]);

        Negeri::create($validated);

        return redirect()->route('master-data.negeri.index')->with('success', 'Negeri berjaya ditambah');
    }

    /**
     * Update the specified Negeri.
     */
    public function negeriUpdate(Request $request, Negeri $negeri)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:negeri,nama,' . $negeri->id,
        ]);

        $negeri->update($validated);

        return redirect()->route('master-data.negeri.index')->with('success', 'Negeri berjaya dikemaskini');
    }

    /**
     * Remove the specified Negeri.
     */
    public function negeriDestroy(Negeri $negeri)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $negeri->delete();

        return redirect()->route('master-data.negeri.index')->with('success', 'Negeri berjaya dipadam');
    }


    /**
     * Display a listing of Bandar.
     */
    public function bandarIndex(Request $request)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = Bandar::with('negeri');

        // Admin Restriction: Only view their assigned Bandar (Parlimen)
        if ($user->isAdmin()) {
            $query->where('id', $user->bandar_id);
        }

        // Filter by negeri if provided via query parameter (Super Admin only)
        if ($user->isSuperAdmin()) {
            $negeriId = $request->input('negeri_id');
            if ($negeriId) {
                $query->where('negeri_id', $negeriId);
            }
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kod_parlimen', 'like', "%{$search}%");
            });
        }

        $bandar = $query->orderBy('nama', 'asc')->paginate(15);
        $negeriList = $user->isSuperAdmin() ? Negeri::orderBy('nama')->get() : collect();
        $selectedNegeri = ($user->isSuperAdmin() && $request->input('negeri_id')) ? Negeri::find($request->input('negeri_id')) : null;

        return Inertia::render('MasterData/Bandar/Index', [
            'bandar' => $bandar,
            'negeriList' => $negeriList,
            'selectedNegeri' => $selectedNegeri,
            'filters' => $request->only(['search', 'negeri_id']),
        ]);
    }

    /**
     * Store a newly created Bandar.
     */
    public function bandarStore(Request $request)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kod_parlimen' => 'nullable|string|max:10',
            'negeri_id' => 'required|exists:negeri,id',
        ]);

        Bandar::create($validated);

        $redirectRoute = $request->negeri_id 
            ? route('master-data.bandar.index', $request->negeri_id)
            : route('master-data.bandar.map');

        return redirect($redirectRoute)->with('success', 'Bandar berjaya ditambah');
    }

    /**
     * Update the specified Bandar.
     */
    public function bandarUpdate(Request $request, Bandar $bandar)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kod_parlimen' => 'nullable|string|max:10',
            'negeri_id' => 'required|exists:negeri,id',
        ]);

        $bandar->update($validated);

        $redirectRoute = $bandar->negeri_id 
            ? route('master-data.bandar.index', $bandar->negeri_id)
            : route('master-data.bandar.map');

        return redirect($redirectRoute)->with('success', 'Bandar berjaya dikemaskini');
    }

    /**
     * Remove the specified Bandar.
     */
    public function bandarDestroy(Bandar $bandar)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $negeriId = $bandar->negeri_id;
        $bandar->delete();

        $redirectRoute = $negeriId 
            ? route('master-data.bandar.index', $negeriId)
            : route('master-data.bandar.map');

        return redirect($redirectRoute)->with('success', 'Bandar berjaya dipadam');
    }

    /**
     * Display a listing of Parlimen.
     */
    public function parlimenIndex(Request $request)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = Bandar::with('negeri');

        // Admin Restriction: Only view their assigned Parlimen
        if ($user->isAdmin()) {
            $query->where('id', $user->bandar_id);
        }

        // Filter by negeri if provided via query parameter (Super Admin only)
        if ($user->isSuperAdmin()) {
            $negeriId = $request->input('negeri_id');
            if ($negeriId) {
                $query->where('negeri_id', $negeriId);
            }
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kod_parlimen', 'like', "%{$search}%");
            });
        }

        $parlimen = $query->orderBy('nama', 'asc')->paginate(15);
        $negeriList = $user->isSuperAdmin() ? Negeri::orderBy('nama')->get() : collect();
        $selectedNegeri = ($user->isSuperAdmin() && $request->input('negeri_id')) ? Negeri::find($request->input('negeri_id')) : null;

        return Inertia::render('MasterData/Parlimen/Index', [
            'parlimen' => $parlimen,
            'negeriList' => $negeriList,
            'selectedNegeri' => $selectedNegeri,
            'filters' => $request->only(['search', 'negeri_id']),
        ]);
    }

    /**
     * Store a newly created Parlimen.
     */
    public function parlimenStore(Request $request)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kod_parlimen' => 'nullable|string|max:10',
            'negeri_id' => 'required|exists:negeri,id',
        ]);

        Bandar::create($validated);

        return redirect()->route('master-data.parlimen.index')->with('success', 'Parlimen berjaya ditambah');
    }

    /**
     * Update the specified Parlimen.
     */
    public function parlimenUpdate(Request $request, Bandar $parlimen)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kod_parlimen' => 'nullable|string|max:10',
            'negeri_id' => 'required|exists:negeri,id',
        ]);

        $parlimen->update($validated);

        return redirect()->route('master-data.parlimen.index')->with('success', 'Parlimen berjaya dikemaskini');
    }

    /**
     * Remove the specified Parlimen.
     */
    public function parlimenDestroy(Bandar $parlimen)
    {
        // Check if user is Super Admin
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Unauthorized action.');
        }

        $parlimen->delete();

        return redirect()->route('master-data.parlimen.index')->with('success', 'Parlimen berjaya dipadam');
    }

    /**
     * Display a listing of KADUN.
     */
    public function kadunIndex(Request $request, $bandarId = null)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = Kadun::with('bandar.negeri');

        // Admin Restriction: Only view KADUN in their assigned Parlimen
        if ($user->isAdmin()) {
            $query->where('bandar_id', $user->bandar_id);
        } elseif ($bandarId) {
            // Super Admin can filter by bandar
            $query->where('bandar_id', $bandarId);
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kod_dun', 'like', "%{$search}%");
            });
        }

        $kadun = $query->orderBy('nama', 'asc')->paginate(15);
        
        // Admin can only see their Bandar, Super Admin can see all
        $bandarList = $user->isSuperAdmin() 
            ? Bandar::with('negeri')->orderBy('nama')->get()
            : Bandar::with('negeri')->where('id', $user->bandar_id)->get();
            
        $selectedBandar = $bandarId ? Bandar::with('negeri')->find($bandarId) : null;

        return Inertia::render('MasterData/Kadun/Index', [
            'kadun' => $kadun,
            'bandarList' => $bandarList,
            'selectedBandar' => $selectedBandar,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created KADUN.
     */
    public function kadunStore(Request $request)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kod_dun' => 'nullable|string|max:10',
            'bandar_id' => 'required|exists:bandar,id',
        ]);

        // Admin Restriction: Can only add KADUN to their assigned Parlimen
        if ($user->isAdmin() && $validated['bandar_id'] != $user->bandar_id) {
            abort(403, 'Unauthorized action.');
        }

        Kadun::create($validated);

        $redirectRoute = $request->bandar_id 
            ? route('master-data.kadun.index', $request->bandar_id)
            : route('master-data.kadun.index');

        return redirect($redirectRoute)->with('success', 'KADUN berjaya ditambah');
    }

    /**
     * Update the specified KADUN.
     */
    public function kadunUpdate(Request $request, Kadun $kadun)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admin Restriction: Can only update KADUN in their assigned Parlimen
        if ($user->isAdmin() && $kadun->bandar_id != $user->bandar_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kod_dun' => 'nullable|string|max:10',
            'bandar_id' => 'required|exists:bandar,id',
        ]);

        // Admin Restriction: Cannot move KADUN to another Parlimen
        if ($user->isAdmin() && $validated['bandar_id'] != $user->bandar_id) {
            abort(403, 'Unauthorized action.');
        }

        $kadun->update($validated);

        $redirectRoute = $kadun->bandar_id 
            ? route('master-data.kadun.index', $kadun->bandar_id)
            : route('master-data.kadun.index');

        return redirect($redirectRoute)->with('success', 'KADUN berjaya dikemaskini');
    }

    /**
     * Remove the specified KADUN.
     */
    public function kadunDestroy(Kadun $kadun)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admin Restriction: Can only delete KADUN in their assigned Parlimen
        if ($user->isAdmin() && $kadun->bandar_id != $user->bandar_id) {
            abort(403, 'Unauthorized action.');
        }

        $bandarId = $kadun->bandar_id;
        $kadun->delete();

        $redirectRoute = $bandarId 
            ? route('master-data.kadun.index', $bandarId)
            : route('master-data.kadun.index');

        return redirect($redirectRoute)->with('success', 'KADUN berjaya dipadam');
    }

    /**
     * Display a listing of MPKK.
     */
    public function mpkkIndex(Request $request, $kadunId = null)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = Mpkk::with('kadun.bandar');

        // Admin Restriction: Only view MPKK in their assigned Parlimen
        if ($user->isAdmin()) {
            $query->whereHas('kadun', function($q) use ($user) {
                $q->where('bandar_id', $user->bandar_id);
            });
        } elseif ($kadunId) {
            // Super Admin can filter by kadun
            $query->where('kadun_id', $kadunId);
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhereHas('kadun', function($q) use ($search) {
                      $q->where('nama', 'like', "%{$search}%")
                        ->orWhereHas('bandar', function($q) use ($search) {
                            $q->where('nama', 'like', "%{$search}%");
                        });
                  });
            });
        }

        $mpkk = $query->orderBy('nama', 'asc')->paginate(15);
        
        // Admin can only see KADUN in their Parlimen, Super Admin can see all
        $kadunList = $user->isSuperAdmin() 
            ? Kadun::with('bandar')->orderBy('nama')->get()
            : Kadun::with('bandar')->where('bandar_id', $user->bandar_id)->orderBy('nama')->get();
            
        $selectedKadun = $kadunId ? Kadun::with('bandar')->find($kadunId) : null;

        return Inertia::render('MasterData/Mpkk/Index', [
            'mpkk' => $mpkk,
            'kadunList' => $kadunList,
            'selectedKadun' => $selectedKadun,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created MPKK.
     */
    public function mpkkStore(Request $request)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kadun_id' => 'required|exists:kadun,id',
        ]);

        // Admin Restriction: Can only add MPKK to KADUN in their assigned Parlimen
        if ($user->isAdmin()) {
            $kadun = Kadun::find($validated['kadun_id']);
            if (!$kadun || $kadun->bandar_id != $user->bandar_id) {
                abort(403, 'Unauthorized action.');
            }
        }

        Mpkk::create($validated);

        $redirectRoute = $request->kadun_id 
            ? route('master-data.mpkk.index', $request->kadun_id)
            : route('master-data.mpkk.index');

        return redirect($redirectRoute)->with('success', 'MPKK berjaya ditambah');
    }

    /**
     * Update the specified MPKK.
     */
    public function mpkkUpdate(Request $request, Mpkk $mpkk)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admin Restriction: Can only update MPKK in their assigned Parlimen
        if ($user->isAdmin() && $mpkk->kadun->bandar_id != $user->bandar_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kadun_id' => 'required|exists:kadun,id',
        ]);

        // Admin Restriction: Cannot move MPKK to KADUN in another Parlimen
        if ($user->isAdmin()) {
            $kadun = Kadun::find($validated['kadun_id']);
            if (!$kadun || $kadun->bandar_id != $user->bandar_id) {
                abort(403, 'Unauthorized action.');
            }
        }

        $mpkk->update($validated);

        $redirectRoute = $mpkk->kadun_id 
            ? route('master-data.mpkk.index', $mpkk->kadun_id)
            : route('master-data.mpkk.index');

        return redirect($redirectRoute)->with('success', 'MPKK berjaya dikemaskini');
    }

    /**
     * Remove the specified MPKK.
     */
    public function mpkkDestroy(Mpkk $mpkk)
    {
        $user = auth()->user();

        // Check if user is Super Admin or Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admin Restriction: Can only delete MPKK in their assigned Parlimen
        if ($user->isAdmin() && $mpkk->kadun->bandar_id != $user->bandar_id) {
            abort(403, 'Unauthorized action.');
        }

        $kadunId = $mpkk->kadun_id;
        $mpkk->delete();

        $redirectRoute = $kadunId 
            ? route('master-data.mpkk.index', $kadunId)
            : route('master-data.mpkk.index');

        return redirect($redirectRoute)->with('success', 'MPKK berjaya dipadam');
    }

    /**
     * Display a listing of Daerah Mengundi.
     */
    public function daerahMengundiIndex(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = DaerahMengundi::with('bandar.negeri');

        // Admin Restriction: Only view Daerah Mengundi in their assigned Parlimen
        if ($user->isAdmin()) {
            $query->where('bandar_id', $user->bandar_id);
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kod_dm', 'like', "%{$search}%");
            });
        }

        $daerahMengundi = $query->orderBy('kod_dm', 'asc')->paginate(15);

        return Inertia::render('MasterData/DaerahMengundi/Index', [
            'daerahMengundi' => $daerahMengundi,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Daerah Mengundi.
     */
    public function daerahMengundiStore(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'kod_dm' => 'required|string|max:255',
            'nama' => 'required|string|max:255',
        ]);

        // Auto-assign bandar_id based on user role
        if ($user->isAdmin()) {
            $validated['bandar_id'] = $user->bandar_id;
        } else {
            // Super Admin must specify or default to first bandar
            $validated['bandar_id'] = $request->bandar_id ?? Bandar::first()->id;
        }

        DaerahMengundi::create($validated);

        return redirect()->route('master-data.daerah-mengundi.index')
            ->with('success', 'Daerah Mengundi berjaya ditambah');
    }

    /**
     * Update the specified Daerah Mengundi.
     */
    public function daerahMengundiUpdate(Request $request, DaerahMengundi $daerahMengundi)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admin Restriction: Can only update Daerah Mengundi in their Parlimen
        if ($user->isAdmin() && $daerahMengundi->bandar_id != $user->bandar_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'kod_dm' => 'required|string|max:255',
            'nama' => 'required|string|max:255',
        ]);

        $daerahMengundi->update($validated);

        return redirect()->route('master-data.daerah-mengundi.index')
            ->with('success', 'Daerah Mengundi berjaya dikemaskini');
    }

    /**
     * Remove the specified Daerah Mengundi.
     */
    public function daerahMengundiDestroy(DaerahMengundi $daerahMengundi)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admin Restriction: Can only delete Daerah Mengundi in their Parlimen
        if ($user->isAdmin() && $daerahMengundi->bandar_id != $user->bandar_id) {
            abort(403, 'Unauthorized action.');
        }

        $daerahMengundi->delete();

        return redirect()->route('master-data.daerah-mengundi.index')
            ->with('success', 'Daerah Mengundi berjaya dipadam');
    }

    /**
     * Display a listing of Tujuan Sumbangan.
     */
    public function tujuanSumbanganIndex(Request $request)
    {
        $user = auth()->user();

        // Allow Super Admin and Admin
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = TujuanSumbangan::query();

        // Admin Restriction: Only view data in their Parlimen (Bandar)
        // if ($user->isAdmin()) {
        //     $query->where('bandar_id', $user->bandar_id);
        // }

        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', "%{$request->search}%");
        }

        $tujuanSumbangan = $query->paginate(15);

        return Inertia::render('MasterData/TujuanSumbangan/Index', [
            'tujuanSumbangan' => $tujuanSumbangan,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Tujuan Sumbangan.
     */
    public function tujuanSumbanganStore(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        // if ($user->isAdmin()) {
        //     $validated['bandar_id'] = $user->bandar_id;
        // }

        TujuanSumbangan::create($validated);

        return redirect()->route('master-data.tujuan-sumbangan.index')
            ->with('success', 'Tujuan Sumbangan berjaya ditambah');
    }

    /**
     * Update the specified Tujuan Sumbangan.
     */
    public function tujuanSumbanganUpdate(Request $request, TujuanSumbangan $tujuanSumbangan)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $tujuanSumbangan->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $tujuanSumbangan->update($validated);

        return redirect()->route('master-data.tujuan-sumbangan.index')
            ->with('success', 'Tujuan Sumbangan berjaya dikemaskini');
    }

    /**
     * Remove the specified Tujuan Sumbangan.
     */
    public function tujuanSumbanganDestroy(TujuanSumbangan $tujuanSumbangan)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $tujuanSumbangan->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $tujuanSumbangan->delete();

        return redirect()->route('master-data.tujuan-sumbangan.index')
            ->with('success', 'Tujuan Sumbangan berjaya dipadam');
    }

    /**
     * Display a listing of Jenis Sumbangan.
     */
    public function jenisSumbanganIndex(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = JenisSumbangan::query();

        // Admin Restriction: Only view data in their Parlimen (Bandar)
        // if ($user->isAdmin()) {
        //     $query->where('bandar_id', $user->bandar_id);
        // }

        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', "%{$request->search}%");
        }

        $jenisSumbangan = $query->paginate(15);

        return Inertia::render('MasterData/JenisSumbangan/Index', [
            'jenisSumbangan' => $jenisSumbangan,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Jenis Sumbangan.
     */
    public function jenisSumbanganStore(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        // if ($user->isAdmin()) {
        //     $validated['bandar_id'] = $user->bandar_id;
        // }

        JenisSumbangan::create($validated);

        return redirect()->route('master-data.jenis-sumbangan.index')
            ->with('success', 'Jenis Sumbangan berjaya ditambah');
    }

    /**
     * Update the specified Jenis Sumbangan.
     */
    public function jenisSumbanganUpdate(Request $request, JenisSumbangan $jenisSumbangan)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $jenisSumbangan->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $jenisSumbangan->update($validated);

        return redirect()->route('master-data.jenis-sumbangan.index')
            ->with('success', 'Jenis Sumbangan berjaya dikemaskini');
    }

    /**
     * Remove the specified Jenis Sumbangan.
     */
    public function jenisSumbanganDestroy(JenisSumbangan $jenisSumbangan)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $jenisSumbangan->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $jenisSumbangan->delete();

        return redirect()->route('master-data.jenis-sumbangan.index')
            ->with('success', 'Jenis Sumbangan berjaya dipadam');
    }

    /**
     * Display a listing of Bantuan Lain.
     */
    public function bantuanLainIndex(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = BantuanLain::query();

        // Admin Restriction: Only view data in their Parlimen (Bandar)
        // if ($user->isAdmin()) {
        //     $query->where('bandar_id', $user->bandar_id);
        // }

        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', "%{$request->search}%");
        }

        $bantuanLain = $query->paginate(15);

        return Inertia::render('MasterData/BantuanLain/Index', [
            'bantuanLain' => $bantuanLain,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Bantuan Lain.
     */
    public function bantuanLainStore(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        // if ($user->isAdmin()) {
        //     $validated['bandar_id'] = $user->bandar_id;
        // }

        BantuanLain::create($validated);

        return redirect()->route('master-data.bantuan-lain.index')
            ->with('success', 'Bantuan Lain berjaya ditambah');
    }

    /**
     * Update the specified Bantuan Lain.
     */
    public function bantuanLainUpdate(Request $request, BantuanLain $bantuanLain)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $bantuanLain->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $bantuanLain->update($validated);

        return redirect()->route('master-data.bantuan-lain.index')
            ->with('success', 'Bantuan Lain berjaya dikemaskini');
    }

    /**
     * Remove the specified Bantuan Lain.
     */
    public function bantuanLainDestroy(BantuanLain $bantuanLain)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $bantuanLain->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $bantuanLain->delete();

        return redirect()->route('master-data.bantuan-lain.index')
            ->with('success', 'Bantuan Lain berjaya dipadam');
    }

    /**
     * Display a listing of Keahlian Parti.
     */
    public function keahlianPartiIndex(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = KeahlianParti::query();

        // Admin Restriction: Only view data in their Parlimen (Bandar)
        // if ($user->isAdmin()) {
        //     $query->where('bandar_id', $user->bandar_id);
        // }

        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', "%{$request->search}%");
        }

        $keahlianParti = $query->paginate(15);

        return Inertia::render('MasterData/KeahlianParti/Index', [
            'keahlianParti' => $keahlianParti,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Keahlian Parti.
     */
    public function keahlianPartiStore(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        // if ($user->isAdmin()) {
        //     $validated['bandar_id'] = $user->bandar_id;
        // }

        KeahlianParti::create($validated);

        return redirect()->route('master-data.keahlian-parti.index')
            ->with('success', 'Keahlian Parti berjaya ditambah');
    }

    /**
     * Update the specified Keahlian Parti.
     */
    public function keahlianPartiUpdate(Request $request, KeahlianParti $keahlianParti)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $keahlianParti->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $keahlianParti->update($validated);

        return redirect()->route('master-data.keahlian-parti.index')
            ->with('success', 'Keahlian Parti berjaya dikemaskini');
    }

    /**
     * Remove the specified Keahlian Parti.
     */
    public function keahlianPartiDestroy(KeahlianParti $keahlianParti)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $keahlianParti->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $keahlianParti->delete();

        return redirect()->route('master-data.keahlian-parti.index')
            ->with('success', 'Keahlian Parti berjaya dipadam');
    }

    /**
     * Display a listing of Kecenderungan Politik.
     */
    public function kecenderunganPolitikIndex(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = KecenderunganPolitik::query();

        // Admin Restriction: Only view data in their Parlimen (Bandar)
        // if ($user->isAdmin()) {
        //     $query->where('bandar_id', $user->bandar_id);
        // }

        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', "%{$request->search}%");
        }

        $kecenderunganPolitik = $query->paginate(15);

        return Inertia::render('MasterData/KecenderunganPolitik/Index', [
            'kecenderunganPolitik' => $kecenderunganPolitik,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Kecenderungan Politik.
     */
    public function kecenderunganPolitikStore(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        // if ($user->isAdmin()) {
        //     $validated['bandar_id'] = $user->bandar_id;
        // }

        KecenderunganPolitik::create($validated);

        return redirect()->route('master-data.kecenderungan-politik.index')
            ->with('success', 'Kecenderungan Politik berjaya ditambah');
    }

    /**
     * Update the specified Kecenderungan Politik.
     */
    public function kecenderunganPolitikUpdate(Request $request, KecenderunganPolitik $kecenderunganPolitik)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $kecenderunganPolitik->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $kecenderunganPolitik->update($validated);

        return redirect()->route('master-data.kecenderungan-politik.index')
            ->with('success', 'Kecenderungan Politik berjaya dikemaskini');
    }

    /**
     * Remove the specified Kecenderungan Politik.
     */
    public function kecenderunganPolitikDestroy(KecenderunganPolitik $kecenderunganPolitik)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $kecenderunganPolitik->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $kecenderunganPolitik->delete();

        return redirect()->route('master-data.kecenderungan-politik.index')
            ->with('success', 'Kecenderungan Politik berjaya dipadam');
    }

    /**
     * Display a listing of Hubungan.
     */
    public function hubunganIndex(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = Hubungan::query();

        // Admin Restriction: Only view data in their Parlimen (Bandar)
        // if ($user->isAdmin()) {
        //     $query->where('bandar_id', $user->bandar_id);
        // }

        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', "%{$request->search}%");
        }

        $hubungan = $query->paginate(15);

        return Inertia::render('MasterData/Hubungan/Index', [
            'hubungan' => $hubungan,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Hubungan.
     */
    public function hubunganStore(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        // if ($user->isAdmin()) {
        //     $validated['bandar_id'] = $user->bandar_id;
        // }

        Hubungan::create($validated);

        return redirect()->route('master-data.hubungan.index')
            ->with('success', 'Hubungan berjaya ditambah');
    }

    /**
     * Update the specified Hubungan.
     */
    public function hubunganUpdate(Request $request, Hubungan $hubungan)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $hubungan->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
        ]);

        $hubungan->update($validated);

        return redirect()->route('master-data.hubungan.index')
            ->with('success', 'Hubungan berjaya dikemaskini');
    }

    /**
     * Remove the specified Hubungan.
     */
    public function hubunganDestroy(Hubungan $hubungan)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // if ($user->isAdmin() && $hubungan->bandar_id != $user->bandar_id) {
        //     abort(403, 'Unauthorized action.');
        // }

        $hubungan->delete();

        return redirect()->route('master-data.hubungan.index')
            ->with('success', 'Hubungan berjaya dipadam');
    }

    /**
     * Display a listing of Bangsa.
     */
    public function bangsaIndex(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = Bangsa::query();

        if ($request->has('search') && $request->search) {
            $query->where('nama', 'like', "%{$request->search}%");
        }

        $bangsa = $query->paginate(15);

        return Inertia::render('MasterData/Bangsa/Index', [
            'bangsa' => $bangsa,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created Bangsa.
     */
    public function bangsaStore(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:bangsa,nama',
        ]);

        Bangsa::create($validated);

        return redirect()->route('master-data.bangsa.index')
            ->with('success', 'Bangsa berjaya ditambah');
    }

    /**
     * Update the specified Bangsa.
     */
    public function bangsaUpdate(Request $request, Bangsa $bangsa)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:bangsa,nama,' . $bangsa->id,
        ]);

        $bangsa->update($validated);

        return redirect()->route('master-data.bangsa.index')
            ->with('success', 'Bangsa berjaya dikemaskini');
    }

    /**
     * Remove the specified Bangsa.
     */
    public function bangsaDestroy(Bangsa $bangsa)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $bangsa->delete();

        return redirect()->route('master-data.bangsa.index')
            ->with('success', 'Bangsa berjaya dipadam');
    }

    /**
     * Reorder master data items.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'model' => 'required|string',
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.sort_order' => 'required|integer',
        ]);

        $modelClass = 'App\\Models\\' . $request->model;

        if (!class_exists($modelClass)) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        foreach ($request->items as $item) {
            $modelClass::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    // =========================================================
    // LOKALITI
    // =========================================================

    public function lokalitiIndex(Request $request)
    {
        $query = Lokaliti::query();

        if ($request->filled('search')) {
            $query->where('nama', 'like', '%' . $request->search . '%');
        }

        $lokaliti = $query->orderBy('nama')->paginate(20)->withQueryString();

        return Inertia::render('MasterData/Lokaliti/Index', [
            'lokaliti' => $lokaliti,
            'filters' => $request->only(['search']),
        ]);
    }

    public function lokalitiStore(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:lokaliti,nama',
        ]);

        Lokaliti::create($validated);

        return back()->with('success', 'Lokaliti berjaya ditambah.');
    }

    public function lokalitiUpdate(Request $request, Lokaliti $lokaliti)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255|unique:lokaliti,nama,' . $lokaliti->id,
        ]);

        $lokaliti->update($validated);

        return back()->with('success', 'Lokaliti berjaya dikemaskini.');
    }

    public function lokalitiDestroy(Lokaliti $lokaliti)
    {
        $lokaliti->delete();

        return back()->with('success', 'Lokaliti berjaya dipadam.');
    }

    public function getAllLokaliti()
    {
        return Lokaliti::orderBy('nama')->get();
    }
}
