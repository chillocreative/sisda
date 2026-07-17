<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCulaanReadTest extends TestCase
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

    public function test_options_requires_authentication(): void
    {
        $this->getJson('/api/mobile/culaan/options')->assertStatus(401);
    }

    public function test_options_returns_every_taxonomy_the_form_needs(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/mobile/culaan/options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['options' => [
                'pekerjaan',
                'jenis_pekerjaan',
                'jenis_sumbangan',
                'tujuan_sumbangan',
                'bantuan_lain',
                'pemilik_rumah',
            ]]);
    }

    public function test_pekerjaan_options_match_the_servers_validation_rule(): void
    {
        Sanctum::actingAs($this->makeUser());

        // If these drift from StoreMobileCulaanRequest's in: rule, the app
        // offers choices the server will reject with a 422 the user cannot fix.
        $this->getJson('/api/mobile/culaan/options')
            ->assertOk()
            ->assertJsonPath('options.pekerjaan', ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja']);
    }

    public function test_mine_returns_only_records_submitted_by_the_caller(): void
    {
        $me = $this->makeUser();
        $other = $this->makeUser();
        Sanctum::actingAs($me);

        HasilCulaan::factory()->create(['nama' => 'Rekod Saya', 'submitted_by' => $me->id]);
        HasilCulaan::factory()->create(['nama' => 'Rekod Orang Lain', 'submitted_by' => $other->id]);

        $this->getJson('/api/mobile/culaan/mine')
            ->assertOk()
            ->assertJsonCount(1, 'culaan')
            ->assertJsonPath('culaan.0.nama', 'Rekod Saya');
    }

    public function test_mine_is_newest_first(): void
    {
        $me = $this->makeUser();
        Sanctum::actingAs($me);

        $old = HasilCulaan::factory()->create(['nama' => 'Lama', 'submitted_by' => $me->id]);
        // created_at is not in HasilCulaan::$fillable, so a plain update()
        // silently no-ops on it (mass-assignment guard drops the attribute).
        // forceFill() bypasses that guard — without it both rows share the
        // same created_at second and the ordering assertion below would
        // pass or fail depending on insertion order rather than on the
        // controller actually sorting by created_at.
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        HasilCulaan::factory()->create(['nama' => 'Baru', 'submitted_by' => $me->id]);

        $this->getJson('/api/mobile/culaan/mine')
            ->assertOk()
            ->assertJsonPath('culaan.0.nama', 'Baru');
    }

    /**
     * mine/ must never leak the submitting staff account's PII. Task 4
     * shipped exactly this bug (email/telephone/role/last_login_ip via an
     * unscoped submittedBy eager-load) — this test locks the fix in for
     * this endpoint too. A HasilCulaan whose submitter is a plain 'user'
     * is locked, so sensitive voter fields must come back masked, but the
     * submitted_by projection must stay to {id, name} only.
     */
    public function test_mine_does_not_leak_submitter_account_details(): void
    {
        $me = $this->makeUser();
        Sanctum::actingAs($me);

        HasilCulaan::factory()->create(['submitted_by' => $me->id]);

        $response = $this->getJson('/api/mobile/culaan/mine')->assertOk();

        $row = $response->json('culaan.0');

        $this->assertSame(['id' => $me->id, 'name' => $me->name], $row['submitted_by']);
        $this->assertArrayNotHasKey('email', $row['submitted_by']);
        $this->assertArrayNotHasKey('telephone', $row['submitted_by']);

        // Own record submitted by a plain 'user' role is locked; sensitive
        // fields must be masked, not the real value.
        $this->assertSame('****', $row['no_ic']);
        $this->assertSame('****', $row['alamat']);
    }
}
