<?php

namespace Tests\Unit;

use App\Support\FilterScopes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilterScopesTest extends TestCase
{
    public function test_every_configured_route_name_exists_in_the_router(): void
    {
        // Ujian yang membandingkan config dengan config sahaja tidak dapat
        // mengesan nama laluan rekaan (war_room pernah menyenaraikan
        // `pilihanraya.war-room.*` yang tidak sepadan dengan apa-apa). Semak
        // terus terhadap Route::has() supaya nama fiksyen menjatuhkan ujian
        // ini, bukan sekadar berlalu senyap.
        foreach (config('sticky_filters', []) as $scope => $def) {
            foreach ($def['routes'] ?? [] as $pattern) {
                if (Str::contains($pattern, '*')) {
                    continue;
                }

                $this->assertTrue(
                    Route::has($pattern),
                    "Skop '{$scope}' menyenaraikan laluan '{$pattern}' yang tidak wujud dalam router."
                );
            }
        }
    }

    public function test_resolves_a_named_route_to_its_scope_and_keys(): void
    {
        $out = FilterScopes::forRoute('dashboard');

        $this->assertSame('dashboard', $out['scope']);
        $this->assertContains('negeri_id', $out['keys']);
        $this->assertContains('tarikh_hingga', $out['keys']);
    }

    public function test_unknown_route_has_no_scope(): void
    {
        $this->assertNull(FilterScopes::forRoute('profile.edit'));
        $this->assertNull(FilterScopes::forRoute(null));
    }

    public function test_war_room_scope_is_live_for_its_page_and_xhr_routes(): void
    {
        // war_room kini DIAKTIFKAN (lihat config/sticky_filters.php) selepas
        // WarRoom.jsx disemai daripada rememberedFilters dan menghantar
        // reset_filters apabila FilterBar dikosongkan. Buktikan laluan
        // halaman DAN keenam-enam laluan XHR tab menyelesaikan kepada skop
        // yang SAMA menggunakan konfigurasi SEBENAR — bukan sekadar wujud
        // dalam router (ujian di atas), tetapi benar-benar dipadankan oleh
        // resolver.
        $routes = [
            'pilihanraya.war-room',
            'pilihanraya.api.overview',
            'pilihanraya.api.composition',
            'pilihanraya.api.sentiment',
            'pilihanraya.api.seat-scores',
            'pilihanraya.api.battlefield',
            'pilihanraya.api.alerts',
        ];

        foreach ($routes as $route) {
            $out = FilterScopes::forRoute($route);
            $this->assertNotNull($out, "Laluan '{$route}' mesti menyelesaikan kepada satu skop.");
            $this->assertSame('war_room', $out['scope'], "Laluan '{$route}' mesti berkongsi skop 'war_room'.");
        }
    }

    /**
     * Kedua-dua ujian nama laluan di atas MELANGKAU corak wildcard
     * (`str_contains($pattern, '*')`) — itulah lubang buta yang membiarkan
     * `pilihanraya.borang-14.*` menangkap laluan .pdf dan .upload.sejarah
     * yang tidak sepatutnya berkongsi skop borang14 (lihat CRITICAL C1).
     * Ujian itu tidak dapat mengesan masalah itu kerana ia tidak pernah
     * benar-benar MENGEMBANGKAN wildcard terhadap laluan sebenar router.
     *
     * Ujian ini mengembangkan SETIAP laluan GET berdaftar (bukan sekadar
     * corak config) melalui FilterScopes::forRoute() sebenar dan
     * membandingkan peta lengkap nama-laluan => skop dengan senarai yang
     * dipatri secara eksplisit di bawah. Jika wildcard baharu (atau
     * wildcard sedia ada) menangkap satu laluan tambahan, peta yang
     * terhasil tidak lagi sepadan dan ujian ini GAGAL, menamakan laluan
     * yang baru ditangkap dalam mesej kegagalan.
     *
     * Tulen (tiada sesi/permintaan/pangkalan data) — Route::getRoutes()
     * dan config sahaja, jadi ia berjalan di SQLite CI.
     */
    public function test_the_full_route_to_scope_map_is_pinned(): void
    {
        $peta = [];

        foreach (Route::getRoutes() as $route) {
            $nama = $route->getName();
            if (! $nama || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $skop = FilterScopes::forRoute($nama);
            if ($skop) {
                $peta[$nama] = $skop['scope'];
            }
        }

        ksort($peta);

        $dijangka = [
            'dashboard' => 'dashboard',
            'keanggotaan.analisa' => 'keanggotaan_analisa',
            'keanggotaan.senarai' => 'keanggotaan_senarai',
            'pilihanraya.analisa' => 'analisa',
            'pilihanraya.analisa.keanggotaan-card' => 'analisa',
            'pilihanraya.api.alerts' => 'war_room',
            'pilihanraya.api.battlefield' => 'war_room',
            'pilihanraya.api.composition' => 'war_room',
            'pilihanraya.api.overview' => 'war_room',
            'pilihanraya.api.seat-scores' => 'war_room',
            'pilihanraya.api.sentiment' => 'war_room',
            'pilihanraya.borang-14' => 'borang14',
            'pilihanraya.borang-14.data' => 'borang14',
            'pilihanraya.borang-14.senarai' => 'borang14',
            'pilihanraya.jawatankuasa.index' => 'jawatankuasa',
            'pilihanraya.kaum-dm' => 'kaum_dm',
            'pilihanraya.minima' => 'minima',
            'pilihanraya.paca' => 'paca',
            'pilihanraya.paca.data' => 'paca',
            'pilihanraya.scoreboard' => 'scoreboard',
            'pilihanraya.scoreboard.data' => 'scoreboard',
            'pilihanraya.war-room' => 'war_room',
            'reports.hasil-culaan.index' => 'hasil_culaan',
            'user-log.index' => 'user_log',
            // Diaktifkan 1 Ogos 2026, selepas butang Set Semula masing-masing
            // dikemas kini menghantar reset_filters=1.
            'master-data.bandar.index' => 'masterdata_bandar',
            'master-data.parlimen.index' => 'masterdata_parlimen',
            'reports.data-pengundi.index' => 'data_pengundi',
            'users.index' => 'users',
        ];
        ksort($dijangka);

        $this->assertSame($dijangka, $peta);
    }

    public function test_page_and_xhr_routes_share_one_scope_when_scope_is_active(): void
    {
        // Tab XHR endpoints MESTI memetakan ke skop yang sama seperti halaman
        // induknya — itulah yang menjadikan pengambilan data halaman merangkap
        // penyimpanan.
        //
        // Laluan di sini SENGAJA laluan yang tiada dalam mana-mana skop hidup.
        // Versi terdahulu ujian ini menggunakan pilihanraya.war-room, yang kini
        // dimiliki oleh skop war_room sebenar — dan kerana forRoute() mengulang
        // config mengikut urutan sisipan, ia memulangkan war_room dan tidak
        // pernah merujuk skop sementara ini langsung. Ujian itu lulus atas
        // sebab yang SALAH: memadam skop sementara pun ia tetap hijau.
        config()->set('sticky_filters.ujian_kongsi_skop', [
            'routes' => ['profile.edit', 'profile.update'],
            'keys' => ['negeri_id'],
        ]);

        $a = FilterScopes::forRoute('profile.edit');
        $b = FilterScopes::forRoute('profile.update');

        $this->assertNotNull($a, 'Laluan pertama mesti diselesaikan kepada skop sementara.');
        $this->assertNotNull($b, 'Laluan kedua mesti berkongsi skop yang sama.');
        $this->assertSame($a['scope'], $b['scope']);
    }
}
