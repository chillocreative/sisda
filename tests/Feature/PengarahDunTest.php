<?php
// tests/Feature/PengarahDunTest.php
//
// Peranan `pengarah_dun` — Pengarah DUN. Walaupun namanya "DUN", skopnya
// ialah PARLIMEN pada users.bandar_id dan setiap DUN di bawahnya, sama
// seperti `admin`. Menunya SATU sahaja: Pilihanraya.
//
// Ujian ini memaku empat jaminan:
//
//   1. Pendaratan — /dashboard TIDAK boleh jatuh melalui ke papan pemuka
//      Super Admin peringkat KEBANGSAAN. DashboardController bercabang bagi
//      ketua_paca_dun, kemudian admin|super_user|user, lalu jatuh ke
//      Dashboard/Index (seluruh Malaysia). Cabang eksplisit WAJIB.
//   2. Menu — Pilihanraya boleh dicapai; Keanggotaan, Data Induk, Laporan
//      dan Tetapan ditolak.
//   3. Kerusi — Parlimen sendiri + DUN di bawahnya sahaja; kerusi asing 403.
//   4. Peranan lain tidak berubah langsung.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\DataPengundi;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Support\SeatScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengarahDunTest extends TestCase
{
    use RefreshDatabase;

    private Negeri $negeri;

    private Bandar $parlimenSendiri;

    private Bandar $parlimenAsing;

    private Kadun $dunSendiri;

    private Kadun $dunSendiriKedua;

    private Kadun $dunAsing;

    protected function setUp(): void
    {
        parent::setUp();
        // Halaman Inertia dirender melalui app.blade.php; tanpa ini ujian
        // bergantung pada public/build/manifest.json yang tiada dalam CI.
        $this->withoutVite();

        $this->negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimenSendiri = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $this->negeri->id]);
        $this->parlimenAsing = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P130', 'negeri_id' => $this->negeri->id]);
        $this->dunSendiri = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $this->parlimenSendiri->id]);
        $this->dunSendiriKedua = Kadun::create(['nama' => 'JOHOL', 'kod_dun' => 'N26', 'bandar_id' => $this->parlimenSendiri->id]);
        $this->dunAsing = Kadun::create(['nama' => 'BAHAU', 'kod_dun' => 'N31', 'bandar_id' => $this->parlimenAsing->id]);
    }

    /** Dibina dengan tangan: UserFactory tidak menetapkan lajur NOT NULL `telephone`. */
    private function user(string $role, array $over = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name' => "Pengguna {$n}",
            'email' => "pengarah{$n}@example.test",
            'telephone' => '01500000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => 'approved',
            'negeri_id' => $this->negeri->id,
            'bandar_id' => $this->parlimenSendiri->id,
            'kadun_id' => $this->dunSendiri->id,
        ], $over));
    }

    private function pengarah(array $over = []): User
    {
        return $this->user('pengarah_dun', $over);
    }

    // ------------------------------------------------------------ pendaratan

    /**
     * BAHAYA UTAMA. Tanpa cabang eksplisit, peranan ini jatuh melalui ke
     * Dashboard/Index — papan pemuka Super Admin bagi SELURUH Malaysia.
     */
    public function test_dashboard_never_renders_the_national_super_admin_page(): void
    {
        $response = $this->actingAs($this->pengarah())->get(route('dashboard'));

        $response->assertRedirect(route('pilihanraya.scoreboard'));
    }

    /** Pendaratan itu sendiri mesti membawa kerusi Parlimen sendiri SAHAJA. */
    public function test_landing_page_carries_only_its_own_parlimen_and_its_duns(): void
    {
        $response = $this->actingAs($this->pengarah())
            ->get(route('dashboard'))
            ->assertRedirect(route('pilihanraya.scoreboard'));

        $seats = $this->actingAs($this->pengarah())
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->viewData('page')['props']['seats'];

        $kunci = collect($seats)->map(fn ($s) => $s['type'].':'.$s['id'])->sort()->values()->all();

        $dijangka = collect([
            'dun:'.$this->dunSendiri->id,
            'dun:'.$this->dunSendiriKedua->id,
            'parlimen:'.$this->parlimenSendiri->id,
        ])->sort()->values()->all();

        $this->assertSame($dijangka, $kunci);

        // Bukan kebangsaan, dan bukan Parlimen orang lain.
        $this->assertNotContains('parlimen:'.$this->parlimenAsing->id, $kunci);
        $this->assertNotContains('dun:'.$this->dunAsing->id, $kunci);
    }

    /** bandar_id NULL = akaun rosak. Gagal-tutup, bukan gagal-buka. */
    public function test_a_seatless_pengarah_dun_is_denied_everywhere(): void
    {
        $tanpaKerusi = $this->pengarah(['bandar_id' => null, 'kadun_id' => null]);

        $this->assertFalse(SeatScope::allows($tanpaKerusi, SeatScope::PARLIMEN, $this->parlimenSendiri->id));
        $this->assertSame([], SeatScope::seats($tanpaKerusi));

        $this->actingAs($tanpaKerusi)->get(route('pilihanraya.scoreboard'))->assertForbidden();
    }

    // ------------------------------------------------------------------ menu

    public function test_the_pilihanraya_menu_is_reachable(): void
    {
        $pengarah = $this->pengarah();

        foreach ([
            'pilihanraya.analisa',
            'pilihanraya.kaum-dm',
            'pilihanraya.borang-14',
            'pilihanraya.simulasi',
            'pilihanraya.minima',
            'pilihanraya.jawatankuasa.index',
            'pilihanraya.scoreboard',
        ] as $nama) {
            $this->actingAs($pengarah)->get(route($nama))->assertOk();
        }
    }

    /**
     * War Room diuji pada peringkat GERBANG sahaja: agregatnya menggunakan
     * DATE_SUB()/INTERVAL yang khusus MySQL, jadi halaman itu tidak boleh
     * dirender di bawah SQLite yang digunakan CI. Yang penting di sini ialah
     * EnsurePilihanrayaAccess membenarkannya masuk — 302 ke /dashboard
     * bermakna gerbang menolaknya.
     */
    public function test_the_war_room_gate_admits_the_role(): void
    {
        $status = $this->actingAs($this->pengarah())
            ->get(route('pilihanraya.war-room'))
            ->getStatusCode();

        $this->assertNotSame(302, $status, 'Gerbang Pilihanraya menolak Pengarah DUN.');
    }

    /**
     * PACA berada dalam kumpulannya sendiri (middleware `paca`) dan tidak
     * dibuka kepada peranan ini — reka bentuk berkata "kumpulan pilihanraya
     * dibuka kepadanya; TIADA laluan lain".
     */
    public function test_paca_stays_closed(): void
    {
        $this->actingAs($this->pengarah())
            ->get(route('pilihanraya.paca'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_keanggotaan_is_refused(): void
    {
        $this->actingAs($this->pengarah())
            ->get(route('keanggotaan.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_data_induk_is_refused(): void
    {
        $pengarah = $this->pengarah();

        foreach ([
            'master-data.bandar.index',
            'master-data.parlimen.index',
            'master-data.kadun.index',
        ] as $nama) {
            $this->actingAs($pengarah)->get(route($nama))->assertForbidden();
        }
    }

    public function test_tetapan_is_refused(): void
    {
        $pengarah = $this->pengarah();

        foreach (['settings.sendora', 'settings.claude', 'settings.ai-usage'] as $nama) {
            $this->actingAs($pengarah)->get(route($nama))->assertRedirect(route('dashboard'));
        }
    }

    /**
     * Laporan hidup dalam kumpulan `auth` kosong dan diskop oleh
     * VoterScopeService. Peranan yang tidak dikenali dahulunya JATUH MELALUI
     * ke "tanpa had" — iaitu data pengundi seluruh negara. Mesti sifar baris.
     */
    public function test_laporan_shows_no_rows_at_all(): void
    {
        // Baris dalam Parlimen SENDIRI — jika skop bocor "tanpa had", baris
        // ini akan muncul. Ia mesti tetap tersembunyi: peranan ini langsung
        // tiada menu Laporan.
        HasilCulaan::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'KUALA PILAH', 'kadun' => 'PILAH',
        ]);
        DataPengundi::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'KUALA PILAH', 'kadun' => 'PILAH',
        ]);
        // Dan satu lagi di Parlimen orang lain.
        HasilCulaan::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'JEMPOL', 'kadun' => 'BAHAU',
        ]);

        $pengarah = $this->pengarah();

        $culaan = $this->actingAs($pengarah)->get(route('reports.hasil-culaan.index'))
            ->assertOk()->viewData('page')['props'];
        $this->assertCount(0, $culaan['hasilCulaan']['data'] ?? $culaan['hasilCulaan'] ?? []);

        $pengundi = $this->actingAs($pengarah)->get(route('reports.data-pengundi.index'))
            ->assertOk()->viewData('page')['props'];
        $this->assertCount(0, $pengundi['dataPengundi']['data'] ?? $pengundi['dataPengundi'] ?? []);
    }

    // ----------------------------------------------------------------- kerusi

    /** @return array<string,mixed> */
    private function muatanUndi(array $ubah = []): array
    {
        return array_merge([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunSendiri->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 500,
        ], $ubah);
    }

    public function test_borang_14_writes_accept_own_seats_and_refuse_foreign_ones(): void
    {
        $pengarah = $this->pengarah();

        foreach ([$this->dunSendiri, $this->dunSendiriKedua] as $dun) {
            $this->actingAs($pengarah)
                ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanUndi(['kawasan_id' => $dun->id]))
                ->assertOk()->assertJson(['ok' => true]);
        }

        $this->actingAs($pengarah)
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanUndi(['kawasan_id' => $this->dunAsing->id]))
            ->assertForbidden();

        $this->actingAs($pengarah)
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanUndi([
                'kawasan_type' => 'parlimen',
                'kawasan_id' => $this->parlimenAsing->id,
                'jenis_pr' => 'pru',
                'contest' => 'parlimen',
            ]))
            ->assertForbidden();

        // firstOrCreate() dalam saveVote() akan MENCIPTA borang pada kerusi
        // yang dinamakan input, jadi penegasan yang terlalu lewat meninggalkan
        // borang hantu. Hanya dua kerusi sendiri yang boleh wujud.
        $this->assertSame(2, Borang14Form::count());
    }

    /** bandar_id NULL mesti menolak walaupun kerusi yang diminta wujud. */
    public function test_a_seatless_pengarah_dun_cannot_write_borang_14(): void
    {
        $this->actingAs($this->pengarah(['bandar_id' => null, 'kadun_id' => null]))
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanUndi())
            ->assertForbidden();

        $this->assertSame(0, Borang14Form::count());
    }

    public function test_scoreboard_settings_refuse_a_foreign_seat(): void
    {
        $this->actingAs($this->pengarah())
            ->postJson(route('pilihanraya.scoreboard.settings'), [
                'kawasan_type' => 'dun',
                'kawasan_id' => $this->dunAsing->id,
                'title' => 'Papan Curi',
            ])->assertForbidden();
    }

    // -------------------------------------------------------- pentadbiran akaun

    public function test_an_admin_may_assign_the_role_within_own_parlimen(): void
    {
        $admin = $this->user('admin');
        $ahli = $this->user('user');

        $this->actingAs($admin)->put(route('users.update', $ahli), [
            'name' => $ahli->name,
            'telephone' => $ahli->telephone,
            'email' => $ahli->email,
            'role' => 'pengarah_dun',
            'negeri_id' => $this->negeri->id,
            'bandar_id' => $this->parlimenSendiri->id,
            'kadun_id' => $this->dunSendiri->id,
            'status' => 'approved',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('pengarah_dun', $ahli->fresh()->role);
    }

    public function test_the_role_can_be_created_through_the_users_form(): void
    {
        $this->actingAs($this->user('super_admin', ['bandar_id' => null, 'kadun_id' => null]))
            ->post(route('users.store'), [
                'name' => 'Pengarah Baharu',
                'telephone' => '0199999999',
                'email' => 'baharu@example.test',
                'password' => 'RahsiaKuat#2026',
                'password_confirmation' => 'RahsiaKuat#2026',
                'role' => 'pengarah_dun',
                'negeri_id' => $this->negeri->id,
                'bandar_id' => $this->parlimenSendiri->id,
                'kadun_id' => $this->dunSendiri->id,
                'status' => 'approved',
            ])->assertRedirect(route('users.index'));

        $this->assertSame('pengarah_dun', User::where('telephone', '0199999999')->value('role'));
    }

    // -------------------------------------------------------- peranan lain kekal

    public function test_other_roles_are_completely_unaffected(): void
    {
        // Admin: masih masuk Keanggotaan, Data Induk dan Pilihanraya.
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('keanggotaan.index'))->assertOk();
        $this->actingAs($admin)->get(route('master-data.bandar.index'))->assertOk();
        $this->actingAs($admin)->get(route('pilihanraya.borang-14'))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        // user / super_user: Pilihanraya masih tertutup kecuali Scoreboard.
        foreach (['user', 'super_user'] as $role) {
            $biasa = $this->user($role);
            $this->actingAs($biasa)->get(route('pilihanraya.war-room'))->assertRedirect(route('dashboard'));
            $this->actingAs($biasa)->get(route('pilihanraya.borang-14'))->assertRedirect(route('dashboard'));
            $this->actingAs($biasa)->get(route('pilihanraya.scoreboard'))->assertOk();
            $this->actingAs($biasa)->get(route('keanggotaan.index'))->assertRedirect(route('dashboard'));
        }

        // ketua_paca_dun: masih mendarat di PACA, masih tiada War Room.
        $ketua = $this->user('ketua_paca_dun');
        $this->actingAs($ketua)->get(route('dashboard'))->assertRedirect(route('pilihanraya.paca'));
        $this->actingAs($ketua)->get(route('pilihanraya.war-room'))->assertRedirect(route('dashboard'));

        // super_admin: papan pemuka KEBANGSAAN kekal — tiada lencongan.
        // Halaman itu sendiri menggunakan REGEXP/CURDATE() yang khusus MySQL
        // dan tidak boleh dirender di bawah SQLite CI, jadi yang disemak
        // ialah ketiadaan lencongan, bukan render penuh.
        $super = $this->user('super_admin', ['bandar_id' => null, 'kadun_id' => null]);
        $this->assertNotSame(302, $this->actingAs($super)->get(route('dashboard'))->getStatusCode());
    }

    /** Borang14Form milik Parlimen asing tidak boleh dipadam atau ditulis. */
    public function test_a_foreign_borang_14_cannot_be_deleted(): void
    {
        $asing = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunAsing->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parties' => [], 'status' => 'draft', 'source' => 'manual',
            'structure' => ['rows' => []],
        ]);

        $this->actingAs($this->pengarah())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $asing->id])
            ->assertForbidden();

        $this->assertNotNull($asing->fresh());
    }
}
