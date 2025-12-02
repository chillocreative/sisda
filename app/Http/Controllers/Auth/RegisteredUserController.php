<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Negeri;
use App\Models\Bandar;
use App\Models\Kadun;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        $negeriList = Negeri::orderBy('nama')->get();
        $bandarList = Bandar::orderBy('nama')->get();
        $kadunList = Kadun::orderBy('nama')->get();

        return Inertia::render('Auth/Register', [
            'negeriList' => $negeriList,
            'bandarList' => $bandarList,
            'kadunList' => $kadunList,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'telephone' => 'required|string|max:255|unique:'.User::class,
            'email' => 'nullable|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'negeri_id' => 'required|exists:negeri,id',
            'bandar_id' => 'required|exists:bandar,id',
            'kadun_id' => 'required|exists:kadun,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'telephone' => $request->telephone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user', // Default role for all new registrations
            'negeri_id' => $request->negeri_id,
            'bandar_id' => $request->bandar_id,
            'kadun_id' => $request->kadun_id,
            'status' => 'pending', // Default status
        ]);

        event(new Registered($user));

        // Do NOT auto-login, redirect to pending approval page
        return redirect()->route('pending-approval')->with('success', 'Pendaftaran berjaya! Sila tunggu kelulusan daripada pentadbir.');
    }
}
