<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tugasan 6a — muatan data() mesti membawa pertandingan Parlimen yang dipaut
 * supaya skrin dua jalur boleh melukis jalur PRU.
 *
 * Dua bahaya dipaku di sini:
 *   1. `penjuru` jalur PRU mesti datang daripada borang PARLIMEN, bukan borang
 *      DUN. Fikstur ini sengaja memberi penjuru yang BERLAINAN (DUN 2, Parlimen
 *      3) supaya sebarang percampuran gagal, bukan lulus secara kebetulan.
 *   2. Kunci sel kini membawa contest. Tanpa itu sel PRU dan PRN pada
 *      (pusat, saluran, slot) yang sama akan bertindih dalam keadaan frontend —
 *      kelas pepijat tulis-ganti senyap yang sama seperti key-drift dahulu.
 *
 * Dan satu jaminan keserasian ke belakang: borang TANPA pautan mesti
 * memulangkan muatan yang sama seperti hari ini — itu kes yang paling biasa
 * dan ia sudah berada di produksi.
 */
class Borang14SerentakKontesPayloadTest extends TestCase
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

        // Roll DPT supaya Borang14Reference memulangkan struktur untuk grid.
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '990101010101', 'nama' => 'PENGUNDI', 'kadun' => 'GEMAS',
            'daerah_mengundi' => 'PEKAN GEMAS', 'lokaliti' => 'SK GEMAS',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` — pincang
        // sedia ada; diatasi setempat seperti ujian lain dalam suite ini.
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123450099']);
    }

    private function borangDun(): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'status' => 'draft',
            'parties' => [['slot' => 1, 'nama' => 'PN'], ['slot' => 2, 'nama' => 'BN']],
        ]);
    }

    /** Penjuru 3 — SENGAJA berbeza daripada penjuru 2 borang DUN. */
    private function borangParlimen(): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->bandar->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'status' => 'draft',
            'parties' => [
                ['slot' => 1, 'nama' => 'BN'],
                ['slot' => 2, 'nama' => 'PN'],
                ['slot' => 3, 'nama' => 'PH'],
            ],
        ]);
    }

    private function undi(Borang14Form $form, string $contest, int $slot, int $undi): void
    {
        Borang14Vote::create([
            'borang14_form_id' => $form->id, 'contest' => $contest,
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => $slot, 'undi' => $undi,
        ]);
    }

    private function ambilData(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
        ]));
    }

    // ---- (a) borang tanpa pautan ------------------------------------------

    public function test_borang_tanpa_pautan_tidak_membawa_kontes_parlimen(): void
    {
        $form = $this->borangDun();
        $this->undi($form, Borang14Vote::CONTEST_DUN, 1, 63);

        $res = $this->ambilData();

        $res->assertOk();
        // Kunci itu mesti TIADA langsung, bukan sekadar null: muatan satu
        // pertandingan mesti kekal serupa-bit dengan hari ini — kes ini sudah
        // di produksi dan ia yang paling biasa.
        $this->assertArrayNotHasKey(
            'kontes_parlimen',
            $res->json(),
            'Borang satu pertandingan mesti kekal seperti hari ini — kes ini sudah di produksi.',
        );
    }

    // ---- (b) borang berpaut ------------------------------------------------

    public function test_borang_berpaut_membawa_penjuru_parti_nama_dan_undi_parlimen(): void
    {
        $parlimen = $this->borangParlimen();
        $form = $this->borangDun();
        $form->update(['borang14_form_parlimen_id' => $parlimen->id]);

        // PRN: 63 + 224.  PRU: 93 + 27 + 204.
        $this->undi($form, Borang14Vote::CONTEST_DUN, 1, 63);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 2, 224);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 1, 93);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 2, 27);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 3, 204);

        $res = $this->ambilData();
        $res->assertOk();

        $this->assertSame($parlimen->id, $res->json('kontes_parlimen.id'));

        // MEDAN PALING KRITIKAL: penjuru jalur PRU datang daripada borang
        // Parlimen (3), bukan borang DUN (2).
        $this->assertSame(3, $res->json('kontes_parlimen.penjuru'), 'penjuru jalur PRU mesti datang daripada borang Parlimen.');
        $this->assertSame(2, $res->json('form.penjuru'), 'penjuru borang DUN tidak boleh berubah.');

        $this->assertSame(['BN', 'PN', 'PH'], array_column($res->json('kontes_parlimen.parties'), 'nama'));
        $this->assertSame('JEMPOL', $res->json('kontes_parlimen.kawasan_nama'));

        // Undi PRU bagi borang DUN INI, berkunci pada contest.
        $this->assertSame(93, $res->json('kontes_parlimen.votes.parlimen|SK GEMAS|3|1'));
        $this->assertSame(27, $res->json('kontes_parlimen.votes.parlimen|SK GEMAS|3|2'));
        $this->assertSame(204, $res->json('kontes_parlimen.votes.parlimen|SK GEMAS|3|3'));
        $this->assertCount(3, $res->json('kontes_parlimen.votes'));
    }

    // ---- (c) peta votes DUN kekal PRN sahaja -------------------------------

    public function test_peta_votes_dun_mengandungi_undi_prn_sahaja(): void
    {
        $parlimen = $this->borangParlimen();
        $form = $this->borangDun();
        $form->update(['borang14_form_parlimen_id' => $parlimen->id]);

        $this->undi($form, Borang14Vote::CONTEST_DUN, 1, 63);
        $this->undi($form, Borang14Vote::CONTEST_DUN, 2, 224);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 1, 93);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 2, 27);

        $votes = $this->ambilData()->json('votes');

        $this->assertSame([
            'dun|SK GEMAS|3|1' => 63,
            'dun|SK GEMAS|3|2' => 224,
        ], $votes, 'Peta votes borang DUN mesti membawa undi PRN sahaja, berkunci pada contest.');
    }

    // ---- kunci sel: PRU dan PRN pada kedudukan sama tidak bertindih --------

    public function test_sel_pru_dan_prn_pada_kedudukan_sama_tidak_bertindih(): void
    {
        $parlimen = $this->borangParlimen();
        $form = $this->borangDun();
        $form->update(['borang14_form_parlimen_id' => $parlimen->id]);

        // Slot 1 pada pusat/saluran YANG SAMA bagi kedua-dua pertandingan.
        $this->undi($form, Borang14Vote::CONTEST_DUN, 1, 63);
        $this->undi($form, Borang14Vote::CONTEST_PARLIMEN, 1, 93);

        $res = $this->ambilData();

        $this->assertSame(63, $res->json('votes.dun|SK GEMAS|3|1'));
        $this->assertSame(93, $res->json('kontes_parlimen.votes.parlimen|SK GEMAS|3|1'));
        $this->assertNull($res->json('votes.parlimen|SK GEMAS|3|1'), 'Undi PRU tidak boleh bocor ke dalam peta jalur PRN.');
    }
}
