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
        // getRealMethod() sengaja digunakan (bukan isMethod()) kerana
        // isMethod() menghormati penggantian `_method`, jadi POST dengan
        // `_method=GET` akan lulus pengawal ini dan merge() akan menulis ke
        // dalam BADAN POST, bukan sekadar apa yang pengguna lihat.
        if ($request->getRealMethod() !== 'GET' || ! $request->user()) {
            return $next($request);
        }

        $scope = FilterScopes::forRoute($request->route()?->getName());
        if (! $scope) {
            return $next($request);
        }

        $kunci = $scope['keys'];
        $sessionKey = FilterScopes::sessionKey($scope['scope']);

        // Isyarat reset yang JELAS. Jangan sekali-kali menyimpulkan "dibersihkan"
        // daripada nilai kosong: frontend menanggalkan nilai kosong sebelum
        // menghantar (cleanParams()), dan butang Set Semula menghantar permintaan
        // KOSONG sepenuhnya. Kedua-duanya tidak dapat dibezakan daripada navigasi
        // biasa, jadi menyimpulkan daripada ketiadaan akan membangkitkan semula
        // penapis yang baru sahaja dibuang oleh pengguna.
        if ($request->boolean('reset_filters')) {
            $request->session()->forget($sessionKey);

            return $next($request);
        }

        // MANA-MANA kunci hadir = pengguna bertindak dengan sengaja. Kunci
        // hadir-tetapi-kosong bermakna "dibersihkan", dan itu MESTI diingat
        // sebagai kosong — jika tidak, Set Semula akan berpatah balik ke
        // pilihan lama pada lawatan berikutnya.
        $adaKunci = collect($kunci)->contains(fn ($k) => $request->has($k));

        if ($adaKunci) {
            $request->session()->put($sessionKey, collect($kunci)
                ->mapWithKeys(fn ($k) => [$k => $this->skalarSahaja($request->input($k))])
                ->all());

            return $next($request);
        }

        $diingat = $request->session()->get($sessionKey, []);
        if ($diingat) {
            // array_intersect_key melindungi daripada entri sesi lama yang
            // membawa kunci yang sejak itu dibuang daripada senarai putih.
            // skalarSahaja() diulang di sini supaya invarian "hanya skalar
            // digabungkan" berlaku untuk SEBARANG kandungan sesi — bukan
            // sekadar apa yang versi semasa middleware ini simpan.
            $request->merge(array_map(
                fn ($v) => $this->skalarSahaja($v),
                array_intersect_key($diingat, array_flip($kunci))
            ));
        }

        return $next($request);
    }

    /**
     * Satu URL rosak (contoh: ?mpkk_id[]=1&mpkk_id[]=2) tidak boleh
     * meracau seluruh sesi. Jika nilai bukan skalar (array/objek) disimpan
     * verbatim, ia akan digabungkan semula ke dalam SETIAP permintaan kosong
     * berikutnya sepanjang sesi log masuk, dan pengawal yang mengharapkan
     * skalar (contoh: ->where('id', $mpkkId)) akan gagal tanpa sebarang
     * laluan UI untuk membersihkannya. Simpan null sebaliknya.
     */
    private function skalarSahaja(mixed $value): mixed
    {
        return is_scalar($value) || $value === null ? $value : null;
    }
}
