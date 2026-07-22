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

    public function test_scope_with_empty_routes_is_unreachable(): void
    {
        // war_room kini SENGAJA `'routes' => []` (lihat config/sticky_filters.php)
        // sehingga front end War Room menghantar reset_filters. Buktikan resolver
        // tidak sekali-kali sepadan dengan skop yang tiada laluan — jika tidak,
        // skop "kosong" ini akan lengai secara tidak sengaja apabila corak Str::is
        // longgar (cth. '*') ditambah semula pada masa depan.
        $this->assertNull(FilterScopes::forRoute('pilihanraya.war-room'));
        $this->assertNull(FilterScopes::forRoute('pilihanraya.api.overview'));
        $this->assertNull(FilterScopes::forRoute('pilihanraya.api.battlefield'));
    }

    public function test_page_and_xhr_routes_share_one_scope_when_scope_is_active(): void
    {
        // Tab XHR endpoints MESTI memetakan ke skop yang sama seperti halaman
        // induknya — itulah yang menjadikan pengambilan data halaman merangkap
        // penyimpanan. war_room sengaja kosong buat masa ini (lihat ujian di
        // atas), jadi tingkah laku ini dibuktikan di sini menggunakan skop
        // sementara yang menggunakan nama laluan sebenar War Room, supaya ujian
        // ini tidak bergantung pada bila skop war_room diaktifkan semula.
        config()->set('sticky_filters.ujian_war_room', [
            'routes' => ['pilihanraya.war-room', 'pilihanraya.api.overview'],
            'keys' => ['negeri_id'],
        ]);

        $a = FilterScopes::forRoute('pilihanraya.war-room');
        $b = FilterScopes::forRoute('pilihanraya.api.overview');

        $this->assertNotNull($a, 'Laluan halaman war-room mesti diselesaikan kepada satu skop.');
        $this->assertNotNull($b, 'Laluan XHR war-room mesti berkongsi skop yang sama.');
        $this->assertSame($a['scope'], $b['scope']);
    }
}
