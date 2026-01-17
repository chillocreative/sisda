<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class CallCenterController extends Controller
{
    /**
     * Display the Call Center page.
     */
    public function index()
    {
        $user = auth()->user();

        // Restrict to Super Admin and Admin only
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('CallCenter/Index', [
            'locality' => [
                'bandar' => $user->bandar?->name ?? 'Seluruh Negara',
                'kadun' => $user->kadun?->name ?? 'Semua KADUN',
                'is_restricted' => !$user->isSuperAdmin()
            ]
        ]);
    }

    /**
     * Display the Call Scripts management page.
     */
    public function scripts()
    {
        $user = auth()->user();

        // Restrict to Super Admin and Admin only
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('CallCenter/Scripts/Index', [
            'locality' => [
                'bandar' => $user->bandar?->name ?? 'Seluruh Negara',
                'kadun' => $user->kadun?->name ?? 'Semua KADUN',
                'is_restricted' => !$user->isSuperAdmin()
            ]
        ]);
    }

    /**
     * Display the Call Center Agent interface.
     */
    public function agent()
    {
        $user = auth()->user();

        // Accessible by Super Admin, Admin, and User (agents)
        if (!in_array($user->role, ['super_admin', 'admin', 'user'])) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('CallCenter/Agent/Index', [
            'locality' => [
                'bandar' => $user->bandar?->name ?? 'Seluruh Negara',
                'kadun' => $user->kadun?->name ?? 'Semua KADUN',
                'is_restricted' => !$user->isSuperAdmin()
            ]
        ]);
    }

    /**
     * Display the Political Analytics dashboard.
     */
    public function analytics()
    {
        $user = auth()->user();

        // Restrict to Super Admin and Admin only
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('CallCenter/Analytics/Index', [
            'locality' => [
                'bandar' => $user->bandar?->name ?? 'Seluruh Negara',
                'kadun' => $user->kadun?->name ?? 'Semua KADUN',
                'is_restricted' => !$user->isSuperAdmin()
            ]
        ]);
    }

    /**
     * Display the AI-Driven Political Analytics dashboard.
     */
    public function aiAnalytics()
    {
        $user = auth()->user();

        // Restrict to Super Admin and Admin only
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('CallCenter/Analytics/AI', [
            'locality' => [
                'bandar' => $user->bandar?->name ?? 'Seluruh Negara',
                'kadun' => $user->kadun?->name ?? 'Semua KADUN',
                'is_restricted' => !$user->isSuperAdmin()
            ]
        ]);
    }

    /**
     * Display the Call History for the agent.
     */
    public function history()
    {
        $user = auth()->user();

        // Accessible by Super Admin, Admin, and User
        if (!in_array($user->role, ['super_admin', 'admin', 'user'])) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('CallCenter/Agent/History', [
            'locality' => [
                'bandar' => $user->bandar?->name ?? 'Seluruh Negara',
                'kadun' => $user->kadun?->name ?? 'Semua KADUN',
                'is_restricted' => !$user->isSuperAdmin()
            ]
        ]);
    }
}
