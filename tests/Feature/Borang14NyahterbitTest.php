<?php
// tests/Feature/Borang14NyahterbitTest.php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nyahterbit (unpublish) — kembalikan borang DITERBITKAN kepada DRAF.
 *
 * SENGAJA lebih ketat daripada publish(): publish() dipagari kerusi (mana-mana
 * admin pemilik kerusi boleh menerbitkan borangnya sendiri), tetapi nyahterbit
 * dipagari PERANAN — super_admin sahaja. Sebabnya: status 'published' ialah
 * satu-satunya perkara yang menyekat suntingan nama calon dan struktur saluran
 * (bolehSuntingStruktur()), jadi menyahterbitkan membuka semula setiap laluan
 * tulis pada keputusan yang sudah dilihat orang ramai.
 *
 * Ujian ini juga mengunci bahawa nyahterbit menyentuh STATUS SAHAJA — undi,
 * struktur dan pemetaan parti kekal utuh. Nyahterbit yang memadam undi ialah
 * kehilangan data yang tidak boleh dipulihkan.
 */
class Borang14NyahterbitTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $parlimen;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);
    }

    private function pengguna(string $role, ?int $bandarId, string $telefon): User
    {
        return User::create([
            'name' => "Ujian {$telefon}",
            'email' => "b14nyahterbit{$telefon}@example.test",
            'telephone' => $telefon,
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => 'approved',
            'bandar_id' => $bandarId,
        ]);
    }

    /** Admin JEMPOL — MEMILIKI kerusi GEMAS, dan tetap tidak boleh nyahterbit. */
    private function pemilik(): User
    {
        return $this->pengguna('admin', $this->parlimen->id, '0123460101');
    }

    private function superAdmin(): User
    {
        return $this->pengguna('super_admin', null, '0123460102');
    }

    private function borang(string $status = 'published'): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PH']],
            'structure' => ['rows' => [['dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran' => '1']]],
        ]);
    }

    public function test_super_admin_boleh_nyahterbit_borang_diterbitkan(): void
    {
        $form = $this->borang();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.unpublish'), ['form_id' => $form->id])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'draft']);

        $form->refresh();
        $this->assertSame('draft', $form->status);
        // published_at bermaksud "bila borang ini DITERBITKAN sekarang" —
        // membiarkannya akan membuatkan senarai memapar tarikh terbitan bagi
        // rekod yang bukan lagi terbitan.
        $this->assertNull($form->published_at);
    }

    /**
     * Pembeza utama daripada publish(): admin PEMILIK kerusi ditolak.
     * Kalau ujian ini gagal, nyahterbit telah tersalin semakan kerusi
     * publish() dan bukan semakan peranannya sendiri.
     */
    public function test_admin_pemilik_kerusi_tetap_ditolak(): void
    {
        $form = $this->borang();

        $this->actingAs($this->pemilik())
            ->postJson(route('pilihanraya.borang-14.unpublish'), ['form_id' => $form->id])
            ->assertStatus(403);

        $form->refresh();
        $this->assertSame('published', $form->status, 'Borang mesti kekal diterbitkan.');
        $this->assertNotNull($form->published_at);
    }

    public function test_super_user_tidak_dapat_mencapai_laluan_langsung(): void
    {
        $form = $this->borang();

        $this->actingAs($this->pengguna('super_user', $this->parlimen->id, '0123460103'))
            ->postJson(route('pilihanraya.borang-14.unpublish'), ['form_id' => $form->id])
            ->assertStatus(403);

        $this->assertSame('published', $form->fresh()->status);
    }

    public function test_borang_draf_ditolak_dengan_422_dan_tidak_berubah(): void
    {
        $form = $this->borang('draft');

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.unpublish'), ['form_id' => $form->id])
            ->assertStatus(422);

        $this->assertSame('draft', $form->fresh()->status);
    }

    /** Nyahterbit menukar STATUS sahaja — bukan laluan pemadaman data. */
    public function test_nyahterbit_tidak_menyentuh_undi_struktur_atau_parti(): void
    {
        $form = $this->borang();
        Borang14Vote::create([
            'borang14_form_id' => $form->id, 'contest' => Borang14Vote::CONTEST_DUN,
            'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 1234,
        ]);
        $strukturAsal = $form->structure;
        $partiAsal = $form->parties;

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.unpublish'), ['form_id' => $form->id])
            ->assertOk();

        $form->refresh();
        $this->assertSame(1, $form->votes()->count());
        $this->assertSame(1234, (int) $form->votes()->first()->undi);
        $this->assertSame($strukturAsal, $form->structure);
        $this->assertSame($partiAsal, $form->parties);
    }

    /** Kitaran penuh: terbit -> nyahterbit -> terbit semula mengecap tarikh baharu. */
    public function test_boleh_diterbitkan_semula_selepas_nyahterbit(): void
    {
        $form = $this->borang();
        $super = $this->superAdmin();

        $this->actingAs($super)
            ->postJson(route('pilihanraya.borang-14.unpublish'), ['form_id' => $form->id])
            ->assertOk();

        $this->actingAs($super)
            ->postJson(route('pilihanraya.borang-14.publish'), ['form_id' => $form->id])
            ->assertOk()->assertJson(['ok' => true]);

        $form->refresh();
        $this->assertSame('published', $form->status);
        $this->assertNotNull($form->published_at);
    }

    public function test_form_id_tidak_wujud_ditolak_sebagai_pengesahan(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.unpublish'), ['form_id' => 999999])
            ->assertStatus(422);
    }
}
