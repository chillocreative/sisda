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
     * failure AFTER the hasil_culaan row is physically inserted (via the
     * model's 'created' event, which fires synchronously inside create()
     * and therefore still inside the open transaction) and prove BOTH the
     * hasil_culaan row and the data_pengundi row VoterSyncService would have
     * fanned out to are rolled back — not just the fan-out.
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
}
