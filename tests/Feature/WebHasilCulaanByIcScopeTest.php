<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Covers the PII-disclosure bug in ReportsController::hasilCulaanByIc
 * (GET /api/hasil-culaan/by-ic): the source_id, hasil_culaan_id, and the
 * final HasilCulaan::where('no_ic', ...) lookup were all unscoped, so any
 * authenticated 'user'-role account could bulk-scrape unmasked voter PII
 * (no_ic, no_tel, alamat, ...) outside their own Kadun by walking
 * sequential integer ids, or by supplying a known IC directly.
 *
 * VoterScopeService::apply() now gates all three lookups.
 *
 * Tests 1 and 2 deliberately give the DataPengundi row (source_id target)
 * / the "other" HasilCulaan row (hasil_culaan_id target) a foreign kadun
 * while the leaking HasilCulaan record itself sits in the attacker's own
 * kadun. This isolates each lookup's scoping from the final query's
 * scoping (which alone would also block a same-kadun victim record) — if
 * only the final query were scoped and the source_id/hasil_culaan_id
 * lookups were left open, the derived IC would still let the attacker
 * pull an in-kadun-but-otherwise-unrelated record whose ic they had no
 * legitimate way to learn.
 */
class WebHasilCulaanByIcScopeTest extends TestCase
{
    use RefreshDatabase;

