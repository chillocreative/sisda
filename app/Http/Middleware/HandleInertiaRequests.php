<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $pendingApprovalsCount = 0;

        // Calculate pending approvals count for Super Admin and Admin
        if ($user && ($user->isSuperAdmin() || $user->isAdmin())) {
            $query = \App\Models\User::where('status', 'pending');
            
            // Admin can only see pending users in their Parlimen (Bandar)
            if ($user->isAdmin()) {
                $query->where('bandar_id', $user->bandar_id);
            }
            
            $pendingApprovalsCount = $query->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'pendingApprovalsCount' => $pendingApprovalsCount,
            ],
        ];
    }
}
