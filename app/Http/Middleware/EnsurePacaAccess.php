<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang luar bagi laluan /pilihanraya/paca/* sahaja.
 *
 * Selebihnya kumpulan Pilihanraya kekal di belakang EnsureAdmin. Middleware
 * ini melebarkan capaian kepada `ketua_paca_dun` TANPA membuka War Room,
 * Borang 14, Analisa atau Scoreboard.
 *
 * Ini lapisan luar sahaja — skop DUN sebenar dikuatkuasakan dalam
 * PacaController::assertPeranan()/assertBolehAkses(), mengikut konvensyen
 * repositori "kebenaran hidup dalam pengawal".
 */
class EnsurePacaAccess
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin() && ! $user->isKetuaPacaDun())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }

            return redirect()->route('dashboard')->with('error', 'Akses ditolak.');
        }

        return $next($request);
    }
}
