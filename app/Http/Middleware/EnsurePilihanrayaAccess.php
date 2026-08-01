<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang luar bagi kumpulan laluan /pilihanraya utama (War Room, Analisa,
 * Borang 14, Simulasi, Jawatankuasa).
 *
 * MENGAPA kelas ini wujud dan bukannya melonggarkan EnsureAdmin: alias
 * `admin` juga menjaga /upload-culaan DAN /keanggotaan. Menambah
 * `pengarah_dun` ke dalam EnsureAdmin akan menyerahkan kedua-dua menu itu
 * kepadanya juga. Jadi kumpulan Pilihanraya mendapat gerbangnya sendiri —
 * mengikut duluan EnsurePacaAccess.
 *
 * Ini lapisan luar sahaja. Skop kerusi sebenar (Parlimen sendiri + DUN di
 * bawahnya) dikuatkuasakan dalam pengawal melalui App\Support\SeatScope,
 * mengikut konvensyen repositori "kebenaran hidup dalam pengawal".
 *
 * PACA TIDAK termasuk di sini: ia berada dalam kumpulan `paca` yang
 * berasingan dan kekal tertutup kepada `pengarah_dun`.
 */
class EnsurePilihanrayaAccess
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin() && ! $user->isPengarahDun())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akses ditolak. Hanya Admin dibenarkan.'], 403);
            }

            return redirect()->route('dashboard')->with('error', 'Akses ditolak. Hanya Admin dibenarkan.');
        }

        return $next($request);
    }
}
