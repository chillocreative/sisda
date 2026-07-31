<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\Borang14RollUp;
use App\Services\Pilihanraya\ScoreboardPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Borang14RollUpTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $parlimen;
    private Kadun $dunA;
    private Kadun $dunB;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dunA = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);
        $this->dunB = Kadun::create(['nama' => 'SERTING', 'kod_dun' => 'N33', 'bandar_id' => $this->parlimen->id]);
    }

    private function definisiParlimen(?array $structure = null): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'structure' => $structure,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PN'], ['slot' => 3, 'nama' => 'PH']],
        ]);
    }

    private function borangDun(Kadun $dun, Borang14Form $definisi, array $undiParlimen): Borang14Form
    {
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $definisi->id,
        ]);

        foreach ($undiParlimen as $slot => $undi) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
                'pusat' => 'PM', 'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        // Undi DUN sendiri — TIDAK boleh masuk ke dalam jumlah Parlimen.
        Borang14Vote::create([
            'borang14_form_id' => $form->id, 'contest' => Borang14Vote::CONTEST_DUN,
            'pusat' => 'PM', 'saluran' => '1', 'slot' => 1, 'undi' => 9999,
        ]);

        return $form;
    }

    public function test_a_parlimen_form_with_its_own_structure_is_read_directly(): void
    {
        $definisi = $this->definisiParlimen(structure: ['daerah_mengundi' => []]);
        Borang14Vote::create([
            'borang14_form_id' => $definisi->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
            'pusat' => 'PM', 'saluran' => '1', 'slot' => 1, 'undi' => 500,
        ]);

        $hasil = Borang14RollUp::forParlimen($this->parlimen->id, 2027);

        $this->assertSame('borang', $hasil['sumber']);
        $this->assertSame(500, $hasil['undi'][1]);
        $this->assertNull($hasil['liputan'], 'Bacaan terus tiada konsep liputan separa.');
    }

    public function test_without_its_own_structure_it_sums_the_linked_dun_forms(): void
    {
        $definisi = $this->definisiParlimen(structure: null);
        $this->borangDun($this->dunA, $definisi, [1 => 2282, 2 => 1195, 3 => 412]);
        $this->borangDun($this->dunB, $definisi, [1 => 345, 2 => 243, 3 => 101]);

        $hasil = Borang14RollUp::forParlimen($this->parlimen->id, 2027);

        $this->assertSame('kumpulan', $hasil['sumber']);
        $this->assertSame(2627, $hasil['undi'][1]);
        $this->assertSame(1438, $hasil['undi'][2]);
        $this->assertSame(513, $hasil['undi'][3]);
        $this->assertSame(['melapor' => 2, 'jumlah' => 2], $hasil['liputan']);
    }

    public function test_partial_coverage_is_reported_not_hidden(): void
    {
        $definisi = $this->definisiParlimen(structure: null);
        $this->borangDun($this->dunA, $definisi, [1 => 2282]);
        // dunB linked but has keyed nothing yet.
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunB->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $definisi->id,
        ]);

        $hasil = Borang14RollUp::forParlimen($this->parlimen->id, 2027);

        $this->assertSame(['melapor' => 1, 'jumlah' => 2], $hasil['liputan']);
    }

    /**
     * Slot 90 (undi ditolak) dan 91 (undi tidak dimasukkan) BUKAN undi calon.
     * Satu DUN yang baru mengunci angka undi ditolak belum melaporkan apa-apa
     * keputusan — mengiranya sebagai "melapor" membolehkan melapor === jumlah
     * pada kiraan yang masih kehilangan seluruh undi calon DUN itu, lalu papan
     * awam memapar banner HIJAU "LENGKAP" di atas kiraan separa.
     */
    public function test_a_dun_with_only_rejected_ballots_keyed_has_not_reported(): void
    {
        $definisi = $this->definisiParlimen(structure: null);
        $this->borangDun($this->dunA, $definisi, [1 => 2282, 2 => 1195, 3 => 412]);

        $dunBelumLapor = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunB->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $definisi->id,
        ]);
        // HANYA undi ditolak (90) + tidak dimasukkan (91) — tiada satu pun
        // angka calon.
        foreach ([90 => 17, 91 => 4] as $slot => $undi) {
            Borang14Vote::create([
                'borang14_form_id' => $dunBelumLapor->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
                'pusat' => 'PM', 'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        $hasil = Borang14RollUp::forParlimen($this->parlimen->id, 2027);

        $this->assertSame(['melapor' => 1, 'jumlah' => 2], $hasil['liputan'],
            'Undi ditolak sahaja bukan laporan keputusan — liputan mesti kekal separa (amber).');

        // Angka 90/91 juga tidak boleh menyelinap masuk sebagai "slot" undi.
        $this->assertArrayNotHasKey(90, $hasil['undi']);
        $this->assertArrayNotHasKey(91, $hasil['undi']);
        $this->assertSame([1 => 2282, 2 => 1195, 3 => 412], $hasil['undi']);
    }

    /**
     * Kes yang sama, tetapi sampai ke muatan papan markah awam: badge mesti
     * kekal SEMENTARA (separa), bukan LENGKAP.
     */
    public function test_the_public_board_badge_stays_partial_when_a_dun_only_keyed_rejected_ballots(): void
    {
        $definisi = $this->definisiParlimen(structure: null);
        $this->borangDun($this->dunA, $definisi, [1 => 2282, 2 => 1195, 3 => 412]);

        $dunBelumLapor = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunB->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $definisi->id,
        ]);
        Borang14Vote::create([
            'borang14_form_id' => $dunBelumLapor->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
            'pusat' => 'PM', 'saluran' => '1', 'slot' => 90, 'undi' => 17,
        ]);

        // Rujukan DPT — tanpanya muatan pulang "belum sedia" dan ujian ini
        // akan lulus atas sebab yang salah.
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '990101010101', 'nama' => 'PENGUNDI', 'kadun' => 'GEMAS',
            'parlimen' => 'JEMPOL', 'daerah_mengundi' => 'PEKAN GEMAS', 'lokaliti' => 'SK GEMAS',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Scoreboard::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'borang14_form_id' => $definisi->id, 'title' => 'JEMPOL', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $muatan = ScoreboardPayload::forPublicSeat('parlimen', $this->parlimen->id);

        $this->assertSame(['melapor' => 1, 'jumlah' => 2], $muatan['liputan']);
        $this->assertNotSame($muatan['liputan']['melapor'], $muatan['liputan']['jumlah'],
            'Papan awam tidak boleh memapar LENGKAP sedangkan satu DUN belum melaporkan undi calon.');
    }

    public function test_no_parlimen_form_at_all_returns_null_not_zero(): void
    {
        $this->assertNull(Borang14RollUp::forParlimen($this->parlimen->id, 2027));
    }

    /**
     * Ulasan Tugasan 4 (dipaku semula di Tugasan 8): Borang14Form::borangDun()
     * memaut HANYA pada borang14_form_parlimen_id — TIADA silang-semak tahun.
     * Jadi kumpulan ini akan menjumlahkan borang DUN tahun LAIN jika pautan
     * rentas-tahun pernah wujud.
     *
     * Keadaan itu TIDAK BOLEH DICAPAI melalui UI: satu-satunya penulis lajur
     * tersebut ialah Borang14Controller::simpanStruktur(), dan firstOrCreate()
     * di sana dikunci pada tahun borang DUN itu sendiri (dibuktikan oleh
     * Borang14SerentakSetupTest::test_definition_is_keyed_on_the_dun_forms_own_tahun…).
     *
     * Ujian ini memaku akibatnya secara terbuka supaya sesiapa yang menambah
     * PENULIS KEDUA kepada lajur itu tahu bahawa kumpulan ini tidak akan
     * melindunginya — jaminan itu hidup SEPENUHNYA pada laluan tulis.
     */
    public function test_the_roll_up_itself_does_not_re_check_tahun_the_write_path_owns_that(): void
    {
        $definisi = $this->definisiParlimen(structure: null);

        // Keadaan yang mustahil melalui UI, dibina terus dalam pangkalan data.
        $dunTahunLain = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunA->id,
            'jenis_pr' => 'prn', 'tahun' => 2099, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $definisi->id,
        ]);
        Borang14Vote::create([
            'borang14_form_id' => $dunTahunLain->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
            'pusat' => 'PM', 'saluran' => '1', 'slot' => 1, 'undi' => 777,
        ]);

        $hasil = Borang14RollUp::forParlimen($this->parlimen->id, 2027);

        $this->assertSame(777, $hasil['undi'][1],
            'Kumpulan mengikut PAUTAN sahaja. Jika ujian ini berubah, laluan tulis mesti disemak semula.');
    }
}
