<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     *
     * Allows Super Admin and Admin roles.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akses ditolak. Hanya Admin dibenarkan.'], 403);
            }

            return redirect()->route('dashboard')->with('error', 'Akses ditolak. Hanya Admin dibenarkan.');
        }

        return $next($request);
    }
}
