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

        // Restrict to Super Admin only
        if (!$user->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('CallCenter/Index');
    }
}