    private Negeri $negeri;
    private Bandar $ownBandar;
    private Kadun $ownKadun;
    private Bandar $foreignBandar;
    private Kadun $foreignKadun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->negeri = Negeri::create(['nama' => 'JOHOR']);
        $this->ownBandar = Bandar::create(['nama' => 'SEGAMAT', 'negeri_id' => $this->negeri->id]);
        $this->ownKadun = Kadun::create(['nama' => 'BULOH KASAP', 'bandar_id' => $this->ownBandar->id]);
        $this->foreignBandar = Bandar::create(['nama' => 'MUAR', 'negeri_id' => $this->negeri->id]);
        $this->foreignKadun = Kadun::create(['nama' => 'BENTAYAN', 'bandar_id' => $this->foreignBandar->id]);
    }

    private function makeUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->negeri->id,
            'bandar_id' => $this->ownBandar->id,
            'kadun_id' => $this->ownKadun->id,
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // 1. source_id path
    // ------------------------------------------------------------------

    public function test_source_id_does_not_leak_pii_for_voter_outside_kadun(): void
    {
        $attacker = $this->makeUser('user');
        $stranger = $this->makeUser('user', [
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'bandar_id' => $this->foreignBandar->id,
            'kadun_id' => $this->foreignKadun->id,
        ]);
        $adminSubmitter = $this->makeUser('admin', [
            'telephone' => '01'.fake()->unique()->numerify('########'),
        ]);

        $ic = '900101011234';

        // The DataPengundi (DPT) row is out of scope for the attacker.
        $source = DataPengundi::factory()->create([
            'no_ic' => $ic,
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'submitted_by' => $stranger->id,
        ]);

        // The leaking HasilCulaan record sits in the attacker's own kadun
        // (so it alone would pass the final query's scope) but is
        // submitted by an admin, so VoterDataMasker::isLocked() is false
        // and it would come back fully unmasked once the attacker learns
        // the ic — precisely the leak this endpoint must prevent.
        HasilCulaan::factory()->create([
            'no_ic' => $ic,
            'kadun' => $this->ownKadun->nama,
            'bandar' => $this->ownBandar->nama,
            'no_tel' => '0177778888',
            'alamat' => 'Alamat Rahsia Mangsa, Muar',
            'submitted_by' => $adminSubmitter->id,
        ]);

        $response = $this->actingAs($attacker)->getJson('/api/hasil-culaan/by-ic?source_id='.$source->id);

        $response->assertOk();
        $body = $response->getContent();
        $response->assertDontSee($ic);
        $response->assertDontSee('0177778888');
        $response->assertDontSee('Alamat Rahsia Mangsa, Muar');
        $this->assertStringNotContainsString($ic, $body);
        $this->assertStringNotContainsString('0177778888', $body);
        $this->assertStringNotContainsString('Alamat Rahsia Mangsa, Muar', $body);
    }

    // ------------------------------------------------------------------
    // 2. hasil_culaan_id path
    // ------------------------------------------------------------------

    public function test_hasil_culaan_id_does_not_leak_pii_for_record_outside_kadun(): void
    {
        $attacker = $this->makeUser('user');
        $stranger = $this->makeUser('user', [
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'bandar_id' => $this->foreignBandar->id,
            'kadun_id' => $this->foreignKadun->id,
        ]);
        $adminSubmitter = $this->makeUser('admin', [
            'telephone' => '01'.fake()->unique()->numerify('########'),
        ]);

        $ic = '910303031234';

        // The record the attacker probes via hasil_culaan_id: out of scope.
        $probeTarget = HasilCulaan::factory()->create([
            'no_ic' => $ic,
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'submitted_by' => $stranger->id,
        ]);

        // A second record for the same ic, sitting in the attacker's own
        // kadun (so it alone would pass the final query's scope) but
        // submitted by an admin (unlocked) — the record that would leak.
        HasilCulaan::factory()->create([
            'no_ic' => $ic,
            'kadun' => $this->ownKadun->nama,
            'bandar' => $this->ownBandar->nama,
            'no_tel' => '0199990000',
            'alamat' => 'Alamat Rahsia Mangsa Dua, Muar',
            'submitted_by' => $adminSubmitter->id,
        ]);

        $response = $this->actingAs($attacker)->getJson('/api/hasil-culaan/by-ic?hasil_culaan_id='.$probeTarget->id);

        $response->assertOk();
        $body = $response->getContent();
        $response->assertDontSee($ic);
        $response->assertDontSee('0199990000');
        $response->assertDontSee('Alamat Rahsia Mangsa Dua, Muar');
        $this->assertStringNotContainsString($ic, $body);
        $this->assertStringNotContainsString('0199990000', $body);
        $this->assertStringNotContainsString('Alamat Rahsia Mangsa Dua, Muar', $body);
    }

    // ------------------------------------------------------------------
    // 3. direct ic= path
    // ------------------------------------------------------------------

    public function test_direct_ic_does_not_leak_pii_for_out_of_scope_ic(): void
    {
        $attacker = $this->makeUser('user');
        $stranger = $this->makeUser('user', [
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'bandar_id' => $this->foreignBandar->id,
            'kadun_id' => $this->foreignKadun->id,
        ]);
        $adminSubmitter = $this->makeUser('admin', [
            'telephone' => '01'.fake()->unique()->numerify('########'),
        ]);

        $ic = '660505051234';

        HasilCulaan::factory()->create([
            'no_ic' => $ic,
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'no_tel' => '0139991111',
            'alamat' => 'Alamat Rahsia Mangsa Tiga, Muar',
            'submitted_by' => $adminSubmitter->id,
        ]);

        $response = $this->actingAs($attacker)->getJson('/api/hasil-culaan/by-ic?ic='.$ic);

        $response->assertOk();
        $body = $response->getContent();
        $response->assertDontSee($ic);
        $response->assertDontSee('0139991111');
        $response->assertDontSee('Alamat Rahsia Mangsa Tiga, Muar');
        $this->assertStringNotContainsString($ic, $body);
        $this->assertStringNotContainsString('0139991111', $body);
        $this->assertStringNotContainsString('Alamat Rahsia Mangsa Tiga, Muar', $body);
        $this->assertSame('[]', $body);

        // Unrelated stranger id kept in the query to avoid "unused variable"
        // false positives from static analysis while documenting that the
        // stranger user itself plays no direct role in this ic-only attack.
        $this->assertNotSame($attacker->id, $stranger->id);
    }

    // ------------------------------------------------------------------
    // 4. Feature preserved: in-scope source_id still works.
    // ------------------------------------------------------------------

    public function test_source_id_still_returns_record_for_voter_in_own_kadun(): void
    {
        $user = $this->makeUser('user');
        // Submitted by an admin so VoterDataMasker::isLocked() is false and
        // the in-scope record comes back unmasked — isolating this test to
        // the scoping behaviour rather than the separate masking rule.
        $adminSubmitter = $this->makeUser('admin', [
            'telephone' => '01'.fake()->unique()->numerify('########'),
        ]);
        $ic = '880202021234';

        $source = DataPengundi::factory()->create([
            'no_ic' => $ic,
            'kadun' => $this->ownKadun->nama,
            'bandar' => $this->ownBandar->nama,
            'submitted_by' => $adminSubmitter->id,
        ]);

        HasilCulaan::factory()->create([
            'no_ic' => $ic,
            'kadun' => $this->ownKadun->nama,
            'bandar' => $this->ownBandar->nama,
            'no_tel' => '0123334444',
            'alamat' => 'Alamat Sebenar, Segamat',
            'submitted_by' => $adminSubmitter->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/hasil-culaan/by-ic?source_id='.$source->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame($ic, $data[0]['no_ic']);
        $this->assertSame('0123334444', $data[0]['no_tel']);
        $this->assertSame('Alamat Sebenar, Segamat', $data[0]['alamat']);
    }

    // ------------------------------------------------------------------
    // 5. No existence oracle: out-of-scope vs non-existent source_id.
    // ------------------------------------------------------------------

    public function test_out_of_scope_and_nonexistent_source_id_return_identical_response(): void
    {
        $attacker = $this->makeUser('user');
        $stranger = $this->makeUser('user', [
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'bandar_id' => $this->foreignBandar->id,
            'kadun_id' => $this->foreignKadun->id,
        ]);

        $outOfScope = DataPengundi::factory()->create([
            'no_ic' => '600101011234',
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'submitted_by' => $stranger->id,
        ]);

        $nonExistentId = $outOfScope->id + 999999;
        $this->assertDatabaseMissing('data_pengundi', ['id' => $nonExistentId]);

        $outOfScopeResponse = $this->actingAs($attacker)->getJson('/api/hasil-culaan/by-ic?source_id='.$outOfScope->id);
        $nonExistentResponse = $this->actingAs($attacker)->getJson('/api/hasil-culaan/by-ic?source_id='.$nonExistentId);

        $outOfScopeResponse->assertOk();
        $nonExistentResponse->assertOk();
        $this->assertSame($outOfScopeResponse->getStatusCode(), $nonExistentResponse->getStatusCode());
        $this->assertSame($outOfScopeResponse->getContent(), $nonExistentResponse->getContent());
        $this->assertSame('[]', $outOfScopeResponse->getContent());
    }

    // ------------------------------------------------------------------
    // 6. Throttle is attached to the route.
    // ------------------------------------------------------------------

    public function test_route_has_throttle_middleware(): void
    {
        $route = Route::getRoutes()->getByName('api.hasil-culaan.by-ic');

        $this->assertNotNull($route, 'Route api.hasil-culaan.by-ic must exist.');
        $middleware = $route->gatherMiddleware();

        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:')),
            'Expected a throttle: middleware on api.hasil-culaan.by-ic, got: '.implode(', ', $middleware)
        );
    }
}
