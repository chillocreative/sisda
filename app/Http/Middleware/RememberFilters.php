<?php

namespace App\Http\Middleware;

use App\Support\FilterScopes;
use Closure;
use Illuminate\Http\Request;

/**
 * Mengingat penapis skrin sepanjang sesi log masuk.
 *
 * INVARIAN: dropdown yang dipulihkan tetapi keputusan yang tidak ditapis
 * ialah UI yang MENIPU. Sebab itu nilai yang diingat digabungkan ke dalam
 * $request itu sendiri — pengawal membina pertanyaan DAN memulangkan penapis
 * ke dropdown daripada sumber yang SAMA, jadi keduanya tidak boleh hanyut.
 *
 * Disimpan dalam sesi Laravel: AuthenticatedSessionController memanggil
 * session()->invalidate() semasa log keluar, jadi "reset selepas log keluar"
 * ialah sifat tempat simpanan, bukan kod yang boleh terlupa dijalankan.
 */
class RememberFilters
{
    public function handle(Request $request, Closure $next)
    {
        // GET sahaja: menulis semula badan POST/PUT/DELETE akan menukar apa
        // yang pengguna hantar, bukan sekadar apa yang dia lihat.
        if (! $request->isMethod('GET') || ! $request->user()) {
            return $next($request);
        }

        $scope = FilterScopes::forRoute($request->route()?->getName());
        if (! $scope) {
            return $next($request);
        }

        $kunci = $scope['keys'];
        $sessionKey = FilterScopes::sessionKey($scope['scope']);

        // MANA-MANA kunci hadir = pengguna bertindak dengan sengaja. Kunci
        // hadir-tetapi-kosong bermakna "dibersihkan", dan itu MESTI diingat
        // sebagai kosong — jika tidak, Set Semula akan berpatah balik ke
        // pilihan lama pada lawatan berikutnya.
        $adaKunci = collect($kunci)->contains(fn ($k) => $request->has($k));

        if ($adaKunci) {
            $request->session()->put($sessionKey, collect($kunci)
                ->mapWithKeys(fn ($k) => [$k => $request->input($k)])
                ->all());

            return $next($request);
        }

        $diingat = $request->session()->get($sessionKey, []);
        if ($diingat) {
            // array_intersect_key melindungi daripada entri sesi lama yang
            // membawa kunci yang sejak itu dibuang daripada senarai putih.
            $request->merge(array_intersect_key($diingat, array_flip($kunci)));
        }

        return $next($request);
    }
}
