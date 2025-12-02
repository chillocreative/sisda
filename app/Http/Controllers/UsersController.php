<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use App\Models\Negeri;
use App\Models\Bandar;
use App\Models\Kadun;
use Illuminate\Validation\Rules;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = User::with(['negeri', 'bandar', 'kadun'])->orderBy('created_at', 'desc');

        // Admin Restriction: Only view users in their Parlimen (Bandar) and hide Super Admins
        if ($user->isAdmin()) {
            $query->where('bandar_id', $user->bandar_id)
                  ->where('role', '!=', 'super_admin');
        }

        // Filtering
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('negeri_id')) {
            $query->where('negeri_id', $request->negeri_id);
        }

        if ($request->filled('bandar_id')) {
            $query->where('bandar_id', $request->bandar_id);
        }

        if ($request->filled('kadun_id')) {
            $query->where('kadun_id', $request->kadun_id);
        }

        $users = $query->get();
        
        $statsQuery = User::query();
        if ($user->isAdmin()) {
            $statsQuery->where('bandar_id', $user->bandar_id);
        }

        $stats = [
            'super_admin' => $user->isAdmin() ? 0 : User::where('role', 'super_admin')->count(),
            'admin' => (clone $statsQuery)->where('role', 'admin')->count(),
            'user' => (clone $statsQuery)->where('role', 'user')->count(),
        ];

        return Inertia::render('Users/Index', [
            'users' => $users,
            'stats' => $stats,
            'negeriList' => Negeri::orderBy('nama')->get(),
            'bandarList' => Bandar::orderBy('nama')->get(),
            'kadunList' => Kadun::orderBy('nama')->get(),
            'filters' => $request->only(['search', 'role', 'status', 'negeri_id', 'bandar_id', 'kadun_id']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $currentUser = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'telephone' => 'required|string|max:255|unique:'.User::class,
            'email' => 'nullable|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:super_admin,admin,user',
            'negeri_id' => 'required|exists:negeri,id',
            'bandar_id' => 'required|exists:bandar,id',
            'kadun_id' => 'required|exists:kadun,id',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        // Admin Restriction: Cannot create Super Admin or user outside their Parlimen
        if ($currentUser->isAdmin()) {
            if ($request->role === 'super_admin') {
                abort(403, 'You cannot create a Super Admin.');
            }
            if ($request->bandar_id != $currentUser->bandar_id) {
                abort(403, 'You can only create users in your Parlimen.');
            }
        }

        $user = User::create([
            'name' => $request->name,
            'telephone' => $request->telephone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'negeri_id' => $request->negeri_id,
            'bandar_id' => $request->bandar_id,
            'kadun_id' => $request->kadun_id,
            'status' => $request->status,
            'approved_by' => $request->status === 'approved' ? auth()->id() : null,
            'approved_at' => $request->status === 'approved' ? now() : null,
        ]);

        return redirect()->route('users.index')->with('success', 'Pengguna berjaya dicipta');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();

        // Admin Restriction: Cannot edit Super Admin or user outside their Parlimen
        if ($currentUser->isAdmin()) {
            if ($user->role === 'super_admin') {
                abort(403, 'You cannot edit a Super Admin.');
            }
            if ($user->bandar_id != $currentUser->bandar_id) {
                abort(403, 'You can only edit users in your Parlimen.');
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'telephone' => 'required|string|max:255|unique:users,telephone,' . $user->id,
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:super_admin,admin,user',
            'negeri_id' => 'required|exists:negeri,id',
            'bandar_id' => 'required|exists:bandar,id',
            'kadun_id' => 'required|exists:kadun,id',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        // Admin Restriction: Cannot promote to Super Admin or move user outside Parlimen
        if ($currentUser->isAdmin()) {
            if ($request->role === 'super_admin') {
                abort(403, 'You cannot promote a user to Super Admin.');
            }
            if ($request->bandar_id != $currentUser->bandar_id) {
                abort(403, 'You cannot move a user outside your Parlimen.');
            }
        }

        // If status changing to approved, set approver info
        if ($request->status === 'approved' && $user->status !== 'approved') {
            $validated['approved_by'] = auth()->id();
            $validated['approved_at'] = now();
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'Pengguna berjaya dikemaskini');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $currentUser = auth()->user();

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Anda tidak boleh memadam akaun sendiri');
        }

        // Admin Restriction: Cannot delete Super Admin or user outside their Parlimen
        if ($currentUser->isAdmin()) {
            if ($user->role === 'super_admin') {
                abort(403, 'You cannot delete a Super Admin.');
            }
            if ($user->bandar_id != $currentUser->bandar_id) {
                abort(403, 'You can only delete users in your Parlimen.');
            }
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Pengguna berjaya dipadam');
    }

    /**
     * Remove multiple users from storage.
     */
    public function bulkDelete(Request $request)
    {
        $currentUser = auth()->user();

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
        ]);

        // Prevent deleting yourself
        $ids = array_filter($validated['ids'], function($id) {
            return $id != auth()->id();
        });

        // Admin Restriction: Filter out Super Admins and users outside Parlimen
        if ($currentUser->isAdmin()) {
            $ids = User::whereIn('id', $ids)
                ->where('role', '!=', 'super_admin')
                ->where('bandar_id', $currentUser->bandar_id)
                ->pluck('id')
                ->toArray();
        }

        User::whereIn('id', $ids)->delete();

        return redirect()->route('users.index')->with('success', 'Pengguna terpilih berjaya dipadam');
    }
}
