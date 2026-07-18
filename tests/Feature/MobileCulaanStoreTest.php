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

    /**
     * Finding 1 (CRITICAL): the 201 response used to echo the record's real
     * no_ic even for a masked-create submitted by a 'user'-role caller who
     * was never shown it — collapsing the IC-oracle protection
     * (MobileVoterController.php:56-66) back to a single request: search by
     * name -> read id -> POST masked-create -> read the real IC out of the
     * 201. The response must be run through VoterDataMasker so a caller who
     * cannot unmask gets '****' back, exactly what they sent.
     */
    public function test_masked_create_response_masks_no_ic_for_a_user_caller(): void
    {
        $caller = $this->makeUser('user');
        Sanctum::actingAs($caller);

        $inScope = DataPengundi::factory()->create([
            'no_ic' => '900101019999',
            'no_tel' => '0111112222',
            'kadun' => 'BULOH KASAP',
        ]);

        $response = $this->postJson('/api/mobile/culaan', $this->maskedPayload($inScope->id, [
            'no_ic' => '900101019999', // caller's own typed value; not masked in this request
        ]))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        // The real IC was swapped into storage (masked-create still works)...
        $this->assertDatabaseHas('hasil_culaan', ['no_ic' => '900101019999']);

        // ...but the response the caller (role 'user') receives must not
        // disclose it, because a 'user'-role submission is always locked
        // and a 'user'-role viewer can never unmask their own submission
        // (VoterDataMasker::canUnmask requires admin/super_user/super_admin
        // — this is consistent with how ReportsController/DashboardController
        // treat every other read of a locked record).
        $response->assertJsonPath('culaan.no_ic', '****');
    }

    /**
     * Finding 1 continued: a record is only "locked" when its submitter's
     * role is 'user' (VoterDataMasker::isLocked). An admin-role caller's OWN
     * submission is never locked in the first place, so their 201 response
     * legitimately carries the real no_ic — this is the "unmasking viewer"
     * case the finding asks to confirm one way or the other.
     */
    public function test_admin_callers_own_submission_is_not_locked_so_response_shows_real_no_ic(): void
    {
        $admin = $this->makeUser('admin');
        Sanctum::actingAs($admin);

        $this->postJson('/api/mobile/culaan', $this->payload(['no_ic' => '811111112222']))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('culaan.no_ic', '811111112222');
    }

    /**
     * Finding 2 (IMPORTANT): the idempotency replay lookup used to be
     * unscoped, so a second user submitting a colliding key received the
     * FIRST user's record id/no_ic outright, and because the short-circuit
     * fired before the Parlimen check, the 403 was bypassed too. The
     * replayed key must never disclose a record the caller does not own.
     *
     * This scenario also mismatches B's submitted parlimen against B's own
     * Bandar (SEGAMAT, A's parlimen) so the pre-fix bypass of the 403 check
     * would have been visible had it still existed.
     */
    public function test_cross_user_idempotency_replay_never_returns_another_users_record_and_403_still_applies(): void
    {
        $negeriB = Negeri::create(['nama' => 'PERAK']);
        $bandarB = Bandar::create(['nama' => 'IPOH', 'negeri_id' => $negeriB->id]);
        $kadunB = Kadun::create(['nama' => 'KLEBANG', 'bandar_id' => $bandarB->id]);
        $userB = User::factory()->create([
            'role' => 'user',
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $negeriB->id,
            'bandar_id' => $bandarB->id,
            'kadun_id' => $kadunB->id,
        ]);

        $sharedKey = 'shared-key-123';

        Sanctum::actingAs($this->makeUser());
        $first = $this->postJson('/api/mobile/culaan', $this->payload(['idempotency_key' => $sharedKey]))
            ->assertStatus(201);
        $originalId = $first->json('culaan.id');

        Sanctum::actingAs($userB);
        // B's own Parlimen is IPOH, but this payload (mirroring A's) claims
        // SEGAMAT. Pre-fix, the unscoped replay short-circuit ran before the
        // Parlimen check and returned A's record with a 201 anyway.
        $second = $this->postJson('/api/mobile/culaan', $this->payload(['idempotency_key' => $sharedKey]));

        $second->assertStatus(403);
        $this->assertNotSame($originalId, $second->json('culaan.id'));
        $this->assertSame(1, HasilCulaan::where('idempotency_key', $sharedKey)->count());
    }

    /**
     * Finding 2 continued: when B's own payload legitimately passes the
     * Parlimen check (i.e. the create actually proceeds), the collision
     * surfaces via hasil_culaan's real unique index on idempotency_key. The
     * QueryException backstop must not resolve that into A's row — it must
     * return a scoped 409, not disclose A's record to B.
     */
    public function test_cross_user_idempotency_key_collision_returns_409_not_the_other_users_record(): void
    {
        $negeriB = Negeri::create(['nama' => 'PERAK']);
        $bandarB = Bandar::create(['nama' => 'IPOH', 'negeri_id' => $negeriB->id]);
        $kadunB = Kadun::create(['nama' => 'KLEBANG', 'bandar_id' => $bandarB->id]);
        $userB = User::factory()->create([
            'role' => 'user',
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $negeriB->id,
            'bandar_id' => $bandarB->id,
            'kadun_id' => $kadunB->id,
        ]);

        $sharedKey = 'shared-key-456';

        Sanctum::actingAs($this->makeUser());
        $first = $this->postJson('/api/mobile/culaan', $this->payload(['idempotency_key' => $sharedKey]))
            ->assertStatus(201);
        $originalId = $first->json('culaan.id');

        Sanctum::actingAs($userB);
        $second = $this->postJson('/api/mobile/culaan', $this->payload([
            'idempotency_key' => $sharedKey,
            'no_ic' => '822222223333',
            'parlimen' => 'IPOH', // B's own Parlimen — clears the 403 check
            'bandar' => 'IPOH',
            'kadun' => 'KLEBANG',
        ]));

        $second->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.idempotency_key.0', 'Kunci idempotency ini telah digunakan oleh pengguna lain.');

        $this->assertNotSame($originalId, $second->json('culaan.id'));
        $this->assertSame(1, HasilCulaan::where('idempotency_key', $sharedKey)->count());
        $this->assertDatabaseMissing('hasil_culaan', ['no_ic' => '822222223333']);
    }

    /**
     * Finding 3 (IMPORTANT): moving the masked-create swap into
     * prepareForValidation() put source resolution ahead of the replay
     * short-circuit, so a replay whose source had since been deleted
     * returned a 409 "Rekod sumber tidak lagi wujud" instead of the
     * original 201 — misclassifying a submission that had already landed
     * as a permanent failure. The replay check must run before source
     * resolution so this returns the ORIGINAL record regardless of whether
     * the source still exists.
     */
    public function test_replay_after_source_deleted_returns_the_original_record_not_409(): void
    {
        Sanctum::actingAs($this->makeUser());

        $source = DataPengundi::factory()->create([
            'no_ic' => '900404045555',
            'no_tel' => '0144445555',
            'kadun' => 'BULOH KASAP',
        ]);

        $payload = $this->maskedPayload($source->id, ['idempotency_key' => 'replay-after-delete']);

        $first = $this->postJson('/api/mobile/culaan', $payload)->assertStatus(201);
        $originalId = $first->json('culaan.id');

        $source->delete();

        $second = $this->postJson('/api/mobile/culaan', $payload);

        $second->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('culaan.id', $originalId);

        $this->assertSame(1, HasilCulaan::where('idempotency_key', 'replay-after-delete')->count());
    }

    /**
     * Finding 4 (IMPORTANT): DB::transaction() wraps create + EditHistory::log
     * + VoterSyncService's fan-out, but nothing asserted atomicity. Force a
     * failure right after the hasil_culaan row is physically inserted (via
     * the model's 'created' event, which fires synchronously inside
     * create() and therefore still inside the open transaction) and prove
     * that row is rolled back.
     *
     * This variant fails BEFORE VoterSyncService::syncFromHasilCulaan() runs
     * at all, so it only proves the hasil_culaan half — see
     * test_a_failure_during_the_fan_out_rolls_back_both_tables() below for
     * the half that actually exercises the fan-out.
     *
     * Mutation-checked manually: removing DB::transaction() from
     * MobileCulaanController::store() makes this test fail (the
     * hasil_culaan row survives the later exception).
     */
    public function test_a_failure_after_create_rolls_back_the_whole_transaction(): void
    {
        Sanctum::actingAs($this->makeUser());

        HasilCulaan::created(function () {
            throw new \RuntimeException('Forced fan-out failure for atomicity test.');
        });

        $this->postJson('/api/mobile/culaan', $this->payload())->assertStatus(500);

        $this->assertDatabaseCount('hasil_culaan', 0);
        $this->assertDatabaseCount('data_pengundi', 0);
    }

    /**
     * Finding D (cleanup round, MINOR): the test above forces its failure
     * from HasilCulaan::created, which fires before
     * VoterSyncService::syncFromHasilCulaan() is ever called — so
     * data_pengundi never gets a single insert attempt, and its
     * assertDatabaseCount('data_pengundi', 0) is vacuously true regardless
     * of whether the transaction actually works. It was previously
     * documented as proving the fan-out rolls back, which was false.
     *
     * This variant forces the failure from DataPengundi::created instead —
     * the event VoterSyncService::syncFromHasilCulaan()'s
     * DataPengundi::create() call fires, still synchronously inside the
     * same open transaction — so the fan-out actually runs, actually
     * inserts a row, and that insert is the one being rolled back. This is
     * the assertion the old docblock claimed to make.
     *
     * Mutation-checked manually: removing DB::transaction() from
     * MobileCulaanController::store() makes this test fail on the
     * data_pengundi assertion specifically (asserted first, below, for
     * exactly that reason) — the fan-out row it just inserted survives the
     * later exception instead of being rolled back with it. (The
     * hasil_culaan row would also survive without the transaction, but
     * that half is already covered by the sibling test above; asserting
     * data_pengundi first here is what makes THIS test's failure legible as
     * "the fan-out didn't roll back" rather than being masked by the
     * earlier test's assertion order.)
     */
    public function test_a_failure_during_the_fan_out_rolls_back_both_tables(): void
    {
        Sanctum::actingAs($this->makeUser());

        DataPengundi::created(function () {
            throw new \RuntimeException('Forced fan-out failure for atomicity test.');
        });

        $this->postJson('/api/mobile/culaan', $this->payload())->assertStatus(500);

        $this->assertDatabaseCount('data_pengundi', 0);
        $this->assertDatabaseCount('hasil_culaan', 0);
    }

    /**
     * Finding 5 (MINOR): prepareForValidation() runs before rules(), so
     * feeding an array into locked_source_id used to reach
     * DataPengundi::where('id', $array) before the `integer` rule could
     * reject it — SQLite quietly 409s, but PDO MySQL typically raises
     * HY093 -> QueryException -> 500, an actionable rejection surfacing as
     * a transient failure the client retries forever. The guard must let
     * the honest `integer` validation rule 422 it instead.
     */
    public function test_locked_source_id_as_an_array_returns_422_not_a_query_error(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload(['locked_source_id' => ['1', '2']]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['locked_source_id']]);

        $this->assertDatabaseCount('hasil_culaan', 0);
    }

    /**
     * Finding 6 (MINOR): the original replay test passes even if the scoped
     * early-return lookup (StoreMobileCulaanRequest::shortCircuitIfReplay())
     * is mutated away, because the QueryException unique-index backstop in
     * the controller rescues it. That means the test cannot tell which
     * mechanism is actually working. Pin the PRIMARY path: a same-user
     * replay must never even reach HasilCulaan::create() — if it falls
     * through to the backstop, create() IS attempted (and its INSERT then
     * fails on the unique index), so counting attempts via the model's
     * 'creating' event (which fires before the INSERT runs, so it counts
     * even a failed attempt — unlike DB::listen, which Laravel never fires
     * for a query that throws) tells the two paths apart.
     */
    public function test_idempotency_replay_is_caught_by_the_scoped_lookup_before_create_is_attempted(): void
    {
        Sanctum::actingAs($this->makeUser());
        $payload = $this->payload();

        $this->postJson('/api/mobile/culaan', $payload)->assertStatus(201);

        $createAttempts = 0;
        HasilCulaan::creating(function () use (&$createAttempts) {
            $createAttempts++;
        });

        $this->postJson('/api/mobile/culaan', $payload)->assertStatus(201);

        $this->assertSame(
            0,
            $createAttempts,
            'A same-user replay must be caught by the scoped prepareForValidation() lookup before HasilCulaan::create() is ever attempted — falling through to the unique-constraint backstop means the primary path silently broke.'
        );
    }

    /**
     * Finding A (IMPORTANT, cleanup round): assertJsonStructure(['errors' =>
     * ['locked_source_id']]) — the shape of the old test for the array
     * case — is language-blind: it passes identically whether the message
     * is BM or Laravel's English default ("The locked source id field must
     * be an integer."). That blind spot is exactly how a real regression
     * shipped: a `nullable|integer` rule with no messages() entry.
     *
     * This asserts the actual text a field agent would see, matching the
     * literal BM messages() now defines for the four fields the finding
     * named as previously missing coverage.
     */
    public function test_finding_a_named_fields_return_bahasa_melayu_not_english(): void
    {
        Sanctum::actingAs($this->makeUser());

        // locked_source_id: array -> `integer` rule. Deliberately does not
        // name the field in the message (see StoreMobileCulaanRequest's
        // messages() docblock) — it's an internal wiring field, never
        // something a field agent typed.
        $this->postJson('/api/mobile/culaan', $this->payload(['locked_source_id' => ['1', '2']]))
            ->assertStatus(422)
            ->assertJsonPath('errors.locked_source_id.0', 'Rekod yang dirujuk tidak sah. Sila cari semula pengundi ini.');

        // has_sumbangan: not a recognised boolean value -> `boolean` rule.
        $this->postJson('/api/mobile/culaan', $this->payload(['has_sumbangan' => 'entahlah']))
            ->assertStatus(422)
            ->assertJsonPath('errors.has_sumbangan.0', 'Status sumbangan tidak sah.');

        // nota: array -> `string` rule.
        $this->postJson('/api/mobile/culaan', $this->payload(['nota' => ['x']]))
            ->assertStatus(422)
            ->assertJsonPath('errors.nota.0', 'Nota tidak sah.');

        // pendapatan_isi_rumah: non-numeric -> `numeric` rule.
        $this->postJson('/api/mobile/culaan', $this->payload(['pendapatan_isi_rumah' => 'bukan-nombor']))
            ->assertStatus(422)
            ->assertJsonPath('errors.pendapatan_isi_rumah.0', 'Pendapatan isi rumah tidak sah.');
    }

    /**
     * Finding A (IMPORTANT, cleanup round) — the actual regression net.
     *
     * A single request cannot trip both "required" (field absent) and a
     * type/format rule (field present but wrong) on the same field, so this
     * sweeps in two passes:
     *
     *  1. An empty payload with has_sumbangan=true (so the has_sumbangan-
     *     conditional fields become `required` too) trips every `required`
     *     rule in rules().
     *  2. A payload where every field is present but of a deliberately
     *     wrong type/shape trips every `string`, `integer`, `numeric`,
     *     `array`, `boolean`, `in` and `digits` rule in rules().
     *
     * Both passes assert, over EVERY message in the response, that none of
     * them match Laravel's English default shape ("The :attribute field
     * ..."). This is deliberately not a fixed list of fields: if a future
     * rule is added to rules() without a matching messages() entry, pass 1
     * or 2 will produce an English string for it and this test fails —
     * that's the point.
     */
    public function test_every_validation_rule_produces_a_bahasa_melayu_message(): void
    {
        Sanctum::actingAs($this->makeUser());

        $englishDefaultShape = '/^The .+ field /i';

        // Pass 1: every `required` rule, including the has_sumbangan-
        // conditional ones (has_sumbangan=true makes them required).
        $requiredSweep = $this->postJson('/api/mobile/culaan', [
            'has_sumbangan' => true,
        ])->assertStatus(422);

        $requiredErrors = $requiredSweep->json('errors');
        $this->assertNotEmpty($requiredErrors, 'Expected the near-empty payload to fail validation on multiple fields.');
        foreach ($requiredErrors as $field => $messages) {
            foreach ($messages as $message) {
                $this->assertDoesNotMatchRegularExpression(
                    $englishDefaultShape,
                    $message,
                    "Field '{$field}' produced an English default message: \"{$message}\". Add a BM entry to StoreMobileCulaanRequest::messages()."
                );
            }
        }
        // Sanity: this must have actually covered every base-required field
        // plus every has_sumbangan-conditional one, not silently 200'd.
        foreach ([
            'nama', 'no_ic', 'umur', 'no_tel', 'bangsa', 'alamat', 'poskod',
            'negeri', 'bandar', 'parlimen', 'kadun', 'idempotency_key',
            'bil_isi_rumah', 'pekerjaan', 'jenis_pekerjaan', 'pemilik_rumah',
            'jenis_sumbangan', 'tujuan_sumbangan', 'bantuan_lain',
        ] as $expectedField) {
            $this->assertArrayHasKey($expectedField, $requiredErrors, "Expected a 'required' failure for '{$expectedField}'.");
        }

        // Pass 2: every field present, but wrong-typed. has_sumbangan is
        // itself given an invalid value, so the $req()-conditional fields
        // fall back to their `nullable` branch — still validated for type
        // when present, just not `required`.
        $wrongTypePayload = $this->payload([
            'idempotency_key' => ['a', 'b'],
            'nama' => ['x'],
            'no_ic' => 'BUKANDIGIT123',
            'umur' => 'bukan-nombor',
            'no_tel' => ['x'],
            'bangsa' => ['x'],
            'alamat' => ['x'],
            'poskod' => ['x'],
            'negeri' => ['x'],
            'bandar' => ['x'],
            'parlimen' => ['x'],
            'kadun' => ['x'],
            'mpkk' => ['x'],
            'daerah_mengundi' => ['x'],
            'lokaliti' => ['x'],
            'has_sumbangan' => 'entahlah',
            'locked_source_id' => ['1', '2'],
            'bil_isi_rumah' => 'bukan-nombor',
            'pendapatan_isi_rumah' => 'bukan-nombor',
            'pekerjaan' => 'Pilihan Tidak Wujud',
            'jenis_pekerjaan' => 'bukan-array',
            'jenis_pekerjaan_lain' => ['x'],
            'pemilik_rumah' => ['x'],
            'pemilik_rumah_lain' => ['x'],
            'jenis_sumbangan' => 'bukan-array',
            'jenis_sumbangan_lain' => ['x'],
            'tujuan_sumbangan' => 'bukan-array',
            'tujuan_sumbangan_lain' => ['x'],
            'bantuan_lain' => 'bukan-array',
            'bantuan_lain_lain' => ['x'],
            'perkeso_bantuan' => 'bukan-array',
            'perkeso_bantuan_lain' => ['x'],
            'zpp_jenis_bantuan' => 'bukan-array',
            'isejahtera_program' => ['x'],
            'bkb_program' => ['x'],
            'jumlah_bantuan_tunai' => 'bukan-nombor',
            'jumlah_wang_tunai' => 'bukan-nombor',
            'keahlian_parti' => ['x'],
            'kecenderungan_politik' => ['x'],
            'status_pengundi' => ['x'],
            'nota' => ['x'],
        ]);

        $wrongTypeSweep = $this->postJson('/api/mobile/culaan', $wrongTypePayload)
            ->assertStatus(422);

        $wrongTypeErrors = $wrongTypeSweep->json('errors');
        $this->assertNotEmpty($wrongTypeErrors, 'Expected the wrong-type payload to fail validation on multiple fields.');
        foreach ($wrongTypeErrors as $field => $messages) {
            foreach ($messages as $message) {
                $this->assertDoesNotMatchRegularExpression(
                    $englishDefaultShape,
                    $message,
                    "Field '{$field}' produced an English default message: \"{$message}\". Add a BM entry to StoreMobileCulaanRequest::messages()."
                );
            }
        }
        foreach (array_keys($wrongTypePayload) as $expectedField) {
            if ($expectedField === 'no_ic') {
                continue; // BUKANDIGIT123 trips `digits`, already covered elsewhere.
            }
            $this->assertArrayHasKey($expectedField, $wrongTypeErrors, "Expected a type-validation failure for '{$expectedField}'.");
        }

        $this->assertDatabaseCount('hasil_culaan', 0);
    }

    /**
     * Finding A continued: `max` rules are exercised separately since
     * tripping them requires a present, correctly-typed, over-length value
     * rather than a wrong type — a different payload shape than the sweep
     * above.
     */
    public function test_max_length_rules_produce_a_bahasa_melayu_message(): void
    {
        Sanctum::actingAs($this->makeUser());

        $englishDefaultShape = '/^The .+ field /i';

        $overLongPayload = $this->payload([
            'idempotency_key' => str_repeat('a', 65),
            'mpkk' => str_repeat('a', 256),
            'status_pengundi' => str_repeat('a', 256),
            'daerah_mengundi' => str_repeat('a', 256),
        ]);

        $response = $this->postJson('/api/mobile/culaan', $overLongPayload)->assertStatus(422);

        $response->assertJsonPath('errors.idempotency_key.0', 'Kunci idempotency terlalu panjang.');
        $response->assertJsonPath('errors.mpkk.0', 'MPKK tidak boleh melebihi 255 aksara.');
        $response->assertJsonPath('errors.status_pengundi.0', 'Status pengundi tidak boleh melebihi 255 aksara.');
        $response->assertJsonPath('errors.daerah_mengundi.0', 'Daerah mengundi tidak boleh melebihi 255 aksara.');

        foreach ($response->json('errors') as $field => $messages) {
            foreach ($messages as $message) {
                $this->assertDoesNotMatchRegularExpression($englishDefaultShape, $message);
            }
        }
    }

    /**
     * Finding B (MINOR, cleanup round): idempotency_key reaches
     * shortCircuitIfReplay()'s where() clause before rules() runs — the same
     * class of bug the locked_source_id is_scalar guard fixed, left
     * unguarded for the field that round's refactor relocated ahead of
     * validation. Probed pre-fix: ['realkey', 'x'] flattened to 'realkey' in
     * the query (Builder::flattenValue()) and returned a 201 replay of the
     * existing 'realkey' row, where validation should have produced an
     * honest 422 on the `string` rule instead.
     */
    public function test_idempotency_key_as_an_array_returns_422_not_a_replay(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $existing = $this->postJson('/api/mobile/culaan', $this->payload(['idempotency_key' => 'realkey']))
            ->assertStatus(201);
        $existingId = $existing->json('culaan.id');

        $response = $this->postJson('/api/mobile/culaan', $this->payload([
            'idempotency_key' => ['realkey', 'x'],
            'no_ic' => '811119992222',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.idempotency_key.0', 'Kunci idempotency tidak sah.');

        $this->assertNotSame($existingId, $response->json('culaan.id'));
        $this->assertDatabaseMissing('hasil_culaan', ['no_ic' => '811119992222']);
        // Only the original row exists — the array did not create a second
        // one, and it did not get treated as a replay of the first either.
        $this->assertSame(1, HasilCulaan::where('idempotency_key', 'realkey')->count());
    }

    /**
     * BLOCKER 1 (final-review): the controller used to call
     * VoterSyncService::syncFromHasilCulaan($record->fresh()) after create().
     * fresh() re-reads every column from the DB, so ALL of
     * VoterSyncService::SHARED_FIELDS are present in getAttributes() — most
     * of them NULL on a minimal mobile submission — and extract()'s
     * array_key_exists() check then copies every one of those NULLs onto
     * the existing data_pengundi row for that IC, silently wiping fields
     * the mobile submission never sent. is_deceased resetting to false is
     * the sharpest edge: it resurrects a voter the office had marked
     * deceased. The web create path (ReportsController.php:460) passes
     * $record, not fresh(), and does not have this bug.
     *
     * Fix: drop ->fresh() so the mobile create path copies only the fields
     * this specific submission actually set, exactly like the web path.
     *
     * Mutation-check: restoring ->fresh() in MobileCulaanController::store()
     * makes this test fail (every asserted field reverts to null/false).
     *
     * voter_color is asserted here too (added alongside the fix for the
     * regression BLOCKER 2's own fix reintroduced — see the class docblock
     * above test_a_minimal_submission_does_not_overwrite_an_existing_voter_color_with_kelabu()
     * for the full story). This was the one seeded field the original
     * version of this test did not assert, which is exactly why that
     * regression shipped without failing any test.
     */
    public function test_a_minimal_mobile_submission_does_not_wipe_an_existing_enriched_voter_record(): void
    {
        Sanctum::actingAs($this->makeUser());

        DataPengundi::factory()->create([
            'no_ic' => '800101015555',
            'nama' => 'Ahmad bin Ali (Sedia Ada)',
            'kadun' => 'BULOH KASAP',
            'parlimen' => 'SEGAMAT',
            'voter_color' => 'hitam',
            'status_pengundi' => 'Aktif',
            'keahlian_parti' => 'UMNO',
            'kecenderungan_politik' => 'BN',
            'mpkk' => 'MPKK SATU',
            'lokaliti' => 'LOKALITI SATU',
            'nota' => 'Nota penting',
            'is_deceased' => true,
        ]);

        // A minimal Culaan submission for the SAME IC from the phone — no
        // party/tendency/mpkk/lokaliti/nota/is_deceased fields sent at all.
        $this->postJson('/api/mobile/culaan', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $voter = DataPengundi::where('no_ic', '800101015555')->firstOrFail();

        $this->assertSame('Aktif', $voter->status_pengundi, 'status_pengundi must survive a minimal submission.');
        $this->assertSame('UMNO', $voter->keahlian_parti, 'keahlian_parti must survive a minimal submission.');
        $this->assertSame('BN', $voter->kecenderungan_politik, 'kecenderungan_politik must survive a minimal submission.');
        $this->assertSame('MPKK SATU', $voter->mpkk, 'mpkk must survive a minimal submission.');
        $this->assertSame('LOKALITI SATU', $voter->lokaliti, 'lokaliti must survive a minimal submission.');
        $this->assertSame('Nota penting', $voter->nota, 'nota must survive a minimal submission.');
        $this->assertTrue($voter->is_deceased, 'is_deceased must NOT be silently reset to false — that resurrects a deceased voter.');
        $this->assertSame('hitam', $voter->voter_color, 'voter_color must survive a minimal submission, not be overwritten with a definite "kelabu" the submission never claimed.');
    }

    /**
     * Regression test for the bug B2's OWN fix reintroduced: computing
     * voter_color unconditionally before create() means it is always
     * present in getAttributes(), so VoterSyncService::extract()'s
     * array_key_exists() gate (see the ->fresh() docblock above) always
     * propagates it — even though this specific submission never mentioned
     * politics. VoterColorService::determine(null, null) returns a definite
     * 'kelabu', not "no data", so an omitted political field was silently
     * downgrading a known BN voter ('hitam') to "undecided" — exactly the
     * "unknown is not zero" violation CLAUDE.md warns about, just reached
     * through a different field than usual.
     *
     * Fix: only set $payload['voter_color'] when at least one of
     * keahlian_parti/kecenderungan_politik is actually present in this
     * submission. When both are absent, the key is left unset entirely, so
     * it never enters getAttributes() and never propagates.
     *
     * Mutation-check: making the injection unconditional again (i.e.
     * reverting to always computing voter_color) makes this test fail —
     * $voter->voter_color reverts from 'hitam' to 'kelabu'.
     */
    public function test_a_minimal_submission_does_not_overwrite_an_existing_voter_color_with_kelabu(): void
    {
        Sanctum::actingAs($this->makeUser());

        DataPengundi::factory()->create([
            'no_ic' => '800101015555',
            'kadun' => 'BULOH KASAP',
            'parlimen' => 'SEGAMAT',
            'voter_color' => 'hitam',
            'keahlian_parti' => 'UMNO',
            'kecenderungan_politik' => 'BN',
        ]);

        // No keahlian_parti/kecenderungan_politik sent at all.
        $this->postJson('/api/mobile/culaan', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $voter = DataPengundi::where('no_ic', '800101015555')->firstOrFail();
        $this->assertSame('hitam', $voter->voter_color, 'A minimal submission with no political signal must not overwrite a known voter_color with a definite "kelabu".');

        // The new hasil_culaan row itself should honestly record NO signal
        // (null), rather than a fabricated 'kelabu' classification for a
        // submission that never mentioned politics.
        $this->assertDatabaseHas('hasil_culaan', [
            'no_ic' => '800101015555',
            'voter_color' => null,
        ]);
    }

    /**
     * BLOCKER 2 (final-review): every web Culaan write computes voter_color
     * via VoterColorService::determine() (ReportsController.php:455, :666,
     * :1116) before HasilCulaan::create(). MobileCulaanController::store()
     * used to skip this entirely, so a mobile POST carrying an unambiguous
     * combination (PKR / PH -> 'putih') stored voter_color = NULL on both
     * hasil_culaan and data_pengundi — silently misclassifying the voter as
     * 'kelabu'/'belum_dicula' across the election analytics and Keanggotaan
     * dashboards (Pilihanraya/ElectionAnalyticsService.php COALESCE(NULLIF(...),
     * 'belum_dicula') and KeanggotaanController.php's whereNull bucket).
     *
     * Fix: mirror ReportsController.php:455 exactly before create().
     */
    public function test_voter_color_is_computed_on_the_mobile_path_matching_the_web(): void
    {
        Sanctum::actingAs($this->makeUser());

        $expected = \App\Services\VoterColorService::determine('PKR', 'PH');
        $this->assertSame('putih', $expected, 'Sanity: PKR/PH is the unambiguous putih case the finding describes.');

        $response = $this->postJson('/api/mobile/culaan', $this->payload([
            'keahlian_parti' => 'PKR',
            'kecenderungan_politik' => 'PH',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('hasil_culaan', [
            'no_ic' => '800101015555',
            'voter_color' => 'putih',
        ]);
        $this->assertDatabaseHas('data_pengundi', [
            'no_ic' => '800101015555',
            'voter_color' => 'putih',
        ]);
    }

    /**
     * BLOCKER 2 continued, revised after the follow-up fix below: this test
     * originally asserted that an absent-political-fields submission stored
     * a computed 'kelabu' on hasil_culaan, on the theory that
     * VoterColorService::determine(null, null) === 'kelabu' is the "correct"
     * classification for no signal. That theory was itself the bug —
     * 'kelabu' is a DEFINITE claim ("this voter looks undecided"), not "we
     * don't know". Because voter_color was set unconditionally, it always
     * entered getAttributes() and always propagated via
     * VoterSyncService::extract(), so a minimal submission for an IC with a
     * KNOWN voter_color silently overwrote it with 'kelabu' — see
     * test_a_minimal_submission_does_not_overwrite_an_existing_voter_color_with_kelabu()
     * above for the regression this caused and its fix.
     *
     * With that fix, a submission carrying no political signal at all does
     * not set voter_color on the payload, so the new hasil_culaan row gets
     * NULL — honestly recording "this submission said nothing about
     * politics" rather than fabricating a classification. determine(null,
     * null) is still 'kelabu' as a function (asserted below for sanity),
     * but the controller no longer calls it in this case.
     */
    public function test_voter_color_for_absent_political_fields_matches_determine_nulls_behaviour(): void
    {
        Sanctum::actingAs($this->makeUser());

        $expected = \App\Services\VoterColorService::determine(null, null);
        $this->assertSame('kelabu', $expected, "Sanity: determine()'s actual behaviour for absent inputs — unchanged by the controller fix, since determine() itself is not touched.");

        $this->postJson('/api/mobile/culaan', $this->payload())->assertStatus(201);

        $this->assertDatabaseHas('hasil_culaan', [
            'no_ic' => '800101015555',
            'voter_color' => null,
        ]);
    }
}
