<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the IDOR in ReportsController's masked-create flow:
 * `locked_source_id` was resolved with an unscoped DataPengundi::find(),
 * so any authenticated user could point it at a stranger's voter record
 * (in any Parlimen) and have the server copy that stranger's real
 * no_ic/no_tel/alamat into a new record under the attacker's own Parlimen.
 * VoterScopeService::apply() now gates the lookup.
 *
 * Also covers a second, unrelated finding in the same method family:
 * hasilCulaanStoreDeceased() marked *any* IC deceased across the whole
 * database because its existing-record lookups were unscoped and its only
 * guard (a Parlimen check) was skippable by omitting `parlimen` from the
 * request entirely.
 */
class WebCulaanLockedSourceScopeTest extends TestCase
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

    private function makeAttacker(): User
    {
        return User::factory()->create([
            'role' => 'user',
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->negeri->id,
            'bandar_id' => $this->ownBandar->id,
            'kadun_id' => $this->ownKadun->id,
        ]);
    }

    private function makeSubmitter(): User
    {
        // A distinct user id so `submitted_by` never accidentally matches
        // the attacker and widens VoterScopeService's "own submissions" leg.
        return User::factory()->create([
            'role' => 'user',
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
        ]);
    }

    // ------------------------------------------------------------------
    // hasilCulaanStore: locked_source_id IDOR
    // ------------------------------------------------------------------

    public function test_store_blocks_masked_create_from_out_of_scope_source(): void
    {
        $attacker = $this->makeAttacker();
        $victim = DataPengundi::factory()->create([
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'no_ic' => '900101011234',
            'no_tel' => '0177778888',
            'alamat' => 'Alamat Rahsia Mangsa, Muar',
            'submitted_by' => $this->makeSubmitter()->id,
        ]);

        $response = $this->actingAs($attacker)->post(route('reports.hasil-culaan.store'), [
            'locked_source_id' => $victim->id,
            'nama' => 'Percubaan Serangan',
            'no_ic' => '****',
            'umur' => '****',
            'no_tel' => '****',
            'bangsa' => '****',
            'alamat' => '****',
            'poskod' => '****',
            'negeri' => '****',
            'bandar' => '****',
            'parlimen' => $this->ownBandar->nama,
            'kadun' => $this->ownKadun->nama,
        ]);

        // The mask survives because the out-of-scope lookup found nothing,
        // so validation rejects '****' against digits:12 / integer rules.
        $response->assertSessionHasErrors(['no_ic', 'umur']);

        // Nothing new was written anywhere: the only DataPengundi row left
        // is the original victim record, and no HasilCulaan row exists.
        $this->assertSame(1, DataPengundi::count());
        $this->assertSame(0, HasilCulaan::count());
        $this->assertDatabaseHas('data_pengundi', ['id' => $victim->id, 'no_ic' => '900101011234']);
    }

    public function test_store_still_swaps_real_values_from_in_scope_source(): void
    {
        $user = $this->makeAttacker();
        $source = DataPengundi::factory()->create([
            'kadun' => $this->ownKadun->nama,
            'bandar' => $this->ownBandar->nama,
            'no_ic' => '880202021234',
            'umur' => 45,
            'no_tel' => '0123334444',
            'bangsa' => 'Cina',
            'alamat' => 'Alamat Sebenar, Segamat',
            'poskod' => '85000',
            'negeri' => 'JOHOR',
            'submitted_by' => $this->makeSubmitter()->id,
        ]);

        $response = $this->actingAs($user)->post(route('reports.hasil-culaan.store'), [
            'locked_source_id' => $source->id,
            'nama' => 'Rekod Baru Sah',
            'no_ic' => '****',
            'umur' => '****',
            'no_tel' => '****',
            'bangsa' => '****',
            'alamat' => '****',
            'poskod' => '****',
            'negeri' => '****',
            'bandar' => '****',
            'parlimen' => $this->ownBandar->nama,
            'kadun' => $this->ownKadun->nama,
        ]);

        $response->assertSessionHasNoErrors();

        $new = DataPengundi::where('submitted_by', $user->id)->firstOrFail();
        $this->assertSame('880202021234', $new->no_ic);
        $this->assertSame(45, $new->umur);
        $this->assertSame('0123334444', $new->no_tel);
        $this->assertSame('Cina', $new->bangsa);
        $this->assertSame('Alamat Sebenar, Segamat', $new->alamat);
        $this->assertSame('85000', $new->poskod);
        $this->assertSame('JOHOR', $new->negeri);
    }

    // ------------------------------------------------------------------
    // hasilCulaanStoreDeceased: same locked_source_id IDOR
    // ------------------------------------------------------------------

    public function test_store_deceased_blocks_masked_create_from_out_of_scope_source(): void
    {
        $attacker = $this->makeAttacker();
        $victim = DataPengundi::factory()->create([
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'no_ic' => '910303031234',
            'no_tel' => '0199990000',
            'alamat' => 'Alamat Rahsia Mangsa Dua, Muar',
            'submitted_by' => $this->makeSubmitter()->id,
        ]);

        $response = $this->actingAs($attacker)->post(route('reports.hasil-culaan.store-deceased'), [
            'locked_source_id' => $victim->id,
            'nama' => 'Percubaan Serangan Deceased',
            'no_ic' => '****',
            'no_tel' => '****',
            'bangsa' => '****',
            'alamat' => '****',
            'poskod' => '****',
            'negeri' => '****',
            'bandar' => '****',
            'parlimen' => $this->ownBandar->nama,
            'kadun' => $this->ownKadun->nama,
        ]);

        $response->assertSessionHasErrors(['no_ic']);

        // Only the original victim row exists; nothing new was created and
        // the victim record itself was not touched.
        $this->assertSame(1, DataPengundi::count());
        $this->assertSame(0, HasilCulaan::count());
        $victim->refresh();
        $this->assertFalse($victim->is_deceased);
    }

    public function test_store_deceased_still_swaps_real_values_from_in_scope_source(): void
    {
        $user = $this->makeAttacker();
        $source = DataPengundi::factory()->create([
            'kadun' => $this->ownKadun->nama,
            'bandar' => $this->ownBandar->nama,
            'no_ic' => '870404041234',
            'no_tel' => '0166667777',
            'alamat' => 'Alamat Sebenar Deceased, Segamat',
            'is_deceased' => false,
            'submitted_by' => $this->makeSubmitter()->id,
        ]);

        $response = $this->actingAs($user)->post(route('reports.hasil-culaan.store-deceased'), [
            'locked_source_id' => $source->id,
            'nama' => 'Rekod Deceased Sah',
            'no_ic' => '****',
            'no_tel' => '****',
            'bangsa' => '****',
            'alamat' => '****',
            'poskod' => '****',
            'negeri' => '****',
            'bandar' => '****',
            'parlimen' => $this->ownBandar->nama,
            'kadun' => $this->ownKadun->nama,
        ]);

        $response->assertSessionHasNoErrors();

        $source->refresh();
        $this->assertTrue($source->is_deceased);
    }

    // ------------------------------------------------------------------
    // hasilCulaanStoreDeceased: unscoped existing-record lookup
    // (marks-anyone-deceased finding, found while fixing the above)
    // ------------------------------------------------------------------

    public function test_store_deceased_blocks_marking_out_of_scope_ic_without_parlimen(): void
    {
        $attacker = $this->makeAttacker();
        $ic = '660505051234';

        $victimDp = DataPengundi::factory()->create([
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'no_ic' => $ic,
            'is_deceased' => false,
            'submitted_by' => $this->makeSubmitter()->id,
        ]);
        $victimHc = HasilCulaan::factory()->create([
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'no_ic' => $ic,
            'is_deceased' => false,
            'submitted_by' => $this->makeSubmitter()->id,
        ]);

        // No `parlimen` in the payload at all: the legacy guard is opt-in
        // by the caller and is skipped entirely when the key is absent.
        $this->actingAs($attacker)->post(route('reports.hasil-culaan.store-deceased'), [
            'no_ic' => $ic,
        ]);

        $victimDp->refresh();
        $victimHc->refresh();
        $this->assertFalse($victimDp->is_deceased, 'Out-of-scope DataPengundi record must not be marked deceased.');
        $this->assertFalse($victimHc->is_deceased, 'Out-of-scope HasilCulaan record must not be marked deceased.');
    }

    public function test_store_deceased_still_marks_in_scope_ic_deceased(): void
    {
        $user = $this->makeAttacker();
        $ic = '650606061234';

        $dp = DataPengundi::factory()->create([
            'kadun' => $this->ownKadun->nama,
            'bandar' => $this->ownBandar->nama,
            'no_ic' => $ic,
            'is_deceased' => false,
            'submitted_by' => $this->makeSubmitter()->id,
        ]);
        $hc = HasilCulaan::factory()->create([
            'kadun' => $this->ownKadun->nama,
            'bandar' => $this->ownBandar->nama,
            'no_ic' => $ic,
            'is_deceased' => false,
            'submitted_by' => $this->makeSubmitter()->id,
        ]);

        $this->actingAs($user)->post(route('reports.hasil-culaan.store-deceased'), [
            'no_ic' => $ic,
        ]);

        $dp->refresh();
        $hc->refresh();
        $this->assertTrue($dp->is_deceased, 'In-scope DataPengundi record should still be markable deceased.');
        $this->assertTrue($hc->is_deceased, 'In-scope HasilCulaan record should still be markable deceased.');
    }

    // ------------------------------------------------------------------
    // hasilCulaanStoreDeceased: phantom-row guard. With the lookups above
    // scoped, an out-of-scope IC that exists elsewhere falls through to the
    // "no record found -> create a new deceased DataPengundi" branch, which
    // is unscoped by nature (it's a create, not a lookup). That would let
    // an attacker plant a phantom deceased duplicate of a real out-of-scope
    // voter's IC in their own kawasan — and VoterSyncService::SHARED_FIELDS
    // includes is_deceased and matches purely on no_ic with no scope, so the
    // phantom can later propagate is_deceased=true onto the real voter's
    // records. The guard below refuses to create when the IC exists
    // anywhere, with the same success response either way (no oracle).
    // ------------------------------------------------------------------

    public function test_store_deceased_refuses_phantom_row_for_out_of_scope_existing_ic(): void
    {
        $attacker = $this->makeAttacker();
        $ic = '630707071234';

        $victimDp = DataPengundi::factory()->create([
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'no_ic' => $ic,
            'is_deceased' => false,
            'submitted_by' => $this->makeSubmitter()->id,
        ]);

        $this->assertDatabaseCount('data_pengundi', 1);
        $this->assertDatabaseCount('hasil_culaan', 0);

        // No `parlimen`: the legacy guard is opt-in and skipped when absent.
        // The scoped lookups find nothing (victim is outside the attacker's
        // Kadun), so without the phantom-row guard this would fall through
        // to creating a new deceased DataPengundi row for the real IC. The
        // other fields are filled in (not just no_ic) so a missing guard
        // manifests as an actual phantom row, not an unrelated missing-key
        // notice from the fallback-create block's own field defaults.
        $response = $this->actingAs($attacker)->post(route('reports.hasil-culaan.store-deceased'), [
            'nama' => 'Percubaan Fantom',
            'no_ic' => $ic,
            'no_tel' => '0139998888',
            'bangsa' => 'Melayu',
            'alamat' => 'Alamat Percubaan',
            'poskod' => '84000',
            'negeri' => 'JOHOR',
            'bandar' => $this->ownBandar->nama,
    // parlimen intentionally left empty (not omitted): still exercises
            // the legacy opt-in Parlimen guard being skipped exactly as it
            // would be if the key were absent entirely (`!empty()` is false
            // either way).
            'parlimen' => '',
            'kadun' => $this->ownKadun->nama,
        ]);

        $response->assertRedirect(route('reports.data-pengundi.index'));
        $response->assertSessionHas('success', 'Rekod telah ditandakan sebagai kematian');

        // No phantom row: still exactly the one original victim row.
        $this->assertDatabaseCount('data_pengundi', 1);
        $this->assertDatabaseCount('hasil_culaan', 0);

        $victimDp->refresh();
        $this->assertFalse($victimDp->is_deceased, 'The real out-of-scope voter must not be marked deceased either.');
    }

    public function test_store_deceased_still_creates_for_genuinely_new_ic_in_own_kawasan(): void
    {
        $user = $this->makeAttacker();
        $ic = '620808081234';

        // This IC exists nowhere in the system at all — the legitimate
        // "record a death for someone not yet in the database" case.
        $this->assertDatabaseCount('data_pengundi', 0);
        $this->assertDatabaseCount('hasil_culaan', 0);

        $response = $this->actingAs($user)->post(route('reports.hasil-culaan.store-deceased'), [
            'nama' => 'Pengundi Baharu',
            'no_ic' => $ic,
            'no_tel' => '0121112222',
            'bangsa' => 'Melayu',
            'alamat' => 'Alamat Baharu',
            'poskod' => '85000',
            'negeri' => 'JOHOR',
            'bandar' => $this->ownBandar->nama,
            'parlimen' => $this->ownBandar->nama,
            'kadun' => $this->ownKadun->nama,
        ]);

        $response->assertRedirect(route('reports.data-pengundi.index'));

        $new = DataPengundi::where('no_ic', $ic)->first();
        $this->assertNotNull($new, 'A genuinely new IC in the caller\'s own kawasan must still create a record.');
        $this->assertTrue($new->is_deceased);
        $this->assertSame($user->id, $new->submitted_by);
    }

    // ------------------------------------------------------------------
    // Oracle-safety of the phantom-row guard. A minimal `no_ic`-only
    // payload used to split by response status: an existing-anywhere IC
    // hit the guard and got a 302, while a genuinely-new IC fell through to
    // the create block and 500'd on `$validated['nama'] ?: 'Tidak
    // Diketahui'` (Elvis reads the array key before checking it exists, so
    // an omitted nullable field crashed it). That 302-vs-500 split is a
    // working oracle over which ICs exist, contradicting the guard's own
    // "cannot be used to probe" comment. Fixed by switching the
    // fallback-create block from `?:` to `??` (null-coalesce short-circuits
    // on a missing key) so a minimal payload no longer crashes either way.
    // ------------------------------------------------------------------

    public function test_store_deceased_minimal_payload_for_new_ic_does_not_500(): void
    {
        $user = $this->makeAttacker();
        $ic = '610909091234';

        $response = $this->actingAs($user)->post(route('reports.hasil-culaan.store-deceased'), [
            'no_ic' => $ic,
        ]);

        $response->assertStatus(302);
        $new = DataPengundi::where('no_ic', $ic)->first();
        $this->assertNotNull($new, 'A minimal no_ic-only post for a genuinely new IC must still create a record, not 500.');
        $this->assertTrue($new->is_deceased);
    }

    public function test_store_deceased_minimal_payload_gives_identical_status_for_existing_and_new_ic(): void
    {
        $existingIcUser = $this->makeAttacker();
        $existingIc = '600101011234';
        DataPengundi::factory()->create([
            'kadun' => $this->foreignKadun->nama,
            'bandar' => $this->foreignBandar->nama,
            'no_ic' => $existingIc,
            'is_deceased' => false,
            'submitted_by' => $this->makeSubmitter()->id,
        ]);

        $existingResponse = $this->actingAs($existingIcUser)->post(route('reports.hasil-culaan.store-deceased'), [
            'no_ic' => $existingIc,
        ]);

        $newIcUser = $this->makeAttacker();
        $newIc = '590202021234';

        $newResponse = $this->actingAs($newIcUser)->post(route('reports.hasil-culaan.store-deceased'), [
            'no_ic' => $newIc,
        ]);

        $this->assertSame(
            $existingResponse->getStatusCode(),
            $newResponse->getStatusCode(),
            'A minimal no_ic-only payload must return the same HTTP status whether the IC exists elsewhere or is brand new — otherwise the status code itself is an oracle over which ICs exist.'
        );
        $existingResponse->assertStatus(302);
        $newResponse->assertStatus(302);
    }
}
