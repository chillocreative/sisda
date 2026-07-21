<?php
// tests/Feature/Borang14StrukturManualTest.php
//
// Struktur Borang 14 yang dibina dengan tangan, untuk PR akan datang yang
// tiada DPT dan tiada scoresheet. Dua bahaya dikunci di sini:
//   1. baris YATIM — undi tersimpan di bawah kunci yang tiada sesiapa baca
//      (punca pepijat produksi Julai 2026: 4,471 undi memapar 0);
//   2. undi hilang SENYAP apabila pusat dinamakan semula atau dibuang.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Borang14StrukturService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14StrukturManualTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $bandar = Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'Juasseh', 'bandar_id' => $bandar->id]);
    }

    private function user(string $role = 'super_admin', array $over = []): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia ada).
        return User::factory()->create(array_merge([
            'role' => $role,
            'telephone' => '01277'.random_int(10000, 99999),
        ], $over));
    }

    /** Struktur manual dua pusat: SK TENGKEK (2 saluran), SK JEMAPOH (1 saluran). */
    private function manualStructure(): array
    {
        return (new Borang14StrukturService)->expand([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 2],
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ], false, true);
    }

    private function form(array $structure, string $status = 'draft'): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'status' => $status, 'source' => 'manual',
            'parties' => [['slot' => 1, 'nama' => 'PN'], ['slot' => 2, 'nama' => 'BN']],
            'structure' => $structure,
        ]);
    }

    public function test_manual_form_reports_no_crosscheck_issues(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $form->id]));

        $res->assertOk();
        // Baris manual tiada (A) bercetak untuk dibandingkan. Satu amaran di
        // sini bermakna borang yang diisi dengan BETUL kelihatan rosak.
        $this->assertSame([], $res->json('form.crosscheck_issues'));
    }

    public function test_manual_structure_is_reported_as_its_own_source(): void
    {
        $form = $this->form($this->manualStructure());

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $form->id]));

        $res->assertOk();
        $this->assertSame('manual', $res->json('reference.source'));
        $this->assertTrue($res->json('hasData'));
    }

    /** @return array<string,mixed> */
    private function payload(array $pusat, bool $undiAwal = false, bool $undiPos = true): array
    {
        return [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => $pusat, 'undi_awal' => $undiAwal, 'undi_pos' => $undiPos,
        ];
    }

    public function test_saving_a_structure_creates_the_form_and_breaks_the_dead_end(): void
    {
        // Kerusi tanpa DPT dan tanpa scoresheet — sebelum ini buntu sepenuhnya.
        $this->assertSame(0, Borang14Form::count());

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 3],
        ]))->assertOk()->assertJson(['ok' => true]);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'));
        $saluran = $res->json('reference.daerah_mengundi.0.pusat_mengundi.0.saluran');
        $this->assertCount(3, $saluran, 'Tiga saluran yang ditaip mesti kekal tiga.');
    }

    public function test_votes_written_after_a_manual_structure_are_readable_again(): void
    {
        // UJIAN ANTI-YATIM. Inilah bentuk pepijat produksi Julai 2026: undi
        // ditulis di bawah satu set kunci, grid dibina daripada set yang lain,
        // setiap sel memapar 0 walaupun undi selamat dalam DB.
        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 2],
        ]))->assertOk();

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
            'penjuru' => 2, 'pusat' => 'SK TENGKEK', 'saluran' => '2', 'slot' => 1, 'undi' => 250,
        ])->assertOk();

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]));

        $this->assertSame(250, $res->json('votes.SK TENGKEK|2|1'));
    }

    public function test_renaming_a_pusat_carries_its_votes_across(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '2', 'slot' => 1, 'undi' => 111]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SEKOLAH KEBANGSAAN TENGKEK', 'saluran_count' => 2],
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ]))->assertOk();

        $this->assertSame(0, $form->votes()->where('pusat', 'SK TENGKEK')->count());
        $this->assertSame(361, (int) $form->votes()->where('pusat', 'SEKOLAH KEBANGSAAN TENGKEK')->sum('undi'));
    }

    public function test_removing_a_pusat_deletes_only_its_own_votes(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK JEMAPOH', 'saluran' => '1', 'slot' => 1, 'undi' => 90]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 1, 'undi' => 12]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ]))->assertOk();

        $this->assertSame(0, $form->votes()->where('pusat', 'SK TENGKEK')->count());
        $this->assertSame(90, (int) $form->votes()->where('pusat', 'SK JEMAPOH')->sum('undi'));
        $this->assertSame(12, (int) $form->votes()->where('saluran', 'UNDI POS')->sum('undi'));
    }

    public function test_shrinking_the_saluran_count_deletes_the_dropped_saluran(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '2', 'slot' => 1, 'undi' => 111]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 1],
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ]))->assertOk();

        $this->assertSame(250, (int) $form->votes()->where('pusat', 'SK TENGKEK')->sum('undi'));
    }

    public function test_a_snapshot_is_written_before_the_edit(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ]))->assertOk();

        $snap = $form->snapshots()->latest('id')->first();
        $this->assertNotNull($snap, 'Suntingan yang memadam undi mesti boleh dipulihkan.');
        $this->assertSame('before_structure_edit', $snap->reason);
        $this->assertSame(250, (int) collect($snap->votes)->firstWhere('pusat', 'SK TENGKEK')['undi']);
    }

    public function test_published_forms_reject_structure_edits(): void
    {
        $this->form($this->manualStructure(), 'published');

        $this->actingAs($this->user('super_admin'))
            ->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK LAIN', 'saluran_count' => 1],
            ]))
            ->assertForbidden();
    }

    public function test_ordinary_users_cannot_edit_the_structure(): void
    {
        $this->actingAs($this->user('user'))
            ->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK A', 'saluran_count' => 1],
            ]))
            ->assertForbidden();

        $this->assertSame(0, Borang14Form::count());
    }

    public function test_duplicate_pusat_names_are_rejected(): void
    {
        // Dua pusat senama akan berkongsi kunci undi yang sama — setiap sel
        // kedua-duanya akan menulis atas satu sama lain, senyap.
        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK SAMA', 'saluran_count' => 1],
                ['row_id' => 'pm_b', 'dm' => 'DM', 'pusat' => 'SK SAMA', 'saluran_count' => 1],
            ]))
            ->assertStatus(422);
    }

    public function test_renaming_a_pusat_onto_another_deleted_pusat_name_is_rejected(): void
    {
        // pm_a dinamakan semula ke 'SK JEMAPOH' (nama pm_b) SAMBIL pm_b
        // dibuang daripada senarai baharu. Senarai baharu sendiri tiada
        // pendua, tetapi 'SK JEMAPOH' LAMA masih memegang undi — jika
        // simpanan diteruskan, undi itu tertulis atas (atau bertembung
        // dengan) kunci pusat yang baharu dinamakan semula, senyap.
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK JEMAPOH', 'saluran' => '1', 'slot' => 1, 'undi' => 90]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 2],
        ]))->assertStatus(422);

        // Transaksi tidak sepatutnya berjalan langsung — kedua-dua pusat
        // mesti kekal dengan jumlah undi asal.
        $this->assertSame(250, (int) $form->votes()->where('pusat', 'SK TENGKEK')->sum('undi'));
        $this->assertSame(90, (int) $form->votes()->where('pusat', 'SK JEMAPOH')->sum('undi'));
    }

    public function test_swapping_two_pusat_names_is_rejected(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK JEMAPOH', 'saluran' => '1', 'slot' => 1, 'undi' => 90]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 2],
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 1],
        ]))->assertStatus(422);

        $this->assertSame(250, (int) $form->votes()->where('pusat', 'SK TENGKEK')->sum('undi'));
        $this->assertSame(90, (int) $form->votes()->where('pusat', 'SK JEMAPOH')->sum('undi'));
    }

    public function test_trailing_whitespace_in_pusat_name_is_trimmed_before_storage(): void
    {
        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK ', 'saluran_count' => 1],
        ]))->assertOk();

        $form = Borang14Form::firstOrFail();
        $this->assertSame('SK TENGKEK', $form->structure['rows'][0]['pusat'], 'Ruang berikutan mesti dipangkas sebelum disimpan.');

        // Undi ditulis di bawah nama yang dipangkas mesti terbaca semula —
        // membuktikan kunci tulis dan kunci baca kini sepadan.
        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
            'penjuru' => 2, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 77,
        ])->assertOk();

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]));

        $this->assertSame(77, $res->json('votes.SK TENGKEK|1|1'));
    }

    public function test_undi_pos_false_deletes_the_undi_pos_row_votes(): void
    {
        // manualStructure() menghidupkan UNDI POS (undi_pos: true) — semua
        // ujian lain menyimpan payload dengan lalai undi_pos: true, jadi
        // separuh "dibuang apabila bendera dimatikan" tidak pernah diuji.
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 1, 'undi' => 12]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 2],
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ], false, false))->assertOk();

        $this->assertSame(0, $form->votes()->where('saluran', 'UNDI POS')->count());
    }
}
