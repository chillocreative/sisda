<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Models\User;
use App\Services\Pilihanraya\ScoreboardPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Borang TAKRIFAN Parlimen ialah perangkap yang paling mudah dipijak dalam
 * cawangan ini: ia kelihatan SAMA seperti mana-mana Borang 14, dan alur kerja
 * MEMAKSA pengendali membukanya (di situ sahaja nama calon PRU dinamakan).
 *
 * `structure` yang KOSONG padanya ialah satu-satunya isyarat yang menyuruh
 * Borang14RollUp MENGUMPUL borang DUN. Beri ia struktur — melalui Sunting
 * Struktur ATAU muat naik scoresheet — dan roll-up bertukar kepada bacaan
 * TERUS daripada borang yang tiada undi, lalu papan Parlimen awam menerbitkan
 * 0 undi TANPA badge SEMENTARA (bacaan terus memulangkan liputan = null).
 */
class Borang14SerentakTakrifanGuardTest extends TestCase
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

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'takrifan@example.test'], [
            'name' => 'Penyelia', 'telephone' => '0123450001', 'password' => bcrypt('rahsia'),
            'role' => 'super_admin', 'status' => 'approved',
        ]);
    }

    /** Takrifan Parlimen + satu borang DUN yang memautinya, dengan undi PRU sebenar. */
    private function pasangSerentak(): array
    {
        $takrifan = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'structure' => null,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PN'], ['slot' => 3, 'nama' => 'PH']],
        ]);

        $dunForm = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $takrifan->id,
        ]);
        foreach ([1 => 2282, 2 => 1195, 3 => 412] as $slot => $undi) {
            Borang14Vote::create([
                'borang14_form_id' => $dunForm->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
                'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        return [$takrifan, $dunForm];
    }

    public function test_saving_a_structure_on_a_linked_parlimen_definition_is_refused(): void
    {
        [$takrifan] = $this->pasangSerentak();

        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027,
            'pusat' => [[
                'row_id' => 'r1', 'dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran_count' => 1,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.pusat.0', fn ($m) => str_contains($m, 'TAKRIFAN calon PRU'));

        $takrifan->refresh();
        $this->assertEmpty($takrifan->structure,
            'Takrifan mesti kekal tanpa struktur — struktur kosong itulah yang menghidupkan roll-up.');
    }

    /**
     * Guard di atas adalah PENTING kerana akibat kegagalannya senyap: papan
     * awam bertukar kepada 0 undi TANPA badge SEMENTARA. Ujian ini memaku
     * akibat itu supaya sesiapa yang melonggarkan guard nampak apa yang hilang.
     */
    public function test_the_public_parlimen_board_still_aggregates_after_the_refused_attempt(): void
    {
        [$takrifan] = $this->pasangSerentak();

        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '990101010101', 'nama' => 'PENGUNDI', 'kadun' => 'GEMAS',
            'parlimen' => 'JEMPOL', 'daerah_mengundi' => 'PEKAN GEMAS', 'lokaliti' => 'SK GEMAS',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Scoreboard::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'borang14_form_id' => $takrifan->id, 'title' => 'P133', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027,
            'pusat' => [[
                'row_id' => 'r1', 'dm' => 'PEKAN GEMAS', 'pusat' => 'DEWAN BARU', 'saluran_count' => 1,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.pusat.0', fn ($m) => str_contains($m, 'TAKRIFAN calon PRU'));

        $muatan = ScoreboardPayload::forPublicSeat('parlimen', $this->parlimen->id);

        $this->assertSame([2282, 1195, 412], array_column($muatan['rows'], 'undi'));
        $this->assertSame(['melapor' => 1, 'jumlah' => 1], $muatan['liputan']);
    }

    /**
     * Borang Parlimen yang TIADA borang DUN memautinya ialah borang PRU biasa —
     * guard tidak boleh menyentuhnya.
     */
    public function test_an_unlinked_parlimen_form_can_still_have_its_structure_edited(): void
    {
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027,
            'pusat' => [[
                'row_id' => 'r1', 'dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran_count' => 1,
            ]],
        ])->assertSuccessful();

        $form = Borang14Form::where('kawasan_type', 'parlimen')->where('kawasan_id', $this->parlimen->id)->firstOrFail();
        $this->assertNotEmpty($form->structure['rows'] ?? []);
    }

    /** Borang DUN yang dipaut tidak terjejas — ia BUKAN takrifan. */
    public function test_a_linked_dun_form_can_still_have_its_structure_edited(): void
    {
        $this->pasangSerentak();

        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => [[
                'row_id' => 'r1', 'dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran_count' => 1,
            ]],
        ])->assertSuccessful();

        $dunForm = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();
        $this->assertNotEmpty($dunForm->structure['rows'] ?? []);
    }

    public function test_keying_a_vote_directly_on_a_linked_parlimen_definition_is_refused(): void
    {
        [$takrifan] = $this->pasangSerentak();

        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3,
            'contest' => 'parlimen', 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 500,
        ])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'TAKRIFAN calon PRU'));

        $this->assertSame(0, $takrifan->votes()->count(),
            'Undi pada borang takrifan tidak dikira sesiapa — ia tidak boleh disimpan langsung.');
    }

    public function test_keying_a_vote_on_an_unlinked_parlimen_form_still_works(): void
    {
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3,
            'contest' => 'parlimen', 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 500,
        ])->assertSuccessful();

        $form = Borang14Form::where('kawasan_type', 'parlimen')->where('kawasan_id', $this->parlimen->id)->firstOrFail();
        $this->assertSame(500, (int) $form->votesFor('parlimen')->value('undi'));
    }

    /**
     * Lubang yang sama daripada arah bertentangan: menghidupkan mod serentak
     * pada borang DUN apabila borang Parlimen tahun itu SUDAH berstruktur.
     * firstOrCreate() akan memulangkan borang berstruktur itu sebagai
     * "takrifan", dan roll-up akan membacanya terus — mengabaikan setiap undi
     * PRU pada borang DUN.
     */
    public function test_a_dun_cannot_be_linked_to_a_parlimen_form_that_already_has_a_structure(): void
    {
        Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'parties' => [],
            'structure' => ['rows' => [['dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran' => '1']]],
        ]);

        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => [],
            'parlimen_id' => $this->parlimen->id,
        ])->assertStatus(422)->assertJsonValidationErrors('parlimen_id');


        $this->assertDatabaseMissing('borang14_forms', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
        ]);
    }

    /**
     * Pintu KETIGA: muat naik scoresheet. writeForm() menulis
     * 'structure' => $extractedData, jadi commit ke atas borang takrifan
     * memberinya struktur — kesan yang SAMA seperti Sunting Struktur, tetapi
     * melalui skrin yang berlainan sepenuhnya.
     */
    public function test_uploading_a_scoresheet_onto_a_linked_parlimen_definition_is_refused(): void
    {
        // Guna negeri yang SAMA seperti setUp() — KawasanResolver memadankan
        // negeri mengikut nama (UPPER), jadi baris negeri kedua akan
        // memisahkan Bandar ini daripada yang dipadankan oleh resolver.
        $juasseh = Bandar::create(['nama' => 'JUASSEH', 'kod_parlimen' => 'P129', 'negeri_id' => $this->parlimen->negeri_id]);
        $dunJuasseh = Kadun::create(['nama' => 'PALONG', 'kod_dun' => 'N01', 'bandar_id' => $juasseh->id]);

        $takrifan = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $juasseh->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 3, 'parties' => [], 'structure' => null,
        ]);
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $dunJuasseh->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $takrifan->id,
        ]);

        $data = json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true);
        $data = array_merge($data, ['kawasan_kod' => 'P.129', 'parlimen_kod' => '129']);
        $this->mock(\App\Services\Pilihanraya\ScoresheetExtractor::class, function ($mock) use ($data) {
            $mock->shouldReceive('extractDetailed')->once()
                ->andReturn(['ok' => true, 'data' => $data, 'error' => null]);
        });

        $user = User::factory()->create(['role' => 'admin', 'telephone' => '0123450099']);
        $token = $this->actingAs($user)->post(route('pilihanraya.borang-14.upload'), [
            'dry_run' => 1,
            'fail' => \Illuminate\Http\UploadedFile::fake()->create('scoresheet.pdf', 10, 'application/pdf'),
            'jenis_pr' => 'prn', 'tahun' => 2023,
        ])->assertOk()->json('token');

        $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertStatus(422);

        $takrifan->refresh();
        $this->assertEmpty($takrifan->structure, 'Muat naik tidak boleh memberi struktur kepada borang takrifan.');
        $this->assertSame(0, $takrifan->votes()->count());
    }

    /** Borang Parlimen KOSONG (takrifan sebenar) masih boleh dipaut. */
    public function test_linking_to_a_structureless_parlimen_form_is_still_allowed(): void
    {
        $takrifan = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'parties' => [], 'structure' => null,
        ]);

        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => [],
            'parlimen_id' => $this->parlimen->id,
        ])->assertSuccessful();

        $dunForm = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();
        $this->assertSame($takrifan->id, (int) $dunForm->borang14_form_parlimen_id);
    }
}
