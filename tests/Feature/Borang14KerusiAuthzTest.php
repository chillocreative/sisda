<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14DeletedForm;
use App\Models\Borang14Form;
use App\Models\Borang14Snapshot;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kebenaran KERUSI bagi lima laluan TULIS Borang 14: saveVote, reset, publish,
 * revert dan hapus.
 *
 * Gate laluan ['auth','admin'] menyemak PERANAN sahaja — EnsureAdmin menerima
 * setiap admin tanpa satu pun semakan kerusi. Tanpa penegasan dalam pengawal,
 * seorang admin yang berskop kepada satu Parlimen boleh mengunci undi,
 * MEMADAM undi, menerbitkan, memulihkan snapshot dan memadam borang mana-mana
 * kerusi di seluruh Malaysia. Ini keluarga yang sama seperti empat IDOR yang
 * dihotfix ke produksi pada Julai 2026.
 *
 * Setiap laluan diuji tiga kali: pemilik BUKAN (403 + data TIDAK berubah),
 * pemilik SAH (masih berjaya), dan super_admin (tidak terjejas).
 */
class Borang14KerusiAuthzTest extends TestCase
{
    use RefreshDatabase;

    /** Kerusi sasaran — JEMPOL / GEMAS. */
    private Bandar $parlimen;

    private Kadun $dun;

