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
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * Kadun 41 ialah satu-satunya kerusi yang mempunyai fail JSON kurasi
     * (resources/data/borang14/kadun-41.json). Grid bagi kerusi begini DIBINA
     * daripada fail itu, BUKAN daripada struktur borang — rujuk keutamaan di
     * resolveReference(): JSON kurasi tiada kunci 'source', jadi pindaan
     * struktur tidak pernah dirujuk.
     */
    private function curatedKadun(): Kadun
    {
        $k = new Kadun(['nama' => 'Buloh Kasap', 'bandar_id' => $this->kadun->bandar_id]);
        $k->id = 41;
        $k->save();

        return $k;
    }

    /** @return array<string,mixed> */
    private function curatedPayload(array $pusat): array
    {
        return [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->curatedKadun()->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => $pusat, 'undi_awal' => false, 'undi_pos' => true,
        ];
    }

    // ---------------------------------------------------------------------
    // Grid yang dipaparkan TIDAK selalunya datang daripada struktur borang
    // ini sendiri. Apabila ia datang daripada JSON kurasi / anggaran DPT /
    // warisan, borang ini sendiri mempunyai structure = null, jadi panel
    // dibuka KOSONG di atas grid yang penuh. Menyimpan ketika itu memadam
    // setiap undi yang tidak ditaip semula — dan bagi kerusi kurasi struktur
    // yang disimpan itu tidak pernah dipaparkan pun. Sebab itu penyuntingan
    // dikunci pada ASAL grid, bukan sekadar peranan pengguna.
    // ---------------------------------------------------------------------

    public function test_editing_is_blocked_when_the_grid_comes_from_a_curated_reference(): void
    {
        $kadun = $this->curatedKadun();

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'));
        // Super admin, borang belum diterbitkan — disekat semata-mata kerana
        // grid itu bukan milik struktur borang ini.
        $this->assertFalse($res->json('boleh_sunting_struktur'));
    }

    public function test_saving_is_rejected_when_the_grid_comes_from_a_curated_reference(): void
    {
        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur'), $this->curatedPayload([
                ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK BARU', 'saluran_count' => 1],
            ]))
            ->assertStatus(422);

        // Tiada borang dicipta — penolakan mesti berlaku SEBELUM firstOrCreate.
        $this->assertSame(0, Borang14Form::count());
    }

    public function test_preview_is_rejected_when_the_grid_comes_from_a_curated_reference(): void
    {
        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur.kesan'), $this->curatedPayload([
                ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK BARU', 'saluran_count' => 1],
            ]))
            ->assertStatus(422);
    }

    public function test_editing_is_allowed_when_the_grid_is_this_forms_own_structure(): void
    {
        // Kawalan positif: sekatan di atas mesti kerana ASAL grid, bukan
        // kerana ia menyekat semua orang.
        $form = $this->form($this->manualStructure());

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $form->id]));

        $res->assertOk();
        $this->assertTrue($res->json('boleh_sunting_struktur'));
    }

    public function test_an_inherited_grid_is_editable_and_the_panel_is_seeded_from_it(): void
    {
        // PR terdahulu bagi kerusi yang SAMA — grid PR baharu diwarisi
        // daripadanya, jadi borang baharu itu sendiri tiada structure.
        $lama = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'manual', 'parties' => [],
            'structure' => $this->manualStructure(),
        ]);
        $this->assertNotNull($lama->id);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'));
        // MESTI boleh disunting: sebaik borang 2027 menyimpan struktur
        // sendiri, cabang warisan tidak berjalan lagi, jadi suntingan itu
        // benar-benar dipaparkan. Menyekatnya membunuh kes "PR akan datang".
        $this->assertTrue($res->json('boleh_sunting_struktur'));
        // Dan panel MESTI disemai daripada grid yang diwarisi, bukan kosong —
        // panel kosong di atas grid penuh ialah punca pemadaman senyap.
        $this->assertSame(
            ['SK TENGKEK', 'SK JEMAPOH'],
            collect($res->json('struktur.pusat'))->pluck('pusat')->all(),
        );
        $this->assertSame(2, $res->json('struktur.pusat.0.saluran_count'));
        // Grid warisan MEMAPARKAN baris undi pos, jadi panel mesti dibuka
        // dengan kotak itu BERTANDA. Kotak kosong di atas baris yang
        // dipaparkan bermakna simpanan seterusnya memadamnya.
        $this->assertTrue($res->json('struktur.undi_pos'));
    }

    public function test_editing_an_inherited_grid_does_not_delete_the_postal_votes(): void
    {
        // PR terdahulu memberi grid; PR baharu ada BORANG (kerana undi sudah
        // ditulis) tetapi masih tiada structure sendiri — jadi asal kekal
        // 'warisan' dan panel disemai daripada grid yang diwarisi.
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'manual', 'parties' => [],
            'structure' => $this->manualStructure(),
        ]);

        $baharu = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'status' => 'draft', 'source' => 'manual', 'parties' => [],
            'structure' => null,
        ]);
        Borang14Vote::create(['borang14_form_id' => $baharu->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 1, 'undi' => 88]);
        Borang14Vote::create(['borang14_form_id' => $baharu->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 500]);

        // Ambil keadaan panel SEPERTI YANG DILIHAT PENGGUNA, kemudian buat
        // satu suntingan yang tidak menyentuh baris pos langsung.
        $data = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]))->json('struktur');

        $pusat = $data['pusat'];
        $pusat[0]['saluran_count'] = 3;

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => $pusat,
            'undi_awal' => $data['undi_awal'], 'undi_pos' => $data['undi_pos'],
            'undi_awal_label' => $data['undi_awal_label'], 'undi_pos_label' => $data['undi_pos_label'],
            'lain_lain' => $data['lain_lain'],
        ])->assertOk();

        // Undi pos MESTI selamat: pengguna tidak pernah meminta ia dipadam.
        $this->assertSame(88, Borang14Vote::where('borang14_form_id', $baharu->id)->where('saluran', 'UNDI POS')->value('undi'));
        $this->assertSame(500, Borang14Vote::where('borang14_form_id', $baharu->id)->where('pusat', 'SK TENGKEK')->value('undi'));
    }

    public function test_a_dpt_seeded_panel_is_saveable_and_does_not_misattribute_votes(): void
    {
        // Anggaran DPT mengumpul [dm][lokaliti] dan menggantikan lokaliti
        // kosong dengan 'TIADA LOKALITI' — SEKALI BAGI SETIAP Daerah
        // Mengundi. Kunci undi ialah pusat|saluran tanpa komponen DM, jadi
        // baris-baris itu sememangnya sel yang sama.
        foreach ([['PELANGAI', null], ['SENALING', null], ['PELANGAI', 'KG TENGKEK']] as [$dm, $lokaliti]) {
            DB::table('pangkalan_data_pengundi')->insert([
                'nama' => 'PENGUNDI '.$dm, 'no_ic' => (string) random_int(100000000000, 999999999999),
                'kadun' => 'JUASSEH', 'daerah_mengundi' => $dm, 'lokaliti' => $lokaliti,
                'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]));
        $res->assertOk();
        $this->assertSame('dpt_estimate', $res->json('reference.source'));
        // Kerusi DPT MESTI boleh disunting — membaiki anggaran 1-saluran/pusat
        // ialah antara sebab ciri ini wujud.
        $this->assertTrue($res->json('boleh_sunting_struktur'));

        $data = $res->json('struktur');
        $nama = collect($data['pusat'])->pluck('pusat');
        // SATU 'TIADA LOKALITI', bukan dua. Panel yang menyerahkan nama
        // berulang menghasilkan muatan yang endpoint simpan sendiri tolak.
        $this->assertSame($nama->unique()->count(), $nama->count(), 'Panel DPT tidak boleh mengandungi nama Pusat berulang: '.$nama->implode(', '));

        // Undi ditaip pada sel yang dikongsi itu.
        $form = Borang14Form::firstOrCreate(
            ['kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027],
            ['penjuru' => 2, 'parties' => [], 'status' => 'draft', 'source' => 'manual'],
        );
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'TIADA LOKALITI', 'saluran' => '1', 'slot' => 1, 'undi' => 80]);

        // Naikkan satu pusat kepada 3 saluran — kes penggunaan yang dinamakan
        // spesifikasi — tanpa menamakan semula apa-apa.
        $pusat = $data['pusat'];
        $i = collect($pusat)->search(fn ($p) => $p['pusat'] === 'KG TENGKEK');
        $pusat[$i]['saluran_count'] = 3;

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => $pusat,
            'undi_awal' => $data['undi_awal'], 'undi_pos' => $data['undi_pos'],
            'undi_awal_label' => $data['undi_awal_label'], 'undi_pos_label' => $data['undi_pos_label'],
            'lain_lain' => $data['lain_lain'],
        ])->assertOk();

        // Undi kekal di bawah kunci asalnya — tiada penamaan semula berlaku.
        $this->assertSame(80, Borang14Vote::where('borang14_form_id', $form->id)
            ->where('pusat', 'TIADA LOKALITI')->value('undi'));

        // Dan saluran 2 & 3 benar-benar dipaparkan selepas simpanan: struktur
        // mengalahkan anggaran DPT (spesifikasi :159).
        $selepas = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]));
        $dm = collect($selepas->json('reference.daerah_mengundi'))
            ->flatMap(fn ($d) => $d['pusat_mengundi'])->firstWhere('nama', 'KG TENGKEK');
        $this->assertCount(3, $dm['saluran']);
    }

    public function test_a_dead_end_seat_with_votes_but_no_reference_can_still_save_a_structure(): void
    {
        // saveVote() melakukan firstOrCreate, jadi menaip SATU sel pada kerusi
        // buntu mencipta borang yang ada undi tetapi TIADA structure, dan
        // tiada rujukan langsung (tiada kurasi, tiada DPT, tiada warisan).
        // Sebelum pintasan dalam assertPusatNamesUsable(), simpanan struktur
        // PERTAMA bagi borang begini ditolak selama-lamanya: setiap nama yang
        // ditaip sudah wujud dalam borang14_votes tetapi tiada dalam struktur,
        // jadi ia dikira berlanggar dengan dirinya sendiri.
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'status' => 'draft', 'source' => 'manual', 'parties' => [],
            'structure' => null,
        ]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 320]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 1],
        ]))->assertOk();

        // Dan undi yang sudah ditaip mesti KEKAL — nama itu tidak berubah.
        $this->assertSame(320, Borang14Vote::where('borang14_form_id', $form->id)
            ->where('pusat', 'SK TENGKEK')->value('undi'));
    }

    public function test_renaming_a_pusat_on_a_seeded_panel_moves_its_votes_instead_of_deleting_them(): void
    {
        // Suntingan PERTAMA panel yang disemai daripada rujukan: borang ini
        // tiada structure sendiri, jadi renameMap() tiada garis dasar dan
        // menamakan semula dilaksanakan sebagai padam-lama + tambah-baharu.
        // Garis dasar SEBENARNYA ada — grid yang disemai — jadi undi
        // sepatutnya BERPINDAH, bukan mati.
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'manual', 'parties' => [],
            'structure' => $this->manualStructure(),
        ]);

        $baharu = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'status' => 'draft', 'source' => 'manual', 'parties' => [],
            'structure' => null,
        ]);
        Borang14Vote::create(['borang14_form_id' => $baharu->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 4471]);

        $data = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]))->json('struktur');

        $pusat = $data['pusat'];
        $i = collect($pusat)->search(fn ($p) => $p['pusat'] === 'SK TENGKEK');
        $pusat[$i]['pusat'] = 'SEKOLAH KEBANGSAAN TENGKEK';

        $badan = [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => $pusat,
            'undi_awal' => $data['undi_awal'], 'undi_pos' => $data['undi_pos'],
            'undi_awal_label' => $data['undi_awal_label'], 'undi_pos_label' => $data['undi_pos_label'],
            'lain_lain' => $data['lain_lain'],
        ];

        // Pratonton mesti melaporkan SIFAR kehilangan — tiada apa yang dipadam.
        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur.kesan'), $badan)
            ->assertOk()->assertJson(['baris' => 0, 'undi' => 0]);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur'), $badan)->assertOk();

        // Undi BERPINDAH ke nama baharu, bukan hilang.
        $this->assertSame(4471, Borang14Vote::where('borang14_form_id', $baharu->id)
            ->where('pusat', 'SEKOLAH KEBANGSAAN TENGKEK')->value('undi'));
        $this->assertSame(0, Borang14Vote::where('borang14_form_id', $baharu->id)
            ->where('pusat', 'SK TENGKEK')->count());
    }

    public function test_saving_preserves_votes_stored_under_a_non_canonical_postal_label(): void
    {
        // Struktur berbentuk SCORESHEET: label undi pos ialah apa yang dibaca
        // daripada sheet, dan saluran pertama KOSONG (kes produksi sebenar).
        // Undi dikunci pada rentetan itu.
        $form = $this->form(['rows' => [
            ['dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran' => ''],
            ['dm' => '', 'pusat' => '', 'saluran' => 'UNDI POS AWAL'],
        ]]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '', 'slot' => 1, 'undi' => 411]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS AWAL', 'slot' => 1, 'undi' => 37]);

        // Buka panel, tambah SATU saluran, simpan — tanpa menyentuh apa-apa lagi.
        $struktur = (new Borang14StrukturService)->collapse($form->structure);
        $pusat = $struktur['pusat'];
        $pusat[0]['saluran_count'] = 2;

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => $pusat,
            'undi_awal' => $struktur['undi_awal'], 'undi_pos' => $struktur['undi_pos'],
            'undi_awal_label' => $struktur['undi_awal_label'],
            'undi_pos_label' => $struktur['undi_pos_label'],
        ])->assertOk();

        // KEDUA-DUA undi mesti selamat. Sebelum pembetulan ini, expand()
        // menomborkan semula saluran kosong menjadi '1' dan menulis semula
        // label pos menjadi literal berkanun — kedua-dua kunci hanyut dan
        // survivingKeys() memadamnya sebagai yatim.
        $this->assertSame(411, Borang14Vote::where('pusat', 'SK TENGKEK')->where('saluran', '')->value('undi'));
        $this->assertSame(37, Borang14Vote::where('saluran', 'UNDI POS AWAL')->value('undi'));
        $this->assertSame(2, Borang14Vote::where('borang14_form_id', $form->id)->count());
    }

    public function test_a_blank_daerah_mengundi_is_rejected(): void
    {
        // Borang14ScenarioMapper MELANGKAU setiap undi yang Pusatnya tiada DM
        // ("jangan reka DM"). Bagi kerusi manual yang tiada rujukan lain, itu
        // bermakna Analisa/AI menerbitkan "keputusan kerusi" yang sebenarnya
        // undi pos semata-mata. Halang di pintu masuk.
        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => '', 'pusat' => 'SK TENGKEK', 'saluran_count' => 1],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('pusat.0.dm');
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
        // Middleware sejagat TrimStrings turut memangkas array bersarang dalam
        // request, jadi ujian ini akan HIJAU dengan atau tanpa pangkasan
        // peringkat pengawal (controller) yang ia sepatutnya menguji. Ia
        // dimatikan di sini supaya ujian ini benar-benar menguji trim() dalam
        // simpanStruktur(), bukan middleware global.
        $this->actingAs($this->user())
            ->withoutMiddleware(TrimStrings::class)
            ->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
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

    public function test_renaming_onto_a_name_that_exists_only_in_the_votes_table_is_rejected(): void
    {
        // saveVote() menulis baris undi bagi MANA-MANA rentetan pusat tanpa
        // menyemaknya terhadap struktur borang — jadi satu baris undi boleh
        // wujud di bawah nama yang TIADA langsung dalam $form->structure
        // (contohnya, kekal daripada pusat yang sudah dibuang sebelum ini).
        // Guard perlanggaran mesti tetap menyekat penamaan semula ke atas
        // nama "yatim" sebegini, bukan sahaja nama yang wujud dalam struktur
        // semasa — jika tidak, sama ada UPDATE bertembung dengan indeks unik
        // (500) atau undi yatim itu bergabung senyap ke dalam pusat baharu.
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK ORPHAN', 'saluran' => '1', 'slot' => 1, 'undi' => 99]);

        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK ORPHAN', 'saluran_count' => 2],
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ]))->assertStatus(422);

        // Undi yatim, dan undi pusat lain, mesti kekal tidak disentuh
        // selepas percubaan simpan ditolak.
        $this->assertSame(99, (int) $form->votes()->where('pusat', 'SK ORPHAN')->sum('undi'));
        $this->assertSame(250, (int) $form->votes()->where('pusat', 'SK TENGKEK')->sum('undi'));
    }

    public function test_renaming_with_case_only_difference_onto_existing_name_is_rejected(): void
    {
        // NOTA: Ujian ini lulus pada SQLite (yang sensitif huruf besar/kecil)
        // atas sebab perbandingan sisi-PHP (nameKey()) yang dinormalisasi —
        // BUKAN kerana SQLite meniru collation utf8mb4_unicode_ci MySQL.
        // Ia mengunci kelakuan guard sisi-PHP sahaja; ia TIDAK membuktikan
        // indeks unik MySQL sebenar akan berkelakuan sama (itu memerlukan
        // MySQL sebenar, di luar skop CI).
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK JEMAPOH', 'saluran' => '1', 'slot' => 1, 'undi' => 90]);

        // pm_a dinamakan semula daripada 'SK TENGKEK' kepada 'sk jemapoh'
        // (beza huruf besar/kecil sahaja) SAMBIL pm_b ('SK JEMAPOH') dibuang
        // daripada senarai baharu.
        $this->actingAs($this->user())->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'sk jemapoh', 'saluran_count' => 2],
        ]))->assertStatus(422);

        $this->assertSame(250, (int) $form->votes()->where('pusat', 'SK TENGKEK')->sum('undi'));
        $this->assertSame(90, (int) $form->votes()->where('pusat', 'SK JEMAPOH')->sum('undi'));
    }

    public function test_admin_scoped_to_another_bandar_gets_403_not_422_for_colliding_payload(): void
    {
        // Peranan 'user' sudah disekat lebih awal oleh middleware laluan
        // ('admin' — route group pilihanraya.*), jadi ia tidak pernah sampai
        // ke pengawal langsung. Kes yang SEBENARNYA sampai ke
        // bolehSuntingStruktur() ialah seorang 'admin' yang disemak per
        // rekod terhadap Bandar-nya sendiri: admin bagi Bandar LAIN lulus
        // middleware tetapi masih tiada kebenaran ke atas kerusi ini.
        //
        // Guard pendua/perlanggaran nama TIDAK boleh berjalan sebelum
        // semakan kebenaran itu — jika tidak, pemanggil yang tiada kebenaran
        // menyunting borang ini menerima 422 (isi kandungan tidak sah) dan
        // bukannya 403 (disekat), yang membocorkan maklumat tentang
        // kandungan borang kepada seseorang yang tidak sepatutnya
        // menyentuhnya langsung.
        $bandarLain = Bandar::create(['nama' => 'Bandar Lain', 'negeri_id' => $this->kadun->bandar->negeri_id]);
        $adminLain = $this->user('admin', ['bandar_id' => $bandarLain->id]);

        $this->actingAs($adminLain)
            ->postJson(route('pilihanraya.borang-14.struktur'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK SAMA', 'saluran_count' => 1],
                ['row_id' => 'pm_b', 'dm' => 'DM', 'pusat' => 'SK SAMA', 'saluran_count' => 1],
            ]))
            ->assertForbidden();

        $this->assertSame(0, Borang14Form::count());
    }

    public function test_preview_reports_exactly_what_the_edit_would_destroy(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 2, 'undi' => 111]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK JEMAPOH', 'saluran' => '1', 'slot' => 1, 'undi' => 90]);

        $res = $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur.kesan'), $this->payload([
                ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
            ]));

        $res->assertOk();
        $this->assertSame(2, $res->json('baris'));
        $this->assertSame(361, $res->json('undi'));
        $this->assertSame(['SK TENGKEK'], $res->json('pusat'));
    }

    public function test_preview_writes_nothing(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur.kesan'), $this->payload([]))
            ->assertOk();

        $this->assertSame(250, (int) $form->votes()->sum('undi'));
        $this->assertSame(0, $form->snapshots()->count());
    }

    public function test_a_rename_destroys_nothing(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);

        $res = $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur.kesan'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'NAMA BAHARU', 'saluran_count' => 2],
                ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
            ]));

        $res->assertOk();
        $this->assertSame(0, $res->json('baris'));
        $this->assertSame(0, $res->json('undi'));
    }

    public function test_preview_with_no_existing_form_reports_zeros_not_an_error(): void
    {
        // Kerusi belum pernah disimpan langsung — tiada undi wujud untuk
        // dipadam, jadi sifar ialah jawapan JUJUR, bukan ralat 404/422.
        $this->assertSame(0, Borang14Form::count());

        $res = $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur.kesan'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 2],
            ]));

        $res->assertOk();
        $this->assertSame(0, $res->json('baris'));
        $this->assertSame(0, $res->json('undi'));
        $this->assertSame([], $res->json('pusat'));

        // Dan mesti benar-benar tidak wujud borang — pratonton bukan simpanan.
        $this->assertSame(0, Borang14Form::count());
    }

    public function test_preview_rejects_a_payload_the_save_would_also_reject(): void
    {
        // Dua pusat senama dalam payload yang sama: simpanStruktur() menolak
        // ini dengan 422 (test_duplicate_pusat_names_are_rejected). Pratonton
        // MESTI bersetuju — jika tidak, dialog memaparkan angka yakin bagi
        // muatan yang akan gagal sebaik sahaja pengguna menekan Simpan.
        $res = $this->actingAs($this->user())
            ->postJson(route('pilihanraya.borang-14.struktur.kesan'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK SAMA', 'saluran_count' => 1],
                ['row_id' => 'pm_b', 'dm' => 'DM', 'pusat' => 'SK SAMA', 'saluran_count' => 1],
            ]));

        $res->assertStatus(422);
        $this->assertSame(0, Borang14Form::count());
    }

    public function test_preview_gives_403_not_422_for_unauthorized_colliding_payload(): void
    {
        // Susunan guard mesti sepadan dengan simpanStruktur(): kebenaran
        // dahulu, guard pendua/perlanggaran kemudian. Admin bagi Bandar LAIN
        // menghantar payload yang JUGA berlanggar (pendua nama) — jawapan
        // mesti 403 (disekat), bukan 422 (isi kandungan tidak sah), supaya
        // pemanggil yang tiada kebenaran tidak menerima maklum balas tentang
        // kandungan borang langsung.
        $bandarLain = Bandar::create(['nama' => 'Bandar Lain', 'negeri_id' => $this->kadun->bandar->negeri_id]);
        $adminLain = $this->user('admin', ['bandar_id' => $bandarLain->id]);

        $this->actingAs($adminLain)
            ->postJson(route('pilihanraya.borang-14.struktur.kesan'), $this->payload([
                ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK SAMA', 'saluran_count' => 1],
                ['row_id' => 'pm_b', 'dm' => 'DM', 'pusat' => 'SK SAMA', 'saluran_count' => 1],
            ]))
            ->assertForbidden();
    }

    public function test_data_returns_the_collapsed_structure_for_the_editor(): void
    {
        $form = $this->form($this->manualStructure());

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $form->id]));

        $res->assertOk();
        $this->assertSame(
            // saluran_labels dihantar ke panel supaya ia boleh pulang ke
            // expand() tanpa diubah — itulah yang mengekalkan kunci undi.
            [['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 2, 'saluran_labels' => ['1', '2']],
             ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1, 'saluran_labels' => ['1']]],
            $res->json('struktur.pusat'),
        );
        $this->assertTrue($res->json('struktur.undi_pos'));
        $this->assertFalse($res->json('struktur.undi_awal'));
        $this->assertTrue($res->json('boleh_sunting_struktur'));
    }

    public function test_data_marks_the_structure_unlockable_for_published_forms_and_plain_users(): void
    {
        $form = $this->form($this->manualStructure(), 'published');

        $res = $this->actingAs($this->user('super_admin'))
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $form->id]));
        $this->assertFalse($res->json('boleh_sunting_struktur'));

        $draf = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'pru', 'tahun' => 2028, 'penjuru' => 2,
            'status' => 'draft', 'source' => 'manual', 'parties' => [],
            'structure' => $this->manualStructure(),
        ]);
        // Bukan '$this->user('user')': keseluruhan kumpulan laluan
        // pilihanraya/* disekat middleware 'admin' (routes/web.php), jadi
        // peranan 'user' tidak pernah sampai ke data() — respons 403 tanpa
        // sebarang kunci JSON, bukan false. Kes sebenar yang dijaga oleh
        // cawangan skop-Bandar bolehSuntingStruktur() ialah admin YANG SAH
        // memasuki data() tetapi bagi Bandar LAIN.
        $bandarLain = Bandar::create(['nama' => 'Bandar Lain Data', 'negeri_id' => $this->kadun->bandar->negeri_id]);
        $adminLain = $this->user('admin', ['bandar_id' => $bandarLain->id]);
        $res = $this->actingAs($adminLain)
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $draf->id]));
        $this->assertFalse($res->json('boleh_sunting_struktur'));
    }

    public function test_a_seat_with_no_form_at_all_still_reports_an_empty_editable_structure(): void
    {
        // Skrin buntu: tiada borang, tiada struktur — tetapi butang "Cipta
        // Borang 14 kosong" mesti muncul, jadi bendera ini mesti true.
        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2029,
        ]));

        $res->assertOk();
        $this->assertSame([], $res->json('struktur.pusat'));
        $this->assertTrue($res->json('boleh_sunting_struktur'));
    }
}
