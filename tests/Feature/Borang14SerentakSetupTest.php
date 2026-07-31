<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14SerentakSetupTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;
    private Bandar $parlimen;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'setup@example.test'], [
            'name' => 'Penyelia', 'telephone' => '0123450000', 'password' => bcrypt('rahsia'),
            'role' => 'super_admin', 'status' => 'approved',
        ]);
    }

    public function test_enabling_concurrent_mode_creates_and_links_the_parlimen_definition(): void
    {
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2027,
            'penjuru' => 2,
            'parlimen_id' => $this->parlimen->id,
        ])->assertSuccessful();

        $dunForm = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();
        $this->assertNotNull($dunForm->borang14_form_parlimen_id);

        $definisi = $dunForm->formParlimen;
        $this->assertSame('parlimen', $definisi->kawasan_type);
        $this->assertSame($this->parlimen->id, $definisi->kawasan_id);
        $this->assertSame(2027, (int) $definisi->tahun);
        $this->assertEmpty($definisi->structure, 'Borang takrifan tiada struktur sendiri.');
    }

    public function test_a_second_dun_reuses_the_same_definition(): void
    {
        $dunB = Kadun::create(['nama' => 'SERTING', 'kod_dun' => 'N33', 'bandar_id' => $this->parlimen->id]);

        foreach ([$this->dun, $dunB] as $dun) {
            $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
                'kawasan_type' => 'dun', 'kawasan_id' => $dun->id,
                'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
                'parlimen_id' => $this->parlimen->id,
            ])->assertSuccessful();
        }

        // SATU takrifan sahaja — jika tidak, slot 1 boleh bermakna calon
        // berbeza di setiap DUN dan kumpulan akan menjumlahkan lajur yang salah.
        $this->assertSame(1, Borang14Form::where('kawasan_type', 'parlimen')
            ->where('kawasan_id', $this->parlimen->id)->where('tahun', 2027)->count());
    }

    /**
     * Semakan wajib daripada ulasan Task 4: Borang14Form::borangDun() memaut
     * hanya pada borang14_form_parlimen_id — TIADA semakan tahun. Jadi
     * firstOrCreate() DI SINI mesti dikunci pada tahun borang DUN itu sendiri
     * ($validated['tahun']), bukan tahun global/lalai — jika tidak, borang
     * DUN 2027 boleh terpaut kepada takrifan Parlimen 2099 dan keputusan
     * 2099 turut menjumlahkan undi 2027.
     *
     * Ujian ini mengesahkan laluan SATU-SATUNYA yang menulis lajur itu
     * (endpoint ini) tidak boleh menghasilkan pautan rentas-tahun: takrifan
     * 2099 sedia ada kekal tidak disentuh, dan satu takrifan BAHARU 2027
     * dicipta/digunakan sebaliknya.
     */
    public function test_definition_is_keyed_on_the_dun_forms_own_tahun_not_reused_across_years(): void
    {
        $takrifanLama = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2099, 'penjuru' => 2, 'parties' => [],
        ]);

        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parlimen_id' => $this->parlimen->id,
        ])->assertSuccessful();

        $dunForm = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();

        $this->assertNotSame($takrifanLama->id, $dunForm->borang14_form_parlimen_id,
            'Borang DUN 2027 tidak boleh terpaut kepada takrifan Parlimen 2099.');
        $this->assertSame(2027, (int) $dunForm->formParlimen->tahun);

        // Takrifan 2099 lama kekal wujud tanpa disentuh — dua takrifan
        // berasingan bagi tahun berlainan, bukan satu ditulis ganti.
        $this->assertSame(2, Borang14Form::where('kawasan_type', 'parlimen')
            ->where('kawasan_id', $this->parlimen->id)->count());
    }

    public function test_disabling_concurrent_mode_nulls_the_link_but_keeps_recorded_parlimen_votes(): void
    {
        // Paut dahulu.
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parlimen_id' => $this->parlimen->id,
        ])->assertSuccessful();

        $dunForm = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();
        $this->assertNotNull($dunForm->borang14_form_parlimen_id);

        // Undi kontes 'parlimen' sudah dikunci masuk pada borang DUN ini
        // (tersimpan pada baris DUN, bukan pada borang takrifan).
        $dunForm->votes()->create(['contest' => 'parlimen', 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 123]);

        // Nyahtogol — parlimen_id dihantar secara EKSPLISIT sebagai null.
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parlimen_id' => null,
        ])->assertSuccessful();

        $dunForm->refresh();
        $this->assertNull($dunForm->borang14_form_parlimen_id, 'Togol dimatikan mesti menyahpaut.');

        // Undi PRU yang sudah direkod TIDAK dipadam — ia yatim/tersembunyi
        // daripada skrin dua jalur, tetapi pulih sepenuhnya jika dipaut semula.
        $this->assertSame(123, (int) $dunForm->votesFor('parlimen')->where('pusat', 'SK GEMAS')->value('undi'));

        // Paut semula — undi lama yang tidak pernah dipadam terus kelihatan.
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parlimen_id' => $this->parlimen->id,
        ])->assertSuccessful();

        $dunForm->refresh();
        $this->assertNotNull($dunForm->borang14_form_parlimen_id);
        $this->assertSame(123, (int) $dunForm->votesFor('parlimen')->where('pusat', 'SK GEMAS')->value('undi'));
    }

    public function test_a_dun_cannot_be_linked_to_a_parlimen_that_is_not_its_own_parent(): void
    {
        $negeriLain = Negeri::create(['nama' => 'SELANGOR']);
        $parlimenLain = Bandar::create(['nama' => 'SHAH ALAM', 'kod_parlimen' => 'P999', 'negeri_id' => $negeriLain->id]);

        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parlimen_id' => $parlimenLain->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('borang14_forms', ['kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id]);
    }
}
