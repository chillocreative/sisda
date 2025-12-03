<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Check if user is approved (Super Admin bypasses this check)
        if (!$user->isSuperAdmin() && $user->status !== 'approved') {
            Auth::logout();
            
            if ($user->status === 'pending') {
                return redirect()->route('pending-approval')
                    ->with('info', 'Akaun anda masih menunggu kelulusan daripada pentadbir.');
            }
            
            if ($user->status === 'rejected') {
                return redirect()->route('login')
                    ->withErrors(['telephone' => 'Akaun anda telah ditolak. Sila hubungi pentadbir untuk maklumat lanjut.']);
            }
        }

        // Update last login timestamp
        $user->update(['last_login' => now()]);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
