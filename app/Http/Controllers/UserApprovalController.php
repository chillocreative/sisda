<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserApprovalController extends Controller
{
    /**
     * Display a listing of pending users.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Only Super Admin and Admin can access
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $query = User::with(['negeri', 'bandar', 'kadun'])
            ->pending()
            ->orderBy('created_at', 'desc');

        // Admin can only see pending users in their Parlimen (Bandar)
        if ($user->isAdmin()) {
            $query->where('bandar_id', $user->bandar_id);
        }

        $pendingUsers = $query->paginate(15);

        return Inertia::render('UserApproval/Index', [
            'pendingUsers' => $pendingUsers,
        ]);
    }

    /**
     * Approve a user.
     */
    public function approve(Request $request, User $user)
    {
        $currentUser = auth()->user();

        // Authorization check
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admin can only approve users in their Parlimen (Bandar)
        if ($currentUser->isAdmin()) {
            if ($user->bandar_id != $currentUser->bandar_id) {
                abort(403, 'You can only approve users in your Parlimen.');
            }
        }

        // Update user status
        $user->update([
            'status' => 'approved',
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Pengguna berjaya diluluskan.');
    }

    /**
     * Reject a user.
     */
    public function reject(Request $request, User $user)
    {
        $currentUser = auth()->user();

        // Authorization check
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admin can only reject users in their Parlimen (Bandar)
        if ($currentUser->isAdmin()) {
            if ($user->bandar_id != $currentUser->bandar_id) {
                abort(403, 'You can only reject users in your Parlimen.');
            }
        }

        // Update user status
        $user->update([
            'status' => 'rejected',
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Pengguna telah ditolak.');
    }
}
