<?php
// tests/Feature/Borang14PolymorphicEndpointsTest.php
//
// Task 12 finding 2: data(), saveParties(), saveVote() and reset() were only
// just migrated from DUN-only (kadun_id) to polymorphic (kawasan_type +
// kawasan_id), with zero test coverage. This is exactly the gap that let an
// earlier task ship a DUN-only breakage through a passing review (the
// reviewer only checked Borang14Form queries for kadun_id — request-field
// usage slipped through). These tests exercise every migrated endpoint
// through BOTH kawasan types, plus the wrong-table-id rejection that proves
// the exists-table validation is actually wired per kawasan_type.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14PolymorphicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function seedGeography(): array
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);

        return [$negeri, $bandar, $kadun];
    }

    /**
     * Bandar only, deliberately WITHOUT creating any kadun — so bandar->id is
     * guaranteed absent from the kadun table (unlike seedGeography(), whose
     * kadun happens to share the same auto-increment id as its own bandar).
     */
    private function seedBandarOnly(): Bandar
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);

        return Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
    }

    /**
     * Setiap laluan TULIS Borang 14 kini menegaskan SeatScope, jadi pengguna
     * ujian mesti DILULUSKAN dan (bagi laluan yang benar-benar menulis)
     * memiliki kerusi sasaran melalui bandar_id. Laluan yang hanya menguji
     * penolakan pengesahan (422) tidak memerlukan kerusi — pengesahan berjalan
     * SEBELUM penegasan kerusi.
     */
    private function user(string $phone = '0123450010', ?int $bandarId = null): User
    {
        // UserFactory has a pre-existing bug: it does not set the NOT NULL
        // `telephone` column. Work around it locally rather than fixing the
        // shared factory (per earlier tests in this suite).
        return User::factory()->create([
            'role' => 'admin', 'telephone' => $phone,
            'status' => 'approved', 'bandar_id' => $bandarId,
        ]);
    }

    // ---- data() -----------------------------------------------------

    public function test_data_returns_structure_for_a_parlimen_type_form(): void
    {
        [, $bandar] = $this->seedGeography();

        $form = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'A'], ['nama' => 'B']],
                'rows' => [[
                    'pusat' => 'PM Ujian', 'dm' => 'DM Ujian', 'saluran' => '1',
                    'a' => 10, 'undi' => [6, 4], 'jumlah_undian' => 10, 'ditolak' => 0, 'tidak_dimasukkan' => 0,
                ]],
            ],
        ]);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'), 'Parlimen-type form with a scoresheet structure must produce reference data, not a "no data" state.');
        $this->assertSame($form->id, $res->json('form.id'));
        $this->assertNotEmpty($res->json('reference.daerah_mengundi'), 'Structure-derived reference must include the daerah mengundi read off the sheet.');
    }

    public function test_data_still_works_for_a_dun_type_form(): void
    {
        [, , $kadun] = $this->seedGeography();

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'A'], ['nama' => 'B']],
                'rows' => [[
                    'pusat' => 'PM Ujian', 'dm' => 'DM Ujian', 'saluran' => '1',
                    'a' => 10, 'undi' => [6, 4], 'jumlah_undian' => 10, 'ditolak' => 0, 'tidak_dimasukkan' => 0,
                ]],
            ],
        ]);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'));
        $this->assertSame($form->id, $res->json('form.id'));
    }

    public function test_data_rejects_a_bandar_id_passed_with_kawasan_type_dun(): void
    {
        $bandar = $this->seedBandarOnly();
        $this->assertDatabaseMissing('kadun', ['id' => $bandar->id]);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $bandar->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
        ]));

        $res->assertStatus(422);
    }

    // ---- saveVote() ---------------------------------------------------

    public function test_save_vote_persists_slot_90_ditolak_and_slot_91_tidak_dimasukkan(): void
    {
        [, $bandar, $kadun] = $this->seedGeography();
        $user = $this->user(bandarId: $bandar->id);

        $this->actingAs($user)->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
            'penjuru' => 2, 'contest' => 'dun', 'pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 90, 'undi' => 7,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->actingAs($user)->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
            'penjuru' => 2, 'contest' => 'dun', 'pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 91, 'undi' => 3,
        ])->assertOk()->assertJson(['ok' => true]);

        $form = Borang14Form::forKawasan('dun', $kadun->id)->where('jenis_pr', 'prn')->where('tahun', 2023)->first();
        $this->assertNotNull($form);

        $this->assertDatabaseHas('borang14_votes', [
            'borang14_form_id' => $form->id, 'pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 90, 'undi' => 7,
        ]);
        $this->assertDatabaseHas('borang14_votes', [
            'borang14_form_id' => $form->id, 'pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 91, 'undi' => 3,
        ]);
    }

    public function test_save_vote_works_through_the_parlimen_polymorphic_key(): void
    {
        [, $bandar] = $this->seedGeography();

        $this->actingAs($this->user(bandarId: $bandar->id))->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
            'penjuru' => 2, 'contest' => 'parlimen', 'pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 1, 'undi' => 42,
        ])->assertOk()->assertJson(['ok' => true]);

        $form = Borang14Form::forKawasan('parlimen', $bandar->id)->where('jenis_pr', 'prn')->where('tahun', 2023)->first();
        $this->assertNotNull($form, 'saveVote() must create the parlimen-type form via firstOrCreate on the polymorphic key.');
        $this->assertDatabaseHas('borang14_votes', [
            'borang14_form_id' => $form->id, 'pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 1, 'undi' => 42,
        ]);
    }

    public function test_save_vote_rejects_a_bandar_id_passed_with_kawasan_type_dun(): void
    {
        $bandar = $this->seedBandarOnly();
        $this->assertDatabaseMissing('kadun', ['id' => $bandar->id]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $bandar->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
            'penjuru' => 2, 'contest' => 'dun', 'pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 1, 'undi' => 42,
        ])->assertStatus(422);
    }

    // ---- saveParties() -------------------------------------------------

    public function test_save_parties_works_through_the_dun_polymorphic_key(): void
    {
        [, $bandar, $kadun] = $this->seedGeography();

        // saveParties() menegaskan SeatScope: pengguna mesti DILULUSKAN dan
        // memiliki kerusi itu — lihat ujian kebenaran dalam
        // Borang14SkopPertandinganTest dan Borang14KerusiAuthzTest.
        $pemilik = $this->user(bandarId: $bandar->id);

        $res = $this->actingAs($pemilik)->postJson(route('pilihanraya.borang-14.parties'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
            'penjuru' => 2,
            'parties' => [
                ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'PARTI A'],
                ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PARTI B'],
            ],
        ]);

        $res->assertOk()->assertJson(['ok' => true]);
        $form = Borang14Form::findOrFail($res->json('form_id'));
        $this->assertSame('dun', $form->kawasan_type);
        $this->assertSame($kadun->id, $form->kawasan_id);
        $this->assertSame('PARTI A', $form->parties[0]['nama']);
    }

    public function test_save_parties_rejects_a_bandar_id_passed_with_kawasan_type_dun(): void
    {
        $bandar = $this->seedBandarOnly();
        $this->assertDatabaseMissing('kadun', ['id' => $bandar->id]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.parties'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $bandar->id, 'jenis_pr' => 'prn', 'tahun' => 2023,
            'penjuru' => 2, 'parties' => [],
        ])->assertStatus(422);
    }

    // ---- reset() -------------------------------------------------------

    public function test_reset_clears_votes_through_the_polymorphic_key(): void
    {
        [, , $kadun] = $this->seedGeography();

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
        ]);
        $form->votes()->create(['pusat' => '', 'saluran' => '1', 'slot' => 1, 'undi' => 5]);
        $this->assertSame(1, $form->votes()->count());

        $this->actingAs($this->user(bandarId: $kadun->bandar_id))->postJson(route('pilihanraya.borang-14.reset'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2023, 'contest' => 'dun',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(0, $form->votes()->count());
    }

    public function test_reset_works_through_the_parlimen_polymorphic_key(): void
    {
        [, $bandar] = $this->seedGeography();

        $form = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
        ]);
        $form->votes()->create(['pusat' => '', 'saluran' => '1', 'slot' => 1, 'undi' => 5]);

        $this->actingAs($this->user(bandarId: $bandar->id))->postJson(route('pilihanraya.borang-14.reset'), [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id, 'jenis_pr' => 'prn', 'tahun' => 2023, 'contest' => 'parlimen',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(0, $form->votes()->count());
    }

    public function test_reset_rejects_a_bandar_id_passed_with_kawasan_type_dun(): void
    {
        $bandar = $this->seedBandarOnly();
        $this->assertDatabaseMissing('kadun', ['id' => $bandar->id]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.reset'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $bandar->id, 'jenis_pr' => 'prn', 'tahun' => 2023, 'contest' => 'dun',
        ])->assertStatus(422);
    }
}
