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
}
