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
        // Setiap kerusi dalam ujian ini SENGAJA diberi Borang 14 yang sedia,
        // supaya satu-satunya sebab kad tercicir ialah penapis STATUS — bukan
        // penapis "ada Borang 14" yang diuji berasingan di bawah.
        $this->seedDpt();
        $papan = $this->board(Scoreboard::STATUS_TERSIAR);
        $papan->update(['borang14_form_id' => $this->borang($this->dun)->id]);

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
        $this->seedDpt('BAHAU', '800101015556');
        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $pernahTersiar->id,
            'title' => 'BAHAU', 'status' => Scoreboard::STATUS_DRAF,
            'borang14_form_id' => $this->borang($pernahTersiar)->id,
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

    /**
     * Papan yang disiarkan TETAPI belum memaut Borang 14 tiada penjuru, tiada
     * parti dan tiada undi — kadnya akan kosong sepenuhnya. Ia digugurkan,
     * bukan dipapar dengan tempat kosong.
     */
    public function test_published_board_without_a_borang14_is_not_listed(): void
    {
        $this->seedDpt();
        $this->board(Scoreboard::STATUS_TERSIAR); // tiada borang14_form_id

        $boards = $this->get('/scoreboard')->assertOk()->viewData('page')['props']['boards'];

        $this->assertSame([], $boards);

        // Laluan tinjauan langsung mesti menapis dengan cara yang SAMA —
        // jika tidak, kad yang digugurkan pada muatan awal muncul semula
        // empat saat kemudian.
        $this->getJson('/scoreboard/senarai')->assertOk()->assertExactJson(['boards' => []]);
    }

    /**
     * Bentuk kad: nama kerusi, dan bagi setiap calon — parti, logo, nama dan
     * angka undi langsung. Sifar di sini ialah sifar SEBENAR (borang wujud,
     * belum ada undi dimasukkan), bukan null yang dipaksa jadi sifar.
     */
    public function test_card_carries_candidates_with_party_logo_and_live_votes(): void
    {
        $this->seedDpt();
        $form = $this->borang($this->dun);
        $papan = $this->board(Scoreboard::STATUS_TERSIAR);
        $papan->update([
            'borang14_form_id' => $form->id,
            'candidates' => [
                ['slot' => 1, 'nama' => 'AHMAD BIN ALI'],
                ['slot' => 2, 'nama' => 'SITI BINTI ABU'],
            ],
        ]);

        $boards = $this->getJson('/scoreboard/senarai')->assertOk()->json('boards');

        $this->assertCount(1, $boards);
        $kad = $boards[0];

        $this->assertSame('N27', $kad['kod']);
        $this->assertSame('dun', $kad['jenis']);
        $this->assertSame('PILAH', $kad['nama']);
        $this->assertCount(2, $kad['calon']);

        $this->assertSame('KEADILAN', $kad['calon'][0]['parti']);
        $this->assertSame('AHMAD BIN ALI', $kad['calon'][0]['calon']);
        $this->assertStringContainsString('images/parti/keadilan.png', $kad['calon'][0]['logo']);
        $this->assertSame(0, $kad['calon'][0]['undi']);
        $this->assertSame(0, $kad['total_keluar']);
    }

    /**
     * REGRESI SEBENAR (1 Ogos 2026): papan DUN GEMAS memapar tajuk
     * "SCOREBOARD - N.15 JUASSEH" kerana pengendali meniru tetapan papan lain.
     * Kerusinya betul, kebenarannya betul — teks bebas itu yang menipu, dan
     * ia elemen TERBESAR pada skrin.
     *
     * Identiti kini diterbitkan daripada Data Induk, dan tajuk yang mendakwa
     * kod kerusi lain digugurkan sepenuhnya.
     */
    public function test_a_title_claiming_another_seat_is_dropped_and_identity_comes_from_master_data(): void
    {
        $this->seedDpt();
        $papan = $this->board(Scoreboard::STATUS_TERSIAR);
        $papan->update([
            'borang14_form_id' => $this->borang($this->dun)->id,
            'title' => 'SCOREBOARD - N.15 JUASSEH',
        ]);

        $data = $this->getJson('/scoreboard/n27/data')->assertOk()->json();

        // Tajuk penipu tidak dipaparkan...
        $this->assertSame('SCOREBOARD', $data['title']);
        // ...dan identiti datang daripada kerusi, bukan daripada teks.
        $this->assertSame('N27', $data['identiti']['kod']);
        $this->assertSame('PILAH', $data['identiti']['nama']);
        $this->assertSame('N27 PILAH', $data['identiti']['label']);

        // Halaman awam tidak boleh menyebut kerusi asing itu langsung.
        $this->get('/scoreboard/n27')->assertOk()->assertDontSee('JUASSEH');

        // Amaran pembetulan ialah maklumat PENGENDALI — bukan untuk penonton.
        $this->assertArrayNotHasKey('tajuk_amaran', $data);
    }

    /** Tajuk yang menyebut kod kerusi SENDIRI adalah sah — "N.27" = "N27". */
    public function test_a_title_naming_its_own_seat_is_kept(): void
    {
        $this->seedDpt();
        $papan = $this->board(Scoreboard::STATUS_TERSIAR);
        $papan->update([
            'borang14_form_id' => $this->borang($this->dun)->id,
            'title' => 'SCOREBOARD N.27 PILAH',
        ]);

        $this->assertSame(
            'SCOREBOARD N.27 PILAH',
            $this->getJson('/scoreboard/n27/data')->assertOk()->json('title'),
        );
    }

    /**
     * Label "Sumber Undi" mesti menyebut KERUSI borang itu, jenis pilihanraya
     * dan tahun. Tanpa kerusi, "PRN 2026" boleh jadi milik mana-mana kerusi di
     * negara ini — dan pengendali tiada cara melihat borang mana yang dipaut.
     */
    public function test_the_vote_source_label_names_the_seat_election_type_and_year(): void
    {
        $this->seedDpt();
        $form = $this->borang($this->dun);
        $papan = $this->board(Scoreboard::STATUS_TERSIAR);
        $papan->update(['borang14_form_id' => $form->id]);

        $pemilik = User::create([
            'name' => 'Pemilik Pilah',
            'email' => 'pemilik-pilah@example.test',
            'telephone' => '0140000777',
            'password' => bcrypt('rahsia'),
            'role' => 'user',
            'status' => 'approved',
            'kadun_id' => $this->dun->id,
        ]);

        $milik = $this->actingAs($pemilik)->getJson(route('pilihanraya.scoreboard.data', [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
        ]))->assertOk()->json();

        $this->assertSame('DUN PILAH (N27) · PRN 2026 · 1 vs 1', $milik['sumber']['label']);

        // Dropdown Tetapan membaca label yang SAMA.
        $this->assertSame(
            [['id' => $form->id, 'label' => 'DUN PILAH (N27) · PRN 2026 · 1 vs 1']],
            $milik['sumberList'],
        );
    }

    /** Tajuk tanpa sebarang kod kerusi tidak pernah disentuh. */
    public function test_a_title_without_any_seat_code_is_never_touched(): void
    {
        $this->seedDpt();
        $papan = $this->board(Scoreboard::STATUS_TERSIAR);
        $papan->update([
            'borang14_form_id' => $this->borang($this->dun)->id,
            'title' => 'PILIHAN RAYA NEGERI 2026',
        ]);

        $this->assertSame(
            'PILIHAN RAYA NEGERI 2026',
            $this->getJson('/scoreboard/n27/data')->assertOk()->json('title'),
        );
    }

    /** Roll DPT minimum supaya Borang14Reference::forKadun() bukan null. */
    private function seedDpt(string $kadun = 'PILAH', string $ic = '800101015555'): void
    {
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => $ic,
            'nama' => 'PENGUNDI '.$kadun,
            'lokaliti' => 'KAMPUNG A',
            'daerah_mengundi' => 'AWAT',
            'kadun' => $kadun,
            'parlimen' => 'KUALA PILAH',
            'negeri' => 'NEGERI SEMBILAN',
            'is_deceased' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Borang 14 minimum bagi satu DUN — inilah yang menjadikan papan "sedia". */
    private function borang(Kadun $kadun): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2026,
            'penjuru' => 2,
            'status' => 'published',
            'parties' => [['nama' => 'KEADILAN'], ['nama' => 'BERSATU']],
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