    /** Kerusi penyerang — SEREMBAN, Parlimen yang BERBEZA sepenuhnya. */
    private Bandar $parlimenLain;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);

        $this->parlimenLain = Bandar::create(['nama' => 'SEREMBAN', 'kod_parlimen' => 'P128', 'negeri_id' => $negeri->id]);
        Kadun::create(['nama' => 'RAHANG', 'kod_dun' => 'N26', 'bandar_id' => $this->parlimenLain->id]);
    }

    // ---- Pembantu -------------------------------------------------------

    private function pengguna(string $role, ?int $bandarId, string $telefon): User
    {
        return User::create([
            'name' => "Ujian {$telefon}",
            'email' => "b14authz{$telefon}@example.test",
            'telephone' => $telefon,
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => 'approved',
            'bandar_id' => $bandarId,
        ]);
    }

    /** Admin SEREMBAN — tiada apa-apa kena-mengena dengan GEMAS/JEMPOL. */
    private function penyerang(): User
    {
        return $this->pengguna('admin', $this->parlimenLain->id, '0123450101');
    }

    /** Admin JEMPOL — memiliki GEMAS menurut binaan (DUN di bawah Parlimennya). */
    private function pemilik(): User
    {
        return $this->pengguna('admin', $this->parlimen->id, '0123450102');
    }

    /** super_admin TANPA bandar_id — tidak "memiliki" apa-apa, boleh semua. */
    private function superAdmin(): User
    {
        return $this->pengguna('super_admin', null, '0123450103');
    }

    /** Admin dengan lajur bandar_id NULL — mesti tidak padan dengan apa-apa. */
    private function adminTanpaKerusi(): User
    {
        return $this->pengguna('admin', null, '0123450104');
    }

    private function borangDun(string $status = 'draft'): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'status' => $status,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PH']],
            'structure' => ['rows' => [['dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran' => '1']]],
        ]);
    }

    private function undi(Borang14Form $form, int $slot, int $undi): Borang14Vote
    {
        return Borang14Vote::create([
            'borang14_form_id' => $form->id, 'contest' => Borang14Vote::CONTEST_DUN,
            'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
        ]);
    }

    private function muatanVote(array $ubah = []): array
    {
        return array_merge([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'contest' => Borang14Vote::CONTEST_DUN,
            'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 500,
        ], $ubah);
    }

    // ---- 1. saveVote ----------------------------------------------------

    public function test_save_vote_is_refused_for_an_admin_of_another_parlimen(): void
    {
        $this->actingAs($this->penyerang())
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanVote())
            ->assertStatus(403);

        // Bukan sekadar status: firstOrCreate() dalam saveVote() MENCIPTA borang
        // pada kerusi yang dinamakan input, jadi kegagalan penegasan yang
        // diletakkan terlalu lewat akan meninggalkan borang hantu di sini.
        $this->assertSame(0, Borang14Form::count(), 'Tiada borang boleh tercipta pada kerusi orang lain.');
        $this->assertSame(0, Borang14Vote::count());
    }

    public function test_save_vote_still_works_for_the_admin_who_owns_the_seat(): void
    {
        $this->actingAs($this->pemilik())
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanVote())
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(500, (int) Borang14Vote::sole()->undi);
    }

    public function test_save_vote_still_works_for_super_admin_on_a_seat_it_does_not_own(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanVote())
            ->assertOk();

        $this->assertSame(500, (int) Borang14Vote::sole()->undi);
    }

    public function test_save_vote_is_refused_for_an_admin_without_a_bandar(): void
    {
        $this->actingAs($this->adminTanpaKerusi())
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanVote())
            ->assertStatus(403);

        $this->assertSame(0, Borang14Form::count());
    }

    // ---- 2. reset -------------------------------------------------------

    private function muatanReset(): array
    {
        return [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'contest' => Borang14Vote::CONTEST_DUN,
        ];
    }

    public function test_reset_is_refused_for_an_admin_of_another_parlimen(): void
    {
        $form = $this->borangDun();
        $this->undi($form, 1, 224);
        $this->undi($form, 2, 118);

        $this->actingAs($this->penyerang())
            ->postJson(route('pilihanraya.borang-14.reset'), $this->muatanReset())
            ->assertStatus(403);

        // reset() memadam TANPA arkib — undi ini tiada snapshot untuk pulih.
        $this->assertSame(2, $form->votes()->count(), 'Undi mesti kekal utuh selepas penolakan.');
        $this->assertSame(342, (int) $form->votes()->sum('undi'));
    }

    public function test_reset_still_works_for_the_admin_who_owns_the_seat(): void
    {
        $form = $this->borangDun();
        $this->undi($form, 1, 224);

        $this->actingAs($this->pemilik())
            ->postJson(route('pilihanraya.borang-14.reset'), $this->muatanReset())
            ->assertOk();

        $this->assertSame(0, $form->votes()->count());
    }

    public function test_reset_still_works_for_super_admin_on_a_seat_it_does_not_own(): void
    {
        $form = $this->borangDun();
        $this->undi($form, 1, 224);

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.reset'), $this->muatanReset())
            ->assertOk();

        $this->assertSame(0, $form->votes()->count());
    }

    public function test_reset_is_refused_for_an_admin_without_a_bandar(): void
    {
        $form = $this->borangDun();
        $this->undi($form, 1, 224);

        $this->actingAs($this->adminTanpaKerusi())
            ->postJson(route('pilihanraya.borang-14.reset'), $this->muatanReset())
            ->assertStatus(403);

        $this->assertSame(1, $form->votes()->count());
    }

    // ---- 3. publish -----------------------------------------------------

    public function test_publish_is_refused_for_an_admin_of_another_parlimen(): void
    {
        $form = $this->borangDun();

        $this->actingAs($this->penyerang())
            ->postJson(route('pilihanraya.borang-14.publish'), ['form_id' => $form->id])
            ->assertStatus(403);

        $form->refresh();
        $this->assertSame('draft', $form->status, 'Borang mesti kekal draf.');
        $this->assertNull($form->published_at);
    }

    public function test_publish_still_works_for_the_admin_who_owns_the_seat(): void
    {
        $form = $this->borangDun();

        $this->actingAs($this->pemilik())
            ->postJson(route('pilihanraya.borang-14.publish'), ['form_id' => $form->id])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('published', $form->fresh()->status);
    }

    public function test_publish_still_works_for_super_admin_on_a_seat_it_does_not_own(): void
    {
        $form = $this->borangDun();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.publish'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertSame('published', $form->fresh()->status);
    }

    public function test_publish_is_refused_for_an_admin_without_a_bandar(): void
    {
        $form = $this->borangDun();

        $this->actingAs($this->adminTanpaKerusi())
            ->postJson(route('pilihanraya.borang-14.publish'), ['form_id' => $form->id])
            ->assertStatus(403);

        $this->assertSame('draft', $form->fresh()->status);
    }

    // ---- 4. revert ------------------------------------------------------

    /** Borang dengan undi SEMASA dan satu snapshot yang BERBEZA daripadanya. */
    private function borangDenganSnapshot(): Borang14Form
    {
        $form = $this->borangDun();
        $this->undi($form, 1, 224);

        Borang14Snapshot::create([
            'borang14_form_id' => $form->id,
            'structure' => $form->structure,
            'votes' => [['contest' => Borang14Vote::CONTEST_DUN, 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 1]],
            'parties' => $form->parties,
            'reason' => 'before_structure_edit',
            'created_by' => null,
        ]);

        return $form;
    }

    public function test_revert_is_refused_for_an_admin_of_another_parlimen(): void
    {
        $form = $this->borangDenganSnapshot();

        $this->actingAs($this->penyerang())
            ->postJson(route('pilihanraya.borang-14.revert'), ['form_id' => $form->id])
            ->assertStatus(403);

        // revert() memadam SETIAP undi lalu menulis semula daripada snapshot;
        // keadaan sebelum-revert tidak dirakam di mana-mana. Undi semasa (224)
        // mesti kekal, bukan digantikan nilai snapshot (1).
        $this->assertSame(1, $form->votes()->count());
        $this->assertSame(224, (int) $form->votes()->sole()->undi);
    }

    public function test_revert_still_works_for_the_admin_who_owns_the_seat(): void
    {
        $form = $this->borangDenganSnapshot();

        $this->actingAs($this->pemilik())
            ->postJson(route('pilihanraya.borang-14.revert'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertSame(1, (int) $form->votes()->sole()->undi);
    }

    public function test_revert_still_works_for_super_admin_on_a_seat_it_does_not_own(): void
    {
        $form = $this->borangDenganSnapshot();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.revert'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertSame(1, (int) $form->votes()->sole()->undi);
    }

    public function test_revert_is_refused_for_an_admin_without_a_bandar(): void
    {
        $form = $this->borangDenganSnapshot();

        $this->actingAs($this->adminTanpaKerusi())
            ->postJson(route('pilihanraya.borang-14.revert'), ['form_id' => $form->id])
            ->assertStatus(403);

        $this->assertSame(224, (int) $form->votes()->sole()->undi);
    }

    // ---- 5. hapus -------------------------------------------------------

    public function test_hapus_is_refused_for_an_admin_of_another_parlimen(): void
    {
        $form = $this->borangDun();
        $this->undi($form, 1, 224);

        $this->actingAs($this->penyerang())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertStatus(403);

        $this->assertNotNull(Borang14Form::find($form->id), 'Borang mesti masih wujud.');
        $this->assertSame(1, $form->votes()->count());
        $this->assertSame(0, Borang14DeletedForm::count());
    }

    public function test_hapus_still_works_for_the_admin_who_owns_the_seat(): void
    {
        $form = $this->borangDun();
        $this->undi($form, 1, 224);

        $this->actingAs($this->pemilik())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertNull(Borang14Form::find($form->id));
        $this->assertSame(1, Borang14DeletedForm::count());
    }

    public function test_hapus_still_works_for_super_admin_on_a_seat_it_does_not_own(): void
    {
        $form = $this->borangDun('published');

        $this->actingAs($this->superAdmin())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertNull(Borang14Form::find($form->id));
    }

    public function test_hapus_is_refused_for_an_admin_without_a_bandar(): void
    {
        $form = $this->borangDun();

        $this->actingAs($this->adminTanpaKerusi())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertStatus(403);

        $this->assertNotNull(Borang14Form::find($form->id));
    }

    // ---- 6. Pengguna BELUM DILULUSKAN -----------------------------------

    /**
     * Akaun baharu ialah `pending` sehingga diluluskan. EnsureAdmin tidak
     * menyemaknya langsung — hanya peranan. SeatScope menolaknya.
     */
    public function test_an_unapproved_admin_of_the_right_seat_is_still_denied(): void
    {
        $belumLulus = $this->pemilik();
        $belumLulus->forceFill(['status' => 'pending'])->save();

        $this->actingAs($belumLulus)
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanVote())
            ->assertStatus(403);

        $this->assertSame(0, Borang14Form::count());
    }

    // ---- 7. Takrifan PRU yang DIKONGSI ----------------------------------

    /**
     * Borang TAKRIFAN Parlimen + satu borang DUN yang memautinya dan sudah
     * mempunyai undi PRU. Memadam takrifan akan menullkan pautan itu (FK
     * nullOnDelete), lalu roll-up berhenti mengira undi tersebut.
     */
    private function takrifanDenganDunBerundi(): array
    {
        $takrifan = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'structure' => null,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PN'], ['slot' => 3, 'nama' => 'PH']],
        ]);

        $borangDun = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $takrifan->id,
        ]);
        Borang14Vote::create([
            'borang14_form_id' => $borangDun->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
            'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 5000,
        ]);

        return [$takrifan, $borangDun];
    }

    public function test_a_shared_pru_definition_cannot_be_deleted_while_a_linked_dun_holds_votes(): void
    {
        [$takrifan, $borangDun] = $this->takrifanDenganDunBerundi();

        $res = $this->actingAs($this->pemilik())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $takrifan->id])
            ->assertStatus(422);

        $this->assertStringContainsString('GEMAS', (string) $res->json('message'));

        $this->assertNotNull(Borang14Form::find($takrifan->id));
        $this->assertSame(0, Borang14DeletedForm::count());
        // Pautan DUN mesti utuh — itulah yang padaman akan nullkan secara senyap.
        $this->assertSame((int) $takrifan->id, (int) $borangDun->fresh()->borang14_form_parlimen_id);
        $this->assertSame(5000, (int) $borangDun->votesFor(Borang14Vote::CONTEST_PARLIMEN)->sum('undi'));
    }

    /** Peraturan yang sama bagi super_admin — ini integriti, bukan kebenaran. */
    public function test_the_shared_definition_guard_applies_to_super_admin_too(): void
    {
        [$takrifan] = $this->takrifanDenganDunBerundi();

        $this->actingAs($this->superAdmin())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $takrifan->id])
            ->assertStatus(422);

        $this->assertNotNull(Borang14Form::find($takrifan->id));
    }

    /** Tanpa undi PRU pada borang DUN terpaut, padaman kekal dibenarkan. */
    public function test_a_shared_definition_without_linked_votes_can_still_be_deleted(): void
    {
        [$takrifan, $borangDun] = $this->takrifanDenganDunBerundi();
        $borangDun->votes()->delete();

        $this->actingAs($this->pemilik())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $takrifan->id])
            ->assertOk();

        $this->assertNull(Borang14Form::find($takrifan->id));
    }
}
