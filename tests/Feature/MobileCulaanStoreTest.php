<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCulaanStoreTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;
    private Bandar $bandar;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'JOHOR']);
        $this->bandar = Bandar::create(['nama' => 'SEGAMAT', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'BULOH KASAP', 'bandar_id' => $this->bandar->id]);
    }

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'nama' => 'Ahmad bin Ali',
            'no_ic' => '800101015555',
            'umur' => 45,
            'no_tel' => '0123456789',
            'bangsa' => 'Melayu',
            'alamat' => 'No 1, Jalan Besar',
            'poskod' => '85000',
            'negeri' => 'JOHOR',
            'bandar' => 'SEGAMAT',
            'parlimen' => 'SEGAMAT',
            'kadun' => 'BULOH KASAP',
            'has_sumbangan' => false,
        ], $overrides);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/mobile/culaan', $this->payload())->assertStatus(401);
    }

    public function test_creates_a_culaan_record(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('hasil_culaan', ['no_ic' => '800101015555']);
    }

    public function test_replaying_the_same_idempotency_key_does_not_create_a_second_record(): void
    {
        Sanctum::actingAs($this->makeUser());
        $payload = $this->payload();

        $first = $this->postJson('/api/mobile/culaan', $payload)->assertStatus(201);
        $second = $this->postJson('/api/mobile/culaan', $payload)->assertStatus(201);

        // This is the lost-response retry. One row, and the SAME row.
        $this->assertSame(1, HasilCulaan::where('no_ic', '800101015555')->count());
        $this->assertSame(
            $first->json('culaan.id'),
            $second->json('culaan.id'),
            'A replayed key must return the original record, not write a new one.'
        );
    }

    public function test_rejects_a_record_outside_the_users_parlimen_with_403(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload(['parlimen' => 'MUAR']))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.parlimen.0', 'Rekod ini di luar Parlimen anda.');
    }

    public function test_missing_required_field_returns_422_not_500(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload(['nama' => '']))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['nama']]);
    }

    public function test_sumbangan_fields_are_required_only_when_has_sumbangan_is_true(): void
    {
        Sanctum::actingAs($this->makeUser());

        // Without the toggle, the Isi Rumah / Bantuan fields may be absent.
        $this->postJson('/api/mobile/culaan', $this->payload())->assertStatus(201);

        // With it, they are required.
        $this->postJson('/api/mobile/culaan', $this->payload([
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'no_ic' => '800101015556',
            'has_sumbangan' => true,
        ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['bil_isi_rumah']]);
    }

    public function test_checkbox_arrays_are_flattened_via_the_normalizer(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload([
            'has_sumbangan' => true,
            'bil_isi_rumah' => 4,
            'pekerjaan' => 'Swasta',
            'jenis_pekerjaan' => ['Pembuatan'],
            'pemilik_rumah' => 'Sendiri',
            'jenis_sumbangan' => ['Tunai', 'Lain-lain'],
            'jenis_sumbangan_lain' => 'Baucar buku',
            'tujuan_sumbangan' => ['Pendidikan'],
            'bantuan_lain' => ['Tiada'],
        ]))->assertStatus(201);

        $this->assertDatabaseHas('hasil_culaan', [
            'no_ic' => '800101015555',
            'jenis_sumbangan' => 'Tunai, Baucar buku',
        ]);
    }

    public function test_idempotency_key_is_required(): void
    {
        Sanctum::actingAs($this->makeUser());
        $payload = $this->payload();
        unset($payload['idempotency_key']);

        $this->postJson('/api/mobile/culaan', $payload)->assertStatus(422);
    }

    public function test_validation_messages_are_bahasa_melayu_not_english(): void
    {
        Sanctum::actingAs($this->makeUser());

        // assertJsonStructure alone is language-blind: Laravel's default
        // English messages ("The nama field is required.") would satisfy
        // it just as well as a BM string. Assert the literal text.
        $this->postJson('/api/mobile/culaan', $this->payload(['nama' => '']))
            ->assertStatus(422)
            ->assertJsonPath('errors.nama.0', 'Sila masukkan nama.');

        $this->postJson('/api/mobile/culaan', $this->payload(['no_ic' => '123']))
            ->assertStatus(422)
            ->assertJsonPath('errors.no_ic.0', 'Nombor IC mesti 12 digit.');

        $this->postJson('/api/mobile/culaan', $this->payload([
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'no_ic' => '800101015557',
            'has_sumbangan' => true,
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.bil_isi_rumah.0', 'Sila masukkan bilangan isi rumah.');
    }

    /**
     * A masked-create payload: sensitive fields carry '****' placeholders
     * and locked_source_id points at the record they should be swapped in
     * from. Mirrors the shape the Flutter client sends when re-submitting
     * a locked (masked) voter it found via search.
     *
     * Only the SENSITIVE_FIELDS whose StoreMobileCulaanRequest rule accepts
     * a bare string can actually carry the '****' placeholder through
     * validation: no_ic (digits:12), umur (integer) and
     * pendapatan_isi_rumah (numeric) reject '****' outright and 422 before
     * the controller ever sees them — a pre-existing quirk of this
     * validate-then-swap ordering (unlike ReportsController::hasilCulaanStore,
     * which merges the swap into the request before validating), and not
     * something this fix touches. no_tel/bangsa/alamat/poskod/negeri/bandar
     * are plain string rules and mask through fine, which is enough to
     * exercise both the leak and the swap.
     */
    private function maskedPayload(int $lockedSourceId, array $overrides = []): array
    {
        return $this->payload(array_merge([
            'no_tel' => '****',
            'bangsa' => '****',
            'alamat' => '****',
            'poskod' => '****',
            'negeri' => '****',
            'bandar' => '****',
            'locked_source_id' => $lockedSourceId,
        ], $overrides));
    }

    public function test_locked_source_id_outside_the_users_kadun_returns_409_and_creates_nothing(): void
    {
        $caller = $this->makeUser();
        Sanctum::actingAs($caller);

        $stranger = DataPengundi::factory()->create([
            'no_ic' => '900202025555',
            'no_tel' => '0199998888',
            'alamat' => 'Rumah Rahsia, Kampung Lain',
            'kadun' => 'KADUN LAIN', // outside the caller's scope
        ]);

        $response = $this->postJson('/api/mobile/culaan', $this->maskedPayload($stranger->id))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.locked_source_id.0', 'Rekod sumber tidak lagi wujud. Sila cari semula pengundi ini.');

        // The core write guarantee: an out-of-scope source must not result
        // in ANY row being written, masked or otherwise.
        $this->assertDatabaseCount('hasil_culaan', 0);
    }

    public function test_locked_source_id_outside_scope_never_leaks_the_strangers_pii(): void
    {
        $caller = $this->makeUser();
        Sanctum::actingAs($caller);

        $stranger = DataPengundi::factory()->create([
            'no_ic' => '900202029999',
            'no_tel' => '0177776666',
            'alamat' => 'Rumah Rahsia, Kampung Lain',
            'kadun' => 'KADUN LAIN',
            'parlimen' => 'SEGAMAT',
        ]);

        $this->postJson('/api/mobile/culaan', $this->maskedPayload($stranger->id))
            ->assertStatus(409);

        // The actual security property: the stranger's real sensitive
        // values must never have been copied into a new hasil_culaan row
        // (there must be none at all — the write never happened)...
        $this->assertDatabaseCount('hasil_culaan', 0);
        $this->assertDatabaseMissing('hasil_culaan', ['no_ic' => '900202029999']);
        $this->assertDatabaseMissing('hasil_culaan', ['no_tel' => '0177776666']);
        $this->assertDatabaseMissing('hasil_culaan', ['alamat' => 'Rumah Rahsia, Kampung Lain']);

        // ...and the stranger's own pre-existing data_pengundi row must be
        // untouched: still exactly one row, with its own kadun intact
        // rather than overwritten by the attacker's fan-out via
        // VoterSyncService (which never ran, because the create never ran).
        $this->assertDatabaseCount('data_pengundi', 1);
        $this->assertDatabaseHas('data_pengundi', [
            'id' => $stranger->id,
            'no_tel' => '0177776666',
            'alamat' => 'Rumah Rahsia, Kampung Lain',
            'kadun' => 'KADUN LAIN',
        ]);
    }

    public function test_locked_source_id_inside_scope_still_swaps_in_the_real_values(): void
    {
        $caller = $this->makeUser();
        Sanctum::actingAs($caller);

        $inScope = DataPengundi::factory()->create([
            'no_ic' => '850303035555',
            'no_tel' => '0122223333',
            'alamat' => 'No 5, Jalan Dalam Skop',
            'poskod' => '86000',
            'negeri' => 'JOHOR',
            'bandar' => 'SEGAMAT',
            'kadun' => 'BULOH KASAP', // inside the caller's own kadun
            'umur' => 40,
            'bangsa' => 'Cina',
        ]);

        $this->postJson('/api/mobile/culaan', $this->maskedPayload($inScope->id))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        // The masked-create flow must still work end to end: the real
        // values were swapped in and actually persisted, not just echoed.
        // no_ic itself is the caller's own typed value (payload()'s
        // default) since that field cannot carry the '****' placeholder
        // through validation — see maskedPayload()'s docblock.
        $this->assertDatabaseHas('hasil_culaan', [
            'no_ic' => '800101015555',
            'no_tel' => '0122223333',
            'alamat' => 'No 5, Jalan Dalam Skop',
            'poskod' => '86000',
            'bangsa' => 'Cina',
        ]);
    }

    public function test_nonexistent_locked_source_id_returns_the_same_409_as_out_of_scope(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->maskedPayload(999999))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.locked_source_id.0', 'Rekod sumber tidak lagi wujud. Sila cari semula pengundi ini.');

        $this->assertDatabaseCount('hasil_culaan', 0);
    }

    /**
     * Regression test for the plan bug: the masked-create swap used to run
     * in the controller AFTER $request->validated(), so no_ic (digits:12),
     * umur (integer) and pendapatan_isi_rumah (numeric) — the three
     * SENSITIVE_FIELDS whose rules reject a bare '****' — 422'd before the
     * swap code ever ran. The fix moves the swap into
     * StoreMobileCulaanRequest::prepareForValidation(), which runs before
     * rules() are evaluated, so validation sees the real values.
     *
     * This is the one masked-create test that exercises ALL of
     * SENSITIVE_FIELDS as '****', including the three strict ones the
     * other tests in this file deliberately avoid (see maskedPayload()'s
     * docblock) precisely because they used to break under the old
     * ordering.
     *
     * Note on pendapatan_isi_rumah: the masked-create source is always a
     * DataPengundi row (both here and in ReportsController::hasilCulaanStore),
     * and the data_pengundi table has no pendapatan_isi_rumah column at all
     * (see 2025_11_22_021510_create_data_pengundi_table.php — it's only
     * ever a hasil_culaan column). So $source->pendapatan_isi_rumah is
     * always null, and swapping it in correctly stores null, not a
     * fabricated figure — consistent with CLAUDE.md's "unknown is not
     * zero" rule. This is a pre-existing schema quirk, unrelated to and
     * unchanged by this fix; it just means this field can never actually
     * carry a "real value" through the swap the way no_ic/umur do.
     */
    public function test_masked_create_swaps_in_the_strict_sensitive_fields_and_succeeds(): void
    {
        $caller = $this->makeUser();
        Sanctum::actingAs($caller);

        $inScope = DataPengundi::factory()->create([
            'no_ic' => '900101015555',
            'umur' => 33,
            'no_tel' => '0133334444',
            'bangsa' => 'India',
            'alamat' => 'No 9, Jalan Skop Penuh',
            'poskod' => '86100',
            'negeri' => 'JOHOR',
            'bandar' => 'SEGAMAT',
            'kadun' => 'BULOH KASAP', // inside the caller's own kadun
        ]);

        $payload = $this->maskedPayload($inScope->id, [
            'no_ic' => '****',
            'umur' => '****',
            'pendapatan_isi_rumah' => '****',
        ]);

        $this->postJson('/api/mobile/culaan', $payload)
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('hasil_culaan', [
            'no_ic' => '900101015555',
            'umur' => 33,
            'pendapatan_isi_rumah' => null,
            'no_tel' => '0133334444',
            'bangsa' => 'India',
            'alamat' => 'No 9, Jalan Skop Penuh',
            'poskod' => '86100',
        ]);
    }

    /**
     * Requirement 2: moving the swap into prepareForValidation() must not
     * regress the IDOR fix. An out-of-scope locked_source_id must still
     * 409 with the identical message even when the strict fields
     * (no_ic/umur/pendapatan_isi_rumah) are also masked — those are
     * exactly the fields a real attack would want swapped in.
     */
    public function test_masked_create_with_strict_fields_outside_scope_still_returns_the_same_409(): void
    {
        Sanctum::actingAs($this->makeUser());

        $stranger = DataPengundi::factory()->create([
            'no_ic' => '900303035555',
            'umur' => 50,
            'kadun' => 'KADUN LAIN', // outside the caller's scope
        ]);

        $payload = $this->maskedPayload($stranger->id, [
            'no_ic' => '****',
            'umur' => '****',
            'pendapatan_isi_rumah' => '****',
        ]);

        $this->postJson('/api/mobile/culaan', $payload)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.locked_source_id.0', 'Rekod sumber tidak lagi wujud. Sila cari semula pengundi ini.');

        $this->assertDatabaseCount('hasil_culaan', 0);
    }

    /**
     * Requirement 3: without a locked_source_id there is no authority to
     * swap anything in, so a bare '****' for no_ic must still fail
     * validation exactly like any other malformed value — it must not
     * become a magic bypass just because it happens to match the mask
     * constant.
     */
    public function test_masked_no_ic_without_locked_source_id_still_returns_422(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload(['no_ic' => '****']))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.no_ic.0', 'Nombor IC mesti 12 digit.');

        $this->assertDatabaseCount('hasil_culaan', 0);
    }
}
