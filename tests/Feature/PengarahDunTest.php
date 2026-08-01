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
        // Pemilih kerusi Scoreboard menapis kepada kerusi yang BERBORANG 14.
        // Setiap kerusi dalam ujian ini diberi borang — TERMASUK yang asing —
        // supaya kerusi asing tercicir kerana SKOP, bukan kerana kebetulan
        // tiada borang. Tanpa borang asing itu, ujian ini akan lulus walaupun
        // pengurungan skop runtuh sepenuhnya.
        foreach ([
            ['dun', $this->dunSendiri->id],
            ['dun', $this->dunSendiriKedua->id],
            ['dun', $this->dunAsing->id],
            ['parlimen', $this->parlimenSendiri->id],
            ['parlimen', $this->parlimenAsing->id],
        ] as [$jenis, $id]) {
            Borang14Form::create([
                'kawasan_type' => $jenis,
                'kawasan_id' => $id,
                'jenis_pr' => 'prn',
                'tahun' => 2026,
                'penjuru' => 2,
                'status' => 'draft',
                'parties' => [['nama' => 'KEADILAN'], ['nama' => 'BERSATU']],
            ]);
        }

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
     * Tiga baris ujian Laporan: satu dalam Parlimen sendiri, satu dalam
     * Parlimen orang lain. Kedua-duanya mesti tidak dapat dicapai — peranan
     * ini langsung tiada menu Laporan.
     *
     * @return array{0: HasilCulaan, 1: HasilCulaan, 2: DataPengundi}
     */
    private function benihLaporan(): array
    {
        $hcSendiri = HasilCulaan::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'KUALA PILAH', 'kadun' => 'PILAH',
        ]);
        $hcAsing = HasilCulaan::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'JEMPOL', 'kadun' => 'BAHAU',
        ]);
        $dpAsing = DataPengundi::factory()->create([
            'negeri' => 'NEGERI SEMBILAN', 'bandar' => 'JEMPOL', 'kadun' => 'BAHAU',
        ]);

        return [$hcSendiri, $hcAsing, $dpAsing];
    }

    /**
     * EKSPORT ialah lubang yang paling teruk: skopnya ditulis DENGAN TANGAN
     * (`if ($user->isAdmin())`) dan tidak pernah memanggil VoterScopeService,
     * jadi peranan yang bukan `user` dan bukan `admin` jatuh melalui dan
     * memuat turun sehingga 10,000 baris no_ic/no_tel/alamat SELURUH NEGARA.
     *
     * Halaman indeks yang berskop TIDAK membuktikan eksport selamat — itulah
     * sebabnya ujian ini wujud berasingan.
     */
    public function test_laporan_exports_are_refused(): void
    {
        $this->benihLaporan();
        $pengarah = $this->pengarah();

        foreach (['reports.hasil-culaan.export', 'reports.data-pengundi.export'] as $nama) {
            $this->actingAs($pengarah)->get(route($nama))->assertForbidden();
        }
    }

    /**
     * Padam pukal berkongsi bentuk pepijat yang sama seperti eksport: cabang
     * `else`-nya memadam SETIAP id yang diminta tanpa semakan kerusi. Itu
     * kemusnahan seluruh negara, bukan sekadar bacaan.
     */
    public function test_laporan_bulk_delete_cannot_reach_foreign_records(): void
    {
        [$hcSendiri, $hcAsing, $dpAsing] = $this->benihLaporan();
        $pengarah = $this->pengarah();

        $this->actingAs($pengarah)->post(route('reports.hasil-culaan.bulk-delete'), [
            'ids' => [$hcSendiri->id, $hcAsing->id],
        ]);
        $this->actingAs($pengarah)->post(route('reports.data-pengundi.bulk-delete'), [
            'ids' => [$dpAsing->id],
        ]);

        $this->assertNotNull($hcSendiri->fresh(), 'Rekod Parlimen sendiri dipadam.');
        $this->assertNotNull($hcAsing->fresh(), 'Rekod Parlimen ASING dipadam.');
        $this->assertNotNull($dpAsing->fresh(), 'Rekod Parlimen ASING dipadam.');
    }

    /** Padam tunggal hanya berpagar pada isUser() — peranan ini terlepas. */
    public function test_laporan_single_delete_is_refused(): void
    {
        [, $hcAsing, $dpAsing] = $this->benihLaporan();
        $pengarah = $this->pengarah();

        $this->actingAs($pengarah)
            ->delete(route('reports.hasil-culaan.destroy', $hcAsing))->assertForbidden();
        $this->actingAs($pengarah)
            ->delete(route('reports.data-pengundi.destroy', $dpAsing))->assertForbidden();

        $this->assertNotNull($hcAsing->fresh());
        $this->assertNotNull($dpAsing->fresh());
    }

    /**
     * Sekatan "hanya Parlimen sendiri" pada cipta rekod ditulis sebagai
     * `if ($user->isAdmin() || $user->isUser())` — peranan ini terlepas
     * sepenuhnya dan boleh mencipta rekod bagi mana-mana Parlimen.
     */
    public function test_laporan_store_is_refused(): void
    {
        $this->actingAs($this->pengarah())
            ->post(route('reports.hasil-culaan.store'), [
                'nama' => 'PENYUSUP', 'no_ic' => '900101015555',
                'parlimen' => 'JEMPOL', 'negeri' => 'NEGERI SEMBILAN', 'kadun' => 'BAHAU',
            ])->assertForbidden();

        $this->assertSame(0, HasilCulaan::where('nama', 'PENYUSUP')->count());
    }

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

    // ------------------------------------------------- skop menu Pilihanraya

    /**
     * Menyemai kedua-dua Parlimen dengan baris undian + culaan supaya agregat
     * mempunyai sesuatu untuk dikembalikan pada kedua-dua belah. Tanpa ini
     * ujian "tiada kerusi asing" lulus secara kosong.
     */
    private function benihAgregat(): void
    {
        foreach ([
            ['KUALA PILAH', 'PILAH'],
            ['JEMPOL', 'BAHAU'],
        ] as [$parlimen, $dun]) {
            for ($i = 0; $i < 3; $i++) {
                HasilCulaan::factory()->create([
                    'negeri' => 'NEGERI SEMBILAN', 'bandar' => $parlimen,
                    'parlimen' => $parlimen, 'kadun' => $dun,
                ]);
            }
        }
    }

    /** Setiap nama kerusi yang muncul dalam muatan seat-scores/battlefield. */
    private function namaKerusi(array $payload): array
    {
        $nama = [];
        array_walk_recursive($payload, function ($v, $k) use (&$nama) {
            if (in_array($k, ['name', 'parlimen', 'kadun'], true) && is_string($v) && $v !== '') {
                $nama[] = mb_strtoupper($v);
            }
        });

        return array_values(array_unique($nama));
    }

    /**
     * "Parlimen sahaja" mesti benar bagi hujung API War Room juga, bukan
     * sekadar bagi Scoreboard dan Borang 14. Hujung ini menerima
     * `parlimen_id` daripada permintaan, jadi ia mesti DIPAKSA kepada
     * Parlimen pengguna dan bukan sekadar dilalaikan kepadanya.
     */
    public function test_war_room_api_never_returns_a_foreign_seat(): void
    {
        $this->benihAgregat();
        $pengarah = $this->pengarah();

        foreach (['pilihanraya.api.seat-scores', 'pilihanraya.api.battlefield'] as $nama) {
            // (a) tanpa penapis langsung
            $nama1 = $this->namaKerusi(
                $this->actingAs($pengarah)->getJson(route($nama))->assertOk()->json()
            );
            // Positif DAHULU: tanpa ini ujian lulus secara kosong apabila
            // muatan langsung tidak mengandungi apa-apa kerusi.
            $this->assertContains('KUALA PILAH', $nama1, "{$nama}: kerusi sendiri hilang — ujian kosong.");
            $this->assertNotContains('JEMPOL', $nama1, "{$nama}: Parlimen asing bocor.");
            $this->assertNotContains('BAHAU', $nama1, "{$nama}: DUN asing bocor.");

            // (b) DENGAN Parlimen asing dipaksa masuk melalui permintaan
            $nama2 = $this->namaKerusi(
                $this->actingAs($pengarah)->getJson(route($nama, [
                    'parlimen_id' => $this->parlimenAsing->id,
                    'kadun_id' => $this->dunAsing->id,
                ]))->assertOk()->json()
            );
            $this->assertNotContains('JEMPOL', $nama2, "{$nama}: parlimen_id yang ditaip menembusi skop.");
            $this->assertNotContains('BAHAU', $nama2, "{$nama}: kadun_id yang ditaip menembusi skop.");
        }
    }

    /**
     * Pemilih antara muka MESTI dibina daripada kerusi yang dibenarkan
     * sahaja — membinanya daripada jadual induk penuh menyenaraikan kerusi
     * yang pengguna tidak berhak sentuh (docblock SeatScope).
     */
    public function test_pilihanraya_pickers_only_offer_own_parlimen(): void
    {
        $pengarah = $this->pengarah();

        $geo = $this->actingAs($pengarah)->get(route('pilihanraya.simulasi'))
            ->assertOk()->viewData('page')['props'];

        $this->assertSame(
            [$this->parlimenSendiri->id],
            collect($geo['parlimenList'])->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$this->dunSendiri->id, $this->dunSendiriKedua->id],
            collect($geo['kadunList'])->pluck('id')->all(),
        );

        $analisa = $this->actingAs($pengarah)->get(route('pilihanraya.analisa'))
            ->assertOk()->viewData('page')['props'];
        $this->assertSame(
            [$this->parlimenSendiri->id],
            collect($analisa['geo']['parlimenList'])->pluck('id')->all(),
        );
    }

    /**
     * Kaum Mengikut DM dan Minima memilih kerusi melalui ?kawasan=<id kadun>
     * daripada senarai induk KEBANGSAAN. Senarai itu mesti ditapis, dan id
     * asing yang ditaip mesti tidak boleh dipilih.
     */
    public function test_kaum_dm_and_minima_cannot_open_a_foreign_seat(): void
    {
        $pengarah = $this->pengarah();

        foreach (['pilihanraya.kaum-dm', 'pilihanraya.minima'] as $nama) {
            $props = $this->actingAs($pengarah)
                ->get(route($nama, ['kawasan' => $this->dunAsing->id]))
                ->assertOk()->viewData('page')['props'];

            $senarai = collect($props['context']['kawasanList']);

            $this->assertEqualsCanonicalizing(
                [(string) $this->dunSendiri->id, (string) $this->dunSendiriKedua->id],
                $senarai->pluck('id')->all(),
                "{$nama}: senarai kawasan mengandungi kerusi asing.",
            );
            $this->assertNotSame('BAHAU', $props['context']['dun'], "{$nama}: kerusi asing dibuka.");
            $this->assertSame('KUALA PILAH', $props['context']['parlimen']);
        }
    }

    /**
     * briefing() ialah SATU-SATUNYA hujung dalam PilihanrayaController yang
     * tidak melalui f()/kekangKawasan(): ia mengambil `level` + `scope_id`
     * terus daripada permintaan dan memanggil resolveFilters() sendiri.
     *
     * Setiap penegasan menyemak 403 DENGAN TEPAT, bukan sekadar "bukan 200" —
     * halaman agregat memulangkan 500 di bawah SQLite (SQL khusus MySQL),
     * jadi "bukan 200" akan lulus secara kosong tanpa membuktikan apa-apa.
     */
    public function test_briefing_cannot_be_pointed_at_a_foreign_or_national_scope(): void
    {
        $pengarah = $this->pengarah();

        $ditolak = [
            'kebangsaan' => ['level' => 'national'],
            'negeri penuh' => ['level' => 'negeri', 'scope_id' => $this->negeri->id],
            'parlimen asing' => ['level' => 'parlimen', 'scope_id' => $this->parlimenAsing->id],
            'dun asing' => ['level' => 'kadun', 'scope_id' => $this->dunAsing->id],
        ];

        foreach ($ditolak as $label => $muatan) {
            $this->actingAs($pengarah)
                ->postJson(route('pilihanraya.api.briefing'), $muatan)
                ->assertStatus(403, "briefing: skop '{$label}' tidak ditolak.");
        }
    }

    /**
     * Kawalan positif: gerbang mesti MEMBENARKAN kerusi sendiri masuk.
     * Tanpa ini, briefing yang menolak segala-galanya akan lulus ujian di
     * atas. Muatan tidak boleh dirender di bawah SQLite, jadi yang disemak
     * ialah ketiadaan 403.
     */
    public function test_briefing_still_admits_its_own_seats(): void
    {
        $pengarah = $this->pengarah();

        foreach ([
            ['level' => 'parlimen', 'scope_id' => $this->parlimenSendiri->id],
            ['level' => 'kadun', 'scope_id' => $this->dunSendiri->id],
        ] as $muatan) {
            $status = $this->actingAs($pengarah)
                ->postJson(route('pilihanraya.api.briefing'), $muatan)->getStatusCode();

            $this->assertNotSame(403, $status, 'Gerbang menolak kerusi sendiri.');
        }
    }

    /**
     * Perbandingan Analisa memegang tally undi sebenar bagi satu kerusi dan
     * diikat melalui laluan, jadi id yang ditaip membuka kerusi orang lain.
     */
    public function test_analisa_comparisons_of_a_foreign_seat_are_unreachable(): void
    {
        $asing = \App\Models\AnalisaComparison::create([
            'user_id' => $this->user('super_admin', ['bandar_id' => null, 'kadun_id' => null])->id,
            'title' => 'Perbandingan JEMPOL', 'level' => 'parlimen',
            'negeri' => 'NEGERI SEMBILAN', 'bandar_id' => $this->parlimenAsing->id,
            'parlimen' => 'JEMPOL', 'status' => 'draft',
        ]);

        $pengarah = $this->pengarah();

        $this->actingAs($pengarah)
            ->getJson(route('pilihanraya.analisa.comparisons.show', $asing))->assertForbidden();
        $this->actingAs($pengarah)
            ->deleteJson(route('pilihanraya.analisa.comparisons.destroy', $asing))->assertForbidden();
        $this->assertNotNull($asing->fresh());

        // Ia juga mesti tidak muncul dalam senarai.
        $senarai = $this->actingAs($pengarah)
            ->getJson(route('pilihanraya.analisa.comparisons.index'))->assertOk()->json('comparisons');
        $this->assertSame([], $senarai);

        // Dan tidak boleh dicipta pada Parlimen orang lain.
        $this->actingAs($pengarah)->postJson(route('pilihanraya.analisa.comparisons.store'), [
            'title' => 'Curi', 'level' => 'parlimen', 'bandar_id' => $this->parlimenAsing->id,
        ])->assertForbidden();

        // Parlimen sendiri kekal berfungsi.
        $this->actingAs($pengarah)->postJson(route('pilihanraya.analisa.comparisons.store'), [
            'title' => 'Sendiri', 'level' => 'parlimen', 'bandar_id' => $this->parlimenSendiri->id,
        ])->assertOk();
    }

    /** Garis dasar rasmi per-kerusi mesti menolak kerusi asing. */
    public function test_seat_baseline_refuses_a_foreign_seat(): void
    {
        $pengarah = $this->pengarah();

        $this->actingAs($pengarah)->getJson(route('pilihanraya.analisa.seat-baseline', [
            'kadun_id' => $this->dunAsing->id, 'level' => 'dun',
        ]))->assertForbidden();

        $this->actingAs($pengarah)->getJson(route('pilihanraya.analisa.seat-baseline', [
            'bandar_id' => $this->parlimenAsing->id, 'level' => 'parlimen',
        ]))->assertForbidden();

        // Kerusi sendiri kekal boleh dibaca.
        $this->actingAs($pengarah)->getJson(route('pilihanraya.analisa.seat-baseline', [
            'kadun_id' => $this->dunSendiri->id, 'level' => 'dun',
        ]))->assertOk();
    }

    /** Akaun tanpa Parlimen mesti ditolak, bukan dilayan sebagai "tiada had". */
    public function test_a_seatless_pengarah_dun_is_refused_on_scoped_pilihanraya_pages(): void
    {
        $tanpaKerusi = $this->pengarah(['bandar_id' => null, 'kadun_id' => null]);

        $this->actingAs($tanpaKerusi)->getJson(route('pilihanraya.api.seat-scores'))->assertForbidden();
        $this->actingAs($tanpaKerusi)->get(route('pilihanraya.kaum-dm'))->assertForbidden();
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

    /**
     * Paku tingkah laku peranan LAIN pada laluan yang disentuh oleh
     * pembetulan ini (eksport Laporan, padam pukal, pemilih Pilihanraya,
     * hujung API agregat). Jika mana-mana daripadanya berubah, pembetulan
     * telah merebak melebihi lajur `pengarah_dun`.
     */
    public function test_the_touched_routes_are_unchanged_for_every_other_role(): void
    {
        $this->benihAgregat();
        [$hcSendiri, $hcAsing] = $this->benihLaporan();

        // --- admin: eksport masih berjaya dan masih terhad kepada Parlimennya
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('reports.hasil-culaan.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.data-pengundi.export'))->assertOk();

        // Pemilih Pilihanraya admin kekal KEBANGSAAN (keputusan produk yang
        // belum dibuat — tugas ini tidak boleh mengubahnya).
        $geoAdmin = $this->actingAs($admin)->get(route('pilihanraya.simulasi'))
            ->assertOk()->viewData('page')['props'];
        $this->assertEqualsCanonicalizing(
            [$this->parlimenSendiri->id, $this->parlimenAsing->id],
            collect($geoAdmin['parlimenList'])->pluck('id')->all(),
        );

        // Agregat admin masih memaparkan kerusi luar Parlimennya (sedia ada).
        $namaAdmin = $this->namaKerusi(
            $this->actingAs($admin)->getJson(route('pilihanraya.api.seat-scores'))->assertOk()->json()
        );
        $this->assertContains('JEMPOL', $namaAdmin, 'Skop admin berubah — di luar skop tugas ini.');

        // Padam pukal admin masih menapis kepada Parlimennya sendiri.
        $this->actingAs($admin)->post(route('reports.hasil-culaan.bulk-delete'), [
            'ids' => [$hcSendiri->id, $hcAsing->id],
        ]);
        $this->assertNull($hcSendiri->fresh(), 'Admin tidak lagi boleh memadam rekod Parlimennya.');
        $this->assertNotNull($hcAsing->fresh(), 'Admin memadam rekod Parlimen asing.');

        // --- super_admin: tiada had di mana-mana
        $super = $this->user('super_admin', ['bandar_id' => null, 'kadun_id' => null]);
        $this->actingAs($super)->get(route('reports.hasil-culaan.export'))->assertOk();
        $geoSuper = $this->actingAs($super)->get(route('pilihanraya.simulasi'))
            ->assertOk()->viewData('page')['props'];
        $this->assertCount(2, $geoSuper['parlimenList']);

        // --- user: masih 403 pada eksport (sedia ada)
        $this->actingAs($this->user('user'))
            ->get(route('reports.hasil-culaan.export'))->assertForbidden();

        // --- super_user: TIDAK disentuh. Eksportnya masih berjaya (dan masih
        // tidak berskop — lubang sedia ada yang direkodkan dalam laporan,
        // BUKAN sesuatu yang tugas ini patut ubah).
        $superUser = $this->user('super_user');
        $this->actingAs($superUser)->get(route('reports.hasil-culaan.export'))->assertOk();
        $this->actingAs($superUser)->get(route('reports.data-pengundi.export'))->assertOk();
        $this->actingAs($superUser)
            ->get(route('pilihanraya.api.seat-scores'))->assertRedirect(route('dashboard'));

        // --- ketua_paca_dun: masih tiada capaian Pilihanraya selain PACA
        $this->actingAs($this->user('ketua_paca_dun'))
            ->getJson(route('pilihanraya.api.seat-scores'))->assertForbidden();

        // --- briefing: admin masih boleh menuding ke mana-mana skop,
        // termasuk kebangsaan (sedia ada — bukan tugas ini untuk mengubah).
        foreach ([
            ['level' => 'national'],
            ['level' => 'parlimen', 'scope_id' => $this->parlimenAsing->id],
        ] as $muatan) {
            $this->assertNotSame(
                403,
                $this->actingAs($admin)->postJson(route('pilihanraya.api.briefing'), $muatan)->getStatusCode(),
                'Skop briefing admin berubah — di luar skop tugas ini.',
            );
        }
    }

    /**
     * SENGAJA diketatkan: `ketua_paca_dun` juga tiada menu Laporan, dan ia
     * turut jatuh melalui eksport tanpa skop. Diketatkan bersama-sama
     * pengarah_dun (kelas pembetulan yang sama seperti VoterScopeService).
     * Dipaku di sini supaya perubahan itu nyata dan bukan kesan sampingan.
     */
    public function test_ketua_paca_dun_is_also_cut_off_from_laporan_exports(): void
    {
        $ketua = $this->user('ketua_paca_dun');

        $this->actingAs($ketua)->get(route('reports.hasil-culaan.export'))->assertForbidden();
        $this->actingAs($ketua)->get(route('reports.data-pengundi.export'))->assertForbidden();
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
