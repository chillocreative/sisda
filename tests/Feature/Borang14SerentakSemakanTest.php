<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Pilihanraya\Borang14ScenarioMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser as PdfParser;
use Tests\TestCase;

/**
 * Tugasan 8 — dua lubang yang ditemui semasa ulasan Tugasan 6:
 *
 *  (1) Silang-semak baki jalur PRU tidak dilakukan SESIAPA. crosscheckIssues()
 *      membaca pertandingan borang itu sendiri sahaja, dan borang Parlimen yang
 *      dipaut tiada undi langsung untuk disemak — jadi satu digit PRU yang
 *      tertukar diterbitkan bersih dan mengalir terus ke papan Parlimen awam.
 *
 *  (2) Pariti keluar antara dua jalur tidak pernah diperiksa, walaupun setiap
 *      pengundi menerima KEDUA-DUA kertas undi — pengesan ralat percuma.
 *
 * Serta liputan per-pembaca pada borang yang memegang KEDUA-DUA pertandingan
 * (pdf() dan Borang14ScenarioMapper), yang sebelum ini bersandar pada ujian
 * regresi lama yang langsung tiada fikstur dua pertandingan.
 */
class Borang14SerentakSemakanTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $bandar;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->bandar = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->bandar->id]);

        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '990101010101', 'nama' => 'PENGUNDI', 'kadun' => 'GEMAS',
            'parlimen' => 'JEMPOL', 'negeri' => 'NEGERI SEMBILAN',
            'daerah_mengundi' => 'PEKAN GEMAS', 'lokaliti' => 'SK GEMAS',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` — pincang
        // sedia ada; diatasi setempat seperti ujian lain dalam suite ini.
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123450088']);
    }

    /** Takrifan Parlimen: penjuru 3, SENGAJA berbeza daripada penjuru 2 DUN. */
    private function takrifanParlimen(): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->bandar->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'status' => 'draft',
            'parties' => [
                ['slot' => 1, 'nama' => 'BN', 'keahlian_parti_id' => 1],
                ['slot' => 2, 'nama' => 'PN', 'keahlian_parti_id' => 2],
                ['slot' => 3, 'nama' => 'PH', 'keahlian_parti_id' => 3],
            ],
        ]);
    }

    /**
     * Borang DUN dengan struktur BERASAL SCORESHEET (bukan manual) supaya
     * semakan terhadap angka bercetak benar-benar berjalan. (A) = 100 pada
     * satu-satunya saluran.
     */
    private function borangDun(?Borang14Form $takrifan = null, string $origin = 'scoresheet'): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet',
            'borang14_form_parlimen_id' => $takrifan?->id,
            'parties' => [
                ['slot' => 1, 'nama' => 'PN', 'keahlian_parti_id' => 2],
                ['slot' => 2, 'nama' => 'BN', 'keahlian_parti_id' => 1],
            ],
            'structure' => [
                'origin' => $origin,
                'calon' => [['nama' => 'A'], ['nama' => 'B']],
                'rows' => [[
                    'pusat' => 'SK GEMAS', 'dm' => 'PEKAN GEMAS', 'saluran' => '1',
                    'a' => 100, 'undi' => [60, 30], 'jumlah_undian' => 90,
                    'ditolak' => 7, 'tidak_dimasukkan' => 3,
                ]],
            ],
        ]);
    }

    private function undi(Borang14Form $form, string $contest, int $slot, int $undi, string $saluran = '1'): void
    {
        Borang14Vote::create([
            'borang14_form_id' => $form->id, 'contest' => $contest,
            'pusat' => 'SK GEMAS', 'saluran' => $saluran, 'slot' => $slot, 'undi' => $undi,
        ]);
    }

    /** @return string[] */
    private function isu(): array
    {
        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
        ]))->assertOk();

        return $res->json('form.crosscheck_issues') ?? [];
    }

    // ------------------------------------------------------------------
    // Addendum 6 — baki PRU disemak, dan dilaporkan per pertandingan
    // ------------------------------------------------------------------

    public function test_a_transposed_pru_digit_is_caught_and_named_as_the_pru_band(): void
    {
        $form = $this->borangDun($this->takrifanParlimen());

        // Jalur PRN seimbang tepat dengan (A) = 100: 60 + 30 + 7 + 3.
        $this->undi($form, Borang14Vote::CONTEST_DUN, 1, 60);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 2, 30);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 90, 7);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 91, 3);

        // Jalur PRU: 45 tersalah kunci sebagai 54 (digit tertukar) —
        // 54 + 25 + 15 + 6 = 100? tidak: 100 ialah (A). Jumlah sebenar 109.
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 1, 54);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 2, 25);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 3, 15);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 90, 10);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 91, 5);

        $isu = $this->isu();

        $this->assertTrue(
            collect($isu)->contains(fn ($m) => str_contains($m, 'PRU · Parlimen JEMPOL') && str_contains($m, '(A) dijangka')),
            'Baki jalur PRU mesti disemak DAN dilabel sebagai jalur PRU. Dapat: '.json_encode($isu),
        );

        // Jalur PRN seimbang, jadi ia TIDAK boleh dituduh sama.
        $this->assertFalse(
            collect($isu)->contains(fn ($m) => str_contains($m, 'PRN · DUN GEMAS') && str_contains($m, '(A) dijangka')),
            'Jalur PRN seimbang tidak boleh dilaporkan. Dapat: '.json_encode($isu),
        );
    }

    public function test_a_balanced_concurrent_form_reports_no_balance_issue_on_either_band(): void
    {
        $form = $this->borangDun($this->takrifanParlimen());

        foreach ([[1, 60], [2, 30], [90, 7], [91, 3]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_DUN, $slot, $n);
        }
        // PRU: 40 + 25 + 20 + 10 + 5 = 100 = (A). Seimbang.
        foreach ([[1, 40], [2, 25], [3, 20], [90, 10], [91, 5]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, $slot, $n);
        }

        $this->assertSame([], $this->isu());
    }

    public function test_an_unkeyed_pru_band_is_unknown_not_zero(): void
    {
        $form = $this->borangDun($this->takrifanParlimen());

        // Hanya jalur PRN dikunci — jalur PRU masih kosong sepenuhnya.
        foreach ([[1, 60], [2, 30], [90, 7], [91, 3]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_DUN, $slot, $n);
        }

        $this->assertSame(
            [],
            $this->isu(),
            'Jalur PRU yang belum dikunci ialah TIDAK DIKETAHUI, bukan sifar undi — '
            .'melaporkannya akan menyalakan setiap baris setiap borang pada saat mod serentak dihidupkan.',
        );
    }

    public function test_a_single_contest_form_keeps_its_message_shape_unchanged(): void
    {
        $form = $this->borangDun();   // tiada pautan Parlimen

        $this->undi($form, Borang14Vote::CONTEST_DUN, 1, 60);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 2, 30);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 90, 0);   // sepatutnya 7
        $this->undi($form, Borang14Vote::CONTEST_DUN, 91, 3);

        $isu = $this->isu();

        $this->assertSame(['SK GEMAS — Saluran 1: (A) dijangka 93, dapat 100'], $isu);
        $this->assertStringNotContainsString('[', $isu[0], 'Borang satu pertandingan tidak boleh mendapat label jalur.');
    }

    // ------------------------------------------------------------------
    // Addendum 7 — pariti keluar, berjalan walaupun pada struktur manual
    // ------------------------------------------------------------------

    public function test_turnout_parity_fires_on_a_manual_structure_where_nothing_else_can(): void
    {
        // Ini mod NORMAL bagi borang serentak: panel Sunting Struktur (satu-
        // satunya cara menghidupkan mod serentak) sentiasa menulis
        // origin='manual', jadi semakan terhadap angka bercetak dilangkau.
        $form = $this->borangDun($this->takrifanParlimen(), origin: 'manual');

        // PRN keluar = 60 + 30 + 7 + 3 = 100.
        foreach ([[1, 60], [2, 30], [90, 7], [91, 3]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_DUN, $slot, $n);
        }
        // PRU keluar = 40 + 25 + 20 + 10 + 5 = 100 -> 99 (satu digit tersasar).
        foreach ([[1, 39], [2, 25], [3, 20], [90, 10], [91, 5]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, $slot, $n);
        }

        $isu = $this->isu();

        $this->assertCount(1, $isu, 'Dapat: '.json_encode($isu));
        $this->assertStringContainsString('Jum. Keluar tidak sama antara jalur', $isu[0]);
        $this->assertStringContainsString('PRN · DUN GEMAS 100', $isu[0]);
        $this->assertStringContainsString('PRU · Parlimen JEMPOL 99', $isu[0]);
    }

    public function test_turnout_parity_is_silent_when_the_two_bands_agree(): void
    {
        $form = $this->borangDun($this->takrifanParlimen(), origin: 'manual');

        foreach ([[1, 60], [2, 30], [90, 7], [91, 3]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_DUN, $slot, $n);
        }
        foreach ([[1, 40], [2, 25], [3, 20], [90, 10], [91, 5]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, $slot, $n);
        }

        $this->assertSame([], $this->isu());
    }

    public function test_turnout_parity_skips_a_saluran_where_one_band_has_no_candidate_cell_yet(): void
    {
        $form = $this->borangDun($this->takrifanParlimen(), origin: 'manual');

        foreach ([[1, 60], [2, 30], [90, 7], [91, 3]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_DUN, $slot, $n);
        }
        // Hanya (C) dikunci pada jalur PRU — jalur itu belum dimulakan.
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 90, 10);

        $this->assertSame(
            [],
            $this->isu(),
            'Jalur tanpa satu pun slot calon belum dimulakan — TIDAK DIKETAHUI, bukan keluar 10.',
        );
    }

    public function test_a_single_contest_form_never_runs_the_parity_check(): void
    {
        $form = $this->borangDun(origin: 'manual');   // tiada pautan

        foreach ([[1, 60], [2, 30]] as [$slot, $n]) {
            $this->undi($form, Borang14Vote::CONTEST_DUN, $slot, $n);
        }
        // Undi PRU yatim (mod serentak pernah dihidupkan lalu dimatikan).
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 1, 1);

        $this->assertSame([], $this->isu(), 'Borang tanpa pautan mesti kekal seperti hari ini.');
    }

    // ------------------------------------------------------------------
    // Addendum 2 — liputan per-pembaca pada borang dua pertandingan
    // ------------------------------------------------------------------

    public function test_the_pdf_prints_only_the_forms_own_contest(): void
    {
        $form = $this->borangDun($this->takrifanParlimen(), origin: 'manual');

        // Angka sengaja tidak mungkin bertindih: PRN 611/222, PRU 977/888.
        $this->undi($form, Borang14Vote::CONTEST_DUN, 1, 611);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 2, 222);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 1, 977);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 2, 888);

        $res = $this->actingAs($this->user())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
        ]));
        $res->assertOk();

        $teks = (new PdfParser())->parseContent($res->getContent())->getText();

        $this->assertStringContainsString('611', $teks);
        $this->assertStringContainsString('222', $teks);
        $this->assertStringNotContainsString('977', $teks, 'Undi PRU tidak boleh dicetak pada Borang 14 PRN.');
        $this->assertStringNotContainsString('888', $teks, 'Undi PRU tidak boleh dicetak pada Borang 14 PRN.');
    }

    public function test_the_scenario_mapper_maps_one_contest_at_a_time(): void
    {
        $form = $this->borangDun($this->takrifanParlimen(), origin: 'manual');

        $this->undi($form, Borang14Vote::CONTEST_DUN, 1, 611);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 2, 222);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 1, 977);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 2, 888);

        $mapper = new Borang14ScenarioMapper();

        // Lalai = pertandingan borang itu sendiri (PRN).
        $prn = $mapper->map($form);
        $this->assertSame(833, $prn['totals']['keluar'], '611 + 222 sahaja — undi PRU tidak boleh masuk.');
        $this->assertNotSame(2698, $prn['totals']['keluar'], 'Kiraan dua kali ganda merentas pertandingan.');
        $this->assertSame(611, $prn['totals']['undi']['PN']);

        // Diminta secara eksplisit = jalur PRU sahaja, dengan nama parti
        // borang DUN (mapper memetakan slot melalui parties borang yang
        // diberi — di sini kita membuktikan pengasingan undi, bukan nama).
        $pru = $mapper->map($form, Borang14Vote::CONTEST_PARLIMEN);
        $this->assertSame(1865, $pru['totals']['keluar'], '977 + 888 sahaja.');
    }
}
