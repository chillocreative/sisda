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
