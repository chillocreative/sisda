<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skop pertandingan pada borang 14: satu scoresheet DUN boleh mengandungi DUA
 * pertandingan (PRU Parlimen + PRN DUN) bersebelahan. Ujian di sini memaku cara
 * senarai calon RATA daripada extractor dipisahkan kepada dua borang.
 */
class Borang14SkopPertandinganTest extends TestCase
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
        return User::firstOrCreate(['email' => 'skop@example.test'], [
            'name' => 'Penyelia', 'telephone' => '0123450001', 'password' => bcrypt('rahsia'),
            'role' => 'super_admin', 'status' => 'approved',
        ]);
    }

    /** Senarai RATA seperti yang dipulangkan extractor bagi scoresheet Gemas. */
    private function rata(): array
    {
        return [
            ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'BN', 'calon' => 'MOHD ISAM', 'kontes' => 'parlimen'],
            ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PN', 'calon' => 'MEJAR (B) ABDUL HALIM', 'kontes' => 'parlimen'],
            ['slot' => 3, 'keahlian_parti_id' => null, 'nama' => 'PH', 'calon' => 'FAIZ FADZIL', 'kontes' => 'parlimen'],
            ['slot' => 4, 'keahlian_parti_id' => null, 'nama' => 'PN', 'calon' => 'HAJI RIDZUAN AHMAD', 'kontes' => 'dun'],
            ['slot' => 5, 'keahlian_parti_id' => null, 'nama' => 'BN', 'calon' => 'ABD RAZAK BIN AB SAID', 'kontes' => 'dun'],
        ];
    }

    private function hantar(array $ubah = [])
    {
        return $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.parties'), array_merge([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2027,
            'penjuru' => 5,
            'skop' => 'kedua',
            'parties' => $this->rata(),
        ], $ubah));
    }

    private function borangDun(): Borang14Form
    {
        return Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();
    }

    // ---- 1. Pemisahan ---------------------------------------------------

    public function test_flat_list_is_split_into_parlimen_and_dun_contests(): void
    {
        $this->hantar()->assertOk()->assertJson(['ok' => true]);

        $dunForm = $this->borangDun();

        // Jalur DUN: dua calon, dinomborkan SEMULA 1..2.
        $this->assertCount(2, $dunForm->parties);
        $this->assertSame([1, 2], array_column($dunForm->parties, 'slot'));
        $this->assertSame('HAJI RIDZUAN AHMAD', $dunForm->parties[0]['calon']);
        $this->assertSame('ABD RAZAK BIN AB SAID', $dunForm->parties[1]['calon']);
        $this->assertSame(2, (int) $dunForm->penjuru);
        // Penetapan dinyatakan oleh PEMISAHAN itu sendiri — tiada keadaan pendua.
        $this->assertArrayNotHasKey('kontes', $dunForm->parties[0]);

        // Jalur Parlimen: pada borang TAKRIFAN, dinomborkan semula 1..3.
        $definisi = $dunForm->formParlimen;
        $this->assertNotNull($definisi, 'Borang DUN mesti dipaut kepada borang takrifan Parlimen.');
        $this->assertSame('parlimen', $definisi->kawasan_type);
        $this->assertSame($this->parlimen->id, (int) $definisi->kawasan_id);
        $this->assertSame('pru', $definisi->jenis_pr);
        $this->assertSame(2027, (int) $definisi->tahun);
        $this->assertCount(3, $definisi->parties);
        $this->assertSame([1, 2, 3], array_column($definisi->parties, 'slot'));
        $this->assertSame('MOHD ISAM', $definisi->parties[0]['calon']);
        $this->assertSame(3, (int) $definisi->penjuru);

        // Struktur KOSONG ialah isyarat yang menyuruh Borang14RollUp mengumpul
        // borang DUN. Memberinya struktur akan memapar 0 undi pada papan Parlimen.
        $this->assertEmpty($definisi->structure);
    }

    // ---- 2. Skop tunggal = tingkah laku hari ini ------------------------

    public function test_single_contest_scope_behaves_exactly_as_before(): void
    {
        $parti = [
            ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'PN', 'calon' => 'HAJI RIDZUAN AHMAD'],
            ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'BN', 'calon' => 'ABD RAZAK BIN AB SAID'],
        ];

        // (a) Tiada skop langsung — muatan lama.
        $this->hantar(['skop' => null, 'penjuru' => 2, 'parties' => $parti])->assertOk();

        $dunForm = $this->borangDun();
        $this->assertCount(2, $dunForm->parties);
        $this->assertNull($dunForm->borang14_form_parlimen_id);
        $this->assertSame(2, (int) $dunForm->penjuru);
        $this->assertSame(0, Borang14Form::where('kawasan_type', 'parlimen')->count(),
            'Skop tunggal tidak boleh mencipta borang takrifan Parlimen.');

        // (b) Skop tunggal EKSPLISIT yang sepadan dengan kawasan borang, dengan
        //     tanda `kontes` yang tertinggal daripada UI — ia tidak disimpan.
        $bertanda = array_map(fn ($p) => $p + ['kontes' => 'dun'], $parti);
        $this->hantar(['skop' => 'dun', 'penjuru' => 2, 'parties' => $bertanda])->assertOk();

        $dunForm->refresh();
        $this->assertCount(2, $dunForm->parties);
        $this->assertArrayNotHasKey('kontes', $dunForm->parties[0]);
        $this->assertNull($dunForm->borang14_form_parlimen_id);
        $this->assertSame(0, Borang14Form::where('kawasan_type', 'parlimen')->count());
    }

    // ---- 3. Tolak apabila undi sudah wujud ------------------------------

    public function test_changing_the_assignment_is_refused_once_votes_exist(): void
    {
        $this->hantar()->assertOk();

        $dunForm = $this->borangDun();
        $dunForm->votes()->create(['contest' => 'dun', 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 111]);
        $dunForm->votes()->create(['contest' => 'parlimen', 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 222]);

        // Pindahkan slot 3 daripada Parlimen ke DUN — 2 lawan 3.
        $rata = $this->rata();
        $rata[2]['kontes'] = 'dun';

        $this->hantar(['parties' => $rata])->assertStatus(422);

        // TIADA apa-apa ditulis pada mana-mana borang.
        $dunForm->refresh();
        $this->assertCount(2, $dunForm->parties);
        $this->assertSame('HAJI RIDZUAN AHMAD', $dunForm->parties[0]['calon']);
        $this->assertCount(3, $dunForm->formParlimen->parties);
        $this->assertSame('FAIZ FADZIL', $dunForm->formParlimen->parties[2]['calon']);
    }

    public function test_changing_the_scope_itself_is_refused_once_votes_exist(): void
    {
        $this->hantar()->assertOk();

        $dunForm = $this->borangDun();
        $dunForm->votes()->create(['contest' => 'dun', 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 111]);

        $this->hantar(['skop' => 'dun', 'penjuru' => 2, 'parties' => [
            ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'PN', 'calon' => 'HAJI RIDZUAN AHMAD'],
            ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'BN', 'calon' => 'ABD RAZAK BIN AB SAID'],
        ]])->assertStatus(422);

        $dunForm->refresh();
        $this->assertNotNull($dunForm->borang14_form_parlimen_id);
        $this->assertCount(2, $dunForm->parties);
    }

    public function test_resaving_the_same_assignment_is_allowed_even_with_votes(): void
    {
        $this->hantar()->assertOk();

        $dunForm = $this->borangDun();
        $dunForm->votes()->create(['contest' => 'dun', 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 111]);

        $this->hantar()->assertOk();
    }

    // ---- 4. Sekurang-kurangnya dua calon setiap pertandingan ------------

    public function test_a_contest_left_with_one_candidate_is_refused_by_name(): void
    {
        $rata = $this->rata();
        $rata[3]['kontes'] = 'parlimen';   // 4 Parlimen, 1 DUN

        $res = $this->hantar(['parties' => $rata])->assertStatus(422);

        $this->assertStringContainsString('DUN', collect($res->json('errors'))->flatten()->implode(' '));
        $this->assertSame(0, Borang14Form::count(), 'Penolakan tidak boleh mencipta apa-apa borang.');
    }

    public function test_a_contest_with_more_than_six_candidates_is_refused(): void
    {
        $rata = [];
        foreach (range(1, 5) as $i) {
            $rata[] = ['slot' => $i, 'keahlian_parti_id' => null, 'nama' => "P{$i}", 'calon' => "CALON {$i}", 'kontes' => 'parlimen'];
        }
        $rata[] = ['slot' => 6, 'keahlian_parti_id' => null, 'nama' => 'P6', 'calon' => 'CALON 6', 'kontes' => 'parlimen'];
        $rata[] = ['slot' => 6, 'keahlian_parti_id' => null, 'nama' => 'P7', 'calon' => 'CALON 7', 'kontes' => 'parlimen'];

        $this->hantar(['parties' => $rata])->assertStatus(422);
        $this->assertSame(0, Borang14Form::count());
    }

    public function test_kontes_is_required_for_every_party_when_scope_is_kedua(): void
    {
        $rata = $this->rata();
        unset($rata[4]['kontes']);

        $this->hantar(['parties' => $rata])->assertStatus(422);
        $this->assertSame(0, Borang14Form::count());
    }

    // ---- 5. Skop kedua-dua pada borang Parlimen -------------------------

    public function test_scope_kedua_on_a_parlimen_form_is_refused(): void
    {
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.parties'), [
            'kawasan_type' => 'parlimen',
            'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru',
            'tahun' => 2027,
            'penjuru' => 5,
            'skop' => 'kedua',
            'parties' => $this->rata(),
        ])->assertStatus(422);

        $this->assertSame(0, Borang14Form::count());
    }

    // ---- 6. Parlimen sahaja pada borang DUN -----------------------------

    public function test_parlimen_only_scope_on_a_dun_form_links_and_leaves_dun_parties_empty(): void
    {
        $parti = [
            ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'BN', 'calon' => 'MOHD ISAM'],
            ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PN', 'calon' => 'MEJAR (B) ABDUL HALIM'],
            ['slot' => 3, 'keahlian_parti_id' => null, 'nama' => 'PH', 'calon' => 'FAIZ FADZIL'],
        ];

        $this->hantar(['skop' => 'parlimen', 'penjuru' => 3, 'parties' => $parti])->assertOk();

        $dunForm = $this->borangDun();
        $this->assertSame([], $dunForm->parties, 'Borang DUN tidak merekod pertandingan DUN dalam skop ini.');
        $this->assertNotNull($dunForm->borang14_form_parlimen_id);

        $definisi = $dunForm->formParlimen;
        $this->assertCount(3, $definisi->parties);
        $this->assertSame([1, 2, 3], array_column($definisi->parties, 'slot'));
        $this->assertSame(3, (int) $definisi->penjuru);
        $this->assertEmpty($definisi->structure);
    }
}
