<?php
namespace Tests\Unit;

use App\Support\FilterScopes;
use Tests\TestCase;

class FilterScopesTest extends TestCase
{
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

    public function test_wildcard_routes_share_one_scope(): void
    {
        // Tab XHR endpoints MESTI memetakan ke skop yang sama seperti halaman
        // induknya — itulah yang menjadikan pengambilan data halaman merangkap
        // penyimpanan.
        $a = FilterScopes::forRoute('pilihanraya.war-room');
        $b = FilterScopes::forRoute('pilihanraya.war-room.battlefield');

        $this->assertSame($a['scope'], $b['scope']);
    }
}
