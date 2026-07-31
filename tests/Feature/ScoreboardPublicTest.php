<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Papan draf tidak boleh bocor. Kod tidak dikenali dan papan draf mesti 404
 * secara SAMA supaya tiada petunjuk kerusi mana yang wujud.
 */
class ScoreboardPublicTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $bandar->id]);
    }

    private function board(string $status, ?string $kod = 'N27'): Scoreboard
    {
        return Scoreboard::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'title' => 'PILAH',
            'status' => $status,
            'kod' => $kod,
        ]);
    }

    public function test_published_board_is_visible_without_login(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/n27')->assertOk();
        $this->getJson('/scoreboard/n27/data')->assertOk();
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/N27')->assertOk();
    }

    public function test_draft_board_404s_exactly_like_an_unknown_code(): void
    {
        $this->board(Scoreboard::STATUS_DRAF);

        $this->get('/scoreboard/n27')->assertNotFound();
        $this->get('/scoreboard/n99')->assertNotFound();
        $this->getJson('/scoreboard/n27/data')->assertNotFound();
    }

    public function test_legacy_numeric_url_redirects_to_the_code_url(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/'.$this->dun->id)
            ->assertRedirect('/scoreboard/n27');
    }

    public function test_legacy_numeric_url_for_an_unpublished_seat_404s(): void
    {
        $this->board(Scoreboard::STATUS_DRAF);

        $this->get('/scoreboard/'.$this->dun->id)->assertNotFound();
    }

    public function test_bare_index_lists_only_published_boards(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        // Kes draf tanpa kod — tidak boleh keluar dalam senarai.
        $lain = Kadun::create(['nama' => 'JOHOL', 'kod_dun' => 'N26', 'bandar_id' => $this->dun->bandar_id]);
        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $lain->id,
            'title' => 'JOHOL', 'status' => Scoreboard::STATUS_DRAF, 'kod' => null,
        ]);

        // Kes yang menanggung beban ujian ini: papan yang PERNAH disiarkan lalu
        // dinyahsiar mengekalkan kod yang telah dicap (lihat
        // ScoreboardController::publish()). Jika penapis status() pada suatu
        // hari regresi, whereNotNull('kod') sahaja TIDAK akan menangkapnya —
        // kod bukan null di sini. Bina fixture ini melalui endpoint publish()
        // sebenar supaya ia mencerminkan cara keadaan ini timbul di produksi.
        $pernahTersiar = Kadun::create(['nama' => 'BAHAU', 'kod_dun' => 'N31', 'bandar_id' => $this->dun->bandar_id]);
        $pemilik = \App\Models\User::create([
            'name' => 'Pemilik Bahau',
            'email' => 'pemilik-bahau@example.test',
            'telephone' => '0140000999',
            'password' => bcrypt('rahsia'),
            'role' => 'user',
            'status' => 'approved',
            'kadun_id' => $pernahTersiar->id,
        ]);
        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $pernahTersiar->id,
            'title' => 'BAHAU', 'status' => Scoreboard::STATUS_DRAF,
        ]);
        $this->actingAs($pemilik)->postJson(route('pilihanraya.scoreboard.publish'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $pernahTersiar->id,
            'status' => Scoreboard::STATUS_TERSIAR,
        ])->assertOk();
        $this->actingAs($pemilik)->postJson(route('pilihanraya.scoreboard.publish'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $pernahTersiar->id,
            'status' => Scoreboard::STATUS_DRAF,
        ])->assertOk();
        $this->assertDatabaseHas('scoreboards', [
            'kawasan_id' => $pernahTersiar->id,
            'status' => Scoreboard::STATUS_DRAF,
            'kod' => 'N31',
        ]);

        $response = $this->get('/scoreboard')->assertOk();
        $boards = $response->viewData('page')['props']['boards'];

        $this->assertCount(1, $boards);
        $this->assertSame('N27', $boards[0]['kod']);

        // Dan papan yang dinyahsiar itu sendiri mesti 404 di laluan awam.
        $this->get('/scoreboard/n31')->assertNotFound();
    }

    /** Roll DPT minimum supaya Borang14Reference::forKadun() bukan null. */
    private function seedDpt(): void
    {
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '800101015555',
            'nama' => 'PENGUNDI PILAH',
            'lokaliti' => 'KAMPUNG A',
            'daerah_mengundi' => 'AWAT',
            'kadun' => 'PILAH',
            'parlimen' => 'KUALA PILAH',
            'negeri' => 'NEGERI SEMBILAN',
            'is_deceased' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Kebocoran privasi: muatan papan membawa 'dikemaskini' (users.name
     * pengendali SISDA yang menyimpan terakhir) dan 'sumber' (senario Borang 14
     * dalaman). Skrin pemilik memerlukan kedua-duanya; URL awam tanpa log masuk
     * tidak sepatutnya mendedahkan siapa menyunting papan itu.
     *
     * Ujian ini memandu KEDUA-DUA laluan pada papan yang SAMA supaya penapis
     * terbukti sebelah sahaja — bukan sekadar dibuang di mana-mana.
     */
    public function test_public_payload_hides_the_editor_name_while_the_owner_payload_keeps_it(): void
    {
        $this->seedDpt();

        $pemilik = User::create([
            'name' => 'Ahmad Bin Penyunting',
            'email' => 'penyunting@example.test',
            'telephone' => '0140000123',
            'password' => bcrypt('rahsia'),
            'role' => 'user',
            'status' => 'approved',
            'kadun_id' => $this->dun->id,
        ]);

        $form = Borang14Form::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2026,
            'penjuru' => 2,
            'status' => 'published',
            'parties' => [['nama' => 'KEADILAN'], ['nama' => 'BERSATU']],
        ]);

        Scoreboard::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $form->id,
            'title' => 'PILAH 2026',
            'status' => Scoreboard::STATUS_TERSIAR,
            'kod' => 'N27',
            'pihak_kami' => [1],
            'updated_by' => $pemilik->id,
        ]);

        // --- Laluan AWAM: kedua-dua kunci mesti tiada sama sekali. ---
        $awam = $this->getJson('/scoreboard/n27/data')->assertOk();
        $data = $awam->json();

        $this->assertTrue($data['ready'], 'Papan mesti sedia supaya ujian ini benar-benar menguji muatan penuh.');
        $this->assertArrayNotHasKey('dikemaskini', $data);
        $this->assertArrayNotHasKey('sumber', $data);
        $this->assertStringNotContainsString('Ahmad Bin Penyunting', $awam->getContent());

        // Halaman awam (Inertia) menyiarkan prop yang sama dalam HTML —
        // periksa ia juga bersih, bukan hanya endpoint JSON.
        $this->get('/scoreboard/n27')->assertOk()->assertDontSee('Ahmad Bin Penyunting');

        // --- Laluan PEMILIK: kedua-dua kunci mesti KEKAL. ---
        $milik = $this->actingAs($pemilik)->getJson(route('pilihanraya.scoreboard.data', [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
        ]))->assertOk()->json();

        $this->assertSame('Ahmad Bin Penyunting', $milik['dikemaskini']['nama']);
        $this->assertSame($form->id, $milik['sumber']['id']);
    }
}
