<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StickyFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia ada).
        return User::factory()->create([
            'role' => 'super_admin',
            'telephone' => '01277'.random_int(10000, 99999),
        ]);
    }

    /** Laluan ujian yang memantulkan apa yang pengawal SEBENARNYA nampak. */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/ujian-penapis', function () {
            return response()->json([
                'negeri_id' => request()->input('negeri_id'),
                'bandar_id' => request()->input('bandar_id'),
                'penceroboh' => request()->input('penceroboh'),
            ]);
        })->name('ujian.penapis');

        // Laluan POST yang berkongsi skop yang sama seperti 'ujian.penapis'
        // — untuk membuktikan permintaan bukan-GET tidak sekali-kali diubah.
        Route::middleware(['web', 'auth'])->post('/ujian-penapis-post', function () {
            return response()->json([
                'negeri_id' => request()->input('negeri_id'),
                'bandar_id' => request()->input('bandar_id'),
            ]);
        })->name('ujian.penapis.post');

        config()->set('sticky_filters.ujian', [
            'routes' => ['ujian.penapis', 'ujian.penapis.post'],
            'keys' => ['negeri_id', 'bandar_id'],
        ]);
    }

    public function test_filters_are_remembered_and_merged_into_a_bare_request(): void
    {
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=5&bandar_id=40')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => '40']);

        // Navigasi biasa: TIADA parameter langsung -> pulihkan.
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => '40']);
    }

    public function test_clearing_a_filter_is_remembered_as_cleared(): void
    {
        $this->actingAs($this->user())->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        // applyFilters() (dipanggil pada setiap pertukaran dropdown, bukan
        // Set Semula) sentiasa menghantar SEMUA kunci skop — termasuk yang
        // dikosongkan oleh pengguna — jadi kunci itu HADIR-TETAPI-KOSONG.
        // Set Semula pula menghantar permintaan KOSONG berserta reset_filters
        // (lihat test_reset_sentinel_forgets_the_session_entry).
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=&bandar_id=')
            ->assertJson(['negeri_id' => '', 'bandar_id' => '']);

        // Lawatan seterusnya mesti memulihkan TIADA APA-APA, bukan nilai lama.
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '', 'bandar_id' => '']);
    }

    public function test_a_key_outside_the_whitelist_is_never_merged(): void
    {
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=5&penceroboh=jahat');

        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'penceroboh' => null]);
    }

    public function test_two_users_do_not_share_remembered_filters(): void
    {
        // Dua pengguna berbeza = dua sesi berbeza. Log keluar MESTI berada di
        // antaranya: dalam ujian, actingAs() menukar pengguna tetapi MENGEKALKAN
        // sesi ujian yang sama, jadi tanpa log keluar ujian ini hanya menguji
        // artifak kerangka ujian, bukan tingkah laku pengeluaran.
        $this->actingAs($this->user())->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');
        $this->post(route('logout'));

        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);
    }

    public function test_reset_sentinel_forgets_the_session_entry(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        // Isyarat reset -> permintaan ini sendiri mesti kembali tidak ditapis.
        $this->actingAs($user)
            ->getJson('/ujian-penapis?reset_filters=1')
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);

        // Lawatan KOSONG seterusnya mesti tidak memulihkan apa-apa — ini
        // membuktikan entri sesi telah DILUPAKAN, bukan sekadar dilangkau
        // sekali untuk permintaan reset itu sahaja.
        $this->actingAs($user)
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);
    }

    public function test_non_get_request_is_never_mutated(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        // Laluan POST berkongsi skop yang sama tetapi tidak menghantar
        // sebarang kunci penapis sendiri. Jika pengawal kaedah dialih
        // keluar, middleware akan menganggap ini "navigasi kosong" dan
        // menggabungkan nilai tersimpan ke dalam BADAN POST.
        $this->actingAs($user)
            ->postJson('/ujian-penapis-post')
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);
    }

    public function test_method_spoofing_does_not_bypass_the_get_only_guard(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        // POST SEBENAR ke laluan berdaftar GET, dengan `_method=GET` dalam
        // badan. Laravel membolehkan penggantian parameter kaedah HTTP, jadi
        // isMethod('GET') akan melapor TRUE di sini walaupun kaedah HTTP
        // sebenar ialah POST — itulah sebab middleware mesti guna
        // getRealMethod(). Jika pengawal method dialih kembali kepada
        // isMethod('GET'), permintaan ini akan lulus dan menggabungkan
        // penapis tersimpan ke dalam badan POST.
        $this->actingAs($user)
            ->post('/ujian-penapis', ['_method' => 'GET'])
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);
    }

    public function test_route_without_a_configured_scope_is_untouched(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        Route::middleware(['web', 'auth'])->get('/ujian-tanpa-skop', function () {
            return response()->json(['negeri_id' => request()->input('negeri_id')]);
        })->name('ujian.tanpa-skop');

        // 'ujian.tanpa-skop' tidak disenaraikan dalam mana-mana skop config
        // — middleware mesti melangkau sepenuhnya, tanpa menggabungkan
        // nilai tersimpan skop lain.
        $this->actingAs($user)
            ->getJson('/ujian-tanpa-skop')
            ->assertJson(['negeri_id' => null]);
    }

    public function test_scopes_with_shared_key_names_do_not_cross_contaminate(): void
    {
        config()->set('sticky_filters.skop_a', [
            'routes' => ['ujian.skop-a'],
            'keys' => ['negeri_id'],
        ]);
        config()->set('sticky_filters.skop_b', [
            'routes' => ['ujian.skop-b'],
            'keys' => ['negeri_id'],
        ]);

        Route::middleware(['web', 'auth'])->get('/ujian-skop-a', function () {
            return response()->json(['negeri_id' => request()->input('negeri_id')]);
        })->name('ujian.skop-a');
        Route::middleware(['web', 'auth'])->get('/ujian-skop-b', function () {
            return response()->json(['negeri_id' => request()->input('negeri_id')]);
        })->name('ujian.skop-b');

        $user = $this->user();

        $this->actingAs($user)->getJson('/ujian-skop-a?negeri_id=7');
        $this->actingAs($user)->getJson('/ujian-skop-b?negeri_id=9');

        // Kedua-dua skop menggunakan nama kunci ('negeri_id') yang sama tetapi
        // MESTI disimpan di bawah kunci sesi yang berasingan — skop A tidak
        // boleh membaca semula nilai skop B, dan sebaliknya.
        $this->actingAs($user)
            ->getJson('/ujian-skop-a')
            ->assertJson(['negeri_id' => '7']);

        $this->actingAs($user)
            ->getJson('/ujian-skop-b')
            ->assertJson(['negeri_id' => '9']);
    }

    public function test_merge_path_is_filtered_against_the_current_whitelist(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        // Kecutkan senarai putih pada waktu jalan (cth: bandar_id dibuang
        // daripada konfigurasi selepas simpanan sesi lama dibuat).
        config()->set('sticky_filters.ujian.keys', ['negeri_id']);

        // Entri sesi lama masih membawa bandar_id, tetapi array_intersect_key
        // mesti menyaringnya keluar berdasarkan senarai putih SEMASA.
        $this->actingAs($user)
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => null]);
    }

    public function test_array_value_is_coerced_to_null_when_saved(): void
    {
        $user = $this->user();

        // URL rosak/berniat jahat: parameter tatasusunan pada kunci yang
        // dijangka skalar.
        $this->actingAs($user)->getJson('/ujian-penapis?negeri_id=5&bandar_id[]=1&bandar_id[]=2');

        // Jika nilai tatasusunan disimpan verbatim, ia akan digabungkan
        // semula ke dalam SETIAP permintaan kosong berikutnya dan meracau
        // seluruh baki sesi pengguna.
        $this->actingAs($user)
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => null]);
    }

    public function test_array_value_already_in_the_session_is_coerced_on_merge(): void
    {
        // skalarSahaja() sebelum ini hanya dipanggil pada laluan SIMPAN, jadi
        // ia hanya melindungi kandungan yang middleware INI yang menulis.
        // Tulis tatasusunan terus ke dalam sesi (memantulkan entri lama dari
        // versi middleware terdahulu, atau kandungan sesi yang dirosakkan
        // dengan cara lain) supaya laluan GABUNG diuji secara berasingan
        // daripada laluan simpan.
        $user = $this->user();
        $this->actingAs($user)->getJson('/ujian-penapis');

        session()->put('sticky_filters.ujian', ['negeri_id' => '5', 'bandar_id' => ['1', '2']]);
        session()->save();

        $this->actingAs($user)
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => null]);
    }

    public function test_war_room_scope_forgets_on_reset_instead_of_restoring(): void
    {
        // war_room kini AKTIF (lihat config/sticky_filters.php) selepas
        // WarRoom.jsx disemai daripada rememberedFilters dan requestParams()
        // menghantar reset_filters apabila cleanParams() memulangkan {}.
        // Laluan SEBENAR War Room memanggil ElectionAnalyticsService, yang
        // menjalankan SQL khusus MySQL (REGEXP) tanpa mengira penapis —
        // tidak dapat diuji di SQLite CI (lihat CLAUDE.md). Sahkan tingkah
        // laku middleware bagi skop 'war_room' SEBENAR melalui laluan ujian
        // ringan, menggantikan 'routes' skop itu buat sementara sahaja —
        // 'keys' kekal senarai putih SEBENAR daripada konfigurasi.
        Route::middleware(['web', 'auth'])->get('/ujian-war-room', function () {
            return response()->json([
                'negeri_id' => request()->input('negeri_id'),
                'parlimen_id' => request()->input('parlimen_id'),
            ]);
        })->name('ujian.war-room');

        config()->set('sticky_filters.war_room.routes', ['ujian.war-room']);

        $user = $this->user();

        $this->actingAs($user)
            ->getJson('/ujian-war-room?negeri_id=3&parlimen_id=12')
            ->assertJson(['negeri_id' => '3', 'parlimen_id' => '12']);

        // Set Semula (FilterBar) -> requestParams() menghantar permintaan
        // KOSONG berserta reset_filters kerana cleanParams() memulangkan {}
        // apabila semua penapis dikosongkan.
        $this->actingAs($user)
            ->getJson('/ujian-war-room?reset_filters=1')
            ->assertJson(['negeri_id' => null, 'parlimen_id' => null]);

        // Lawatan KOSONG seterusnya mesti KEKAL tidak ditapis — entri sesi
        // dilupakan, bukan dipulihkan.
        $this->actingAs($user)
            ->getJson('/ujian-war-room')
            ->assertJson(['negeri_id' => null, 'parlimen_id' => null]);
    }

    public function test_logging_out_forgets_the_filters(): void
    {
        $user = $this->user();

        $this->actingAs($user)->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');
        $this->post(route('logout'));

        // Log masuk semula pada sesi baharu -> lalai, seperti diminta.
        $this->actingAs($user)
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);
    }

    public function test_remembered_filters_are_shared_to_inertia(): void
    {
        // 'super_admin' pada laluan dashboard PENUH menjalankan pertanyaan
        // `tahun_lahir REGEXP ...` (khusus MySQL — lihat CLAUDE.md: untestable
        // di SQLite CI). Guna peranan 'user' supaya pengawal terus pulang
        // Dashboard/UserDashboard ringkas, membolehkan kongsi rememberedFilters
        // diuji tanpa menyentuh laluan pertanyaan MySQL-sahaja itu.
        $user = User::factory()->create([
            'role' => 'user',
            'telephone' => '01277'.random_int(10000, 99999),
        ]);

        $this->actingAs($user)->get(route('dashboard', ['negeri_id' => 5]));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('rememberedFilters.negeri_id', '5'));
    }

    public function test_dashboard_echoes_its_filters_back_to_the_page(): void
    {
        // Pepijat sedia ada: pengawal MEMBACA keenam-enam parameter tetapi
        // tidak pernah memulangkannya, jadi setiap dropdown bermula kosong
        // walaupun URL membawanya. Refresh biasa pun kehilangannya.
        //
        // NOTA CI: laluan 'super_admin' penuh menjalankan BUKAN SAHAJA
        // `tahun_lahir REGEXP ...` (khusus MySQL, lihat CLAUDE.md) tetapi
        // juga pertanyaan $petugasStats (HAVING tanpa GROUP BY) yang
        // ditolak oleh pemeriksa sintaks SQLite walaupun pada jadual kosong
        // — kedua-duanya sedia ada dan tidak berkaitan dengan pembaikan
        // Tugasan 4 ini. Disahkan secara manual (di luar suite ini, tanpa
        // menyentuh kod pengeluaran) bahawa selepas melangkau kedua-dua
        // sekatan itu di sisi ujian sahaja, laluan sebenar MEMANG
        // memulangkan prop 'filters' yang betul — jadi ujian ini
        // dilangkau di SQLite dan akan LULUS di MySQL (pengeluaran).
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'Dashboard super_admin penuh guna SQL khusus MySQL (REGEXP, dan '.
                'HAVING tanpa GROUP BY pada $petugasStats) yang gagal di SQLite '.
                'tidak kira data — sedia ada, tidak berkaitan pembaikan ini.'
            );
        }

        $this->actingAs($this->user())
            ->get(route('dashboard', ['negeri_id' => 5, 'bandar_id' => 40]))
            ->assertInertia(fn ($page) => $page
                ->where('filters.negeri_id', '5')
                ->where('filters.bandar_id', '40'));
    }

    /**
     * NAMA INI SENGAJA SEMPIT. Ujian ini membuktikan GERBANG PERANAN sahaja:
     * peranan berskop tidak pernah sampai ke dashboard yang boleh ditapis
     * (DashboardController.php:32-34 memulangkan Dashboard/UserDashboard tanpa
     * sebarang prop sebelum satu pun parameter penapis dibaca).
     *
     * Ia TIDAK membuktikan bahawa penapis yang diingat menyempitkan dan bukan
     * meluaskan. Sifat itu benar dengan pembacaan kod — DashboardController
     * :56-69 (skop wilayah pengguna) dan :72-83 (penapis permintaan) kedua-dua
     * menambah where() pada pembina yang SAMA, jadi ia bergabung secara AND —
     * tetapi tiada ujian menegakkannya, kerana laluan itu tidak dapat dicapai
     * oleh mana-mana peranan berskop hari ini. Jika dashboard penuh yang
     * berskop pernah dibuka kepada peranan bukan super_admin, ujian ini akan
     * KEKAL HIJAU sementara sifat keselamatan itu menjadi tidak diuji.
     */
    public function test_scoped_roles_never_reach_the_filterable_dashboard(): void
    {
        // Penapis yang diingat MENYEMPITKAN di dalam sempadan kebenaran
        // pengguna; ia tidak boleh MELUASKANNYA.
        //
        // PENTING: peranan 'admin' tidak sekali-kali sampai ke sempadan
        // penskopan di DashboardController.php:55-63 — baris 32-34 pengawal
        // memulangkan 'Dashboard/UserDashboard' (TANPA prop) untuk admin/
        // super_user/user SEBELUM satu parameter penapis pun dibaca. Jadi
        // menyemak `totalPengundi === 0` (cadangan asal) akan GAGAL SENTIASA
        // dengan "Property [totalPengundi] does not exist" — bukan kerana
        // penskopan berfungsi, tetapi kerana prop itu tidak wujud langsung
        // pada laluan ini. Itu bukan bukti tentang peluasan akses.
        //
        // Pengesahan yang lebih kukuh: sahkan KOMPONEN yang dipulangkan DAN
        // ketiadaan LANGSUNG sebarang prop berskop-bandar ('totalPengundi').
        // Ini terbukti benar tidak kira nilai bandar_id yang diingat —
        // pintu masuk kepada pertanyaan berskop itu sendiri tidak pernah
        // dibuka untuk peranan ini, jadi peluasan akses mustahil secara
        // struktur, bukan sekadar kebetulan mengira sifar.
        $negeri = \App\Models\Negeri::create(['nama' => 'Negeri Sembilan']);
        $kita = \App\Models\Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id]);
        $orangLain = \App\Models\Bandar::create(['nama' => 'Seremban', 'negeri_id' => $negeri->id]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'telephone' => '01277'.random_int(10000, 99999),
            'negeri_id' => $negeri->id,
            'bandar_id' => $kita->id,
        ]);

        // Cuba mencapai Bandar orang lain melalui penapis, kemudian melalui
        // penapis yang DIINGAT pada lawatan kosong berikutnya.
        $this->actingAs($admin)->get(route('dashboard', ['bandar_id' => $orangLain->id]));

        $res = $this->actingAs($admin)->get(route('dashboard'));

        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('Dashboard/UserDashboard')
            ->missing('totalPengundi'));
    }

    /**
     * REGRESI SEBENAR: skop 'scoreboard' membawa kunci negeri_id/parlimen_id/
     * kadun_id lama sedangkan skrin itu telah ditulis semula untuk menghantar
     * kawasan_type/kawasan_id. Tiada satu pun kunci itu pernah muncul dalam
     * permintaan, jadi skop itu menyimpan dan memulihkan KEKOSONGAN — ciri
     * "ingat" mati SENYAP, tiada ujian gagal.
     *
     * Ujian ini memandu aliran SEBENAR (XHR data -> lawat semula halaman)
     * dan bukan mengesahkan konfigurasi terhadap dirinya sendiri, jadi
     * ketidakpadanan kunci begitu tidak boleh berulang tanpa disedari.
     */
    public function test_the_scoreboard_remembers_the_last_seat_across_navigation(): void
    {
        $negeri = \App\Models\Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = \App\Models\Bandar::create(['nama' => 'TAMPIN', 'kod_parlimen' => 'P132', 'negeri_id' => $negeri->id]);
        $dun = \App\Models\Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $bandar->id]);

        // Pemilih kerusi Scoreboard hanya menawarkan kerusi yang berborang 14
        // (ScoreboardController::index), jadi tanpa borang halaman itu 403.
        \App\Models\Borang14Form::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2026,
            'penjuru' => 2,
            'status' => 'draft',
            'parties' => [['nama' => 'KEADILAN'], ['nama' => 'BERSATU']],
        ]);

        // SeatScope menolak akaun yang belum diluluskan — UserFactory tidak
        // menetapkannya.
        $u = $this->user();
        $u->update(['status' => 'approved']);

        // Tinjauan biasa halaman — inilah yang merangkap penyimpanan.
        $this->actingAs($u)->getJson(route('pilihanraya.scoreboard.data', [
            'kawasan_type' => 'dun',
            'kawasan_id' => $dun->id,
        ]))->assertOk();

        // Pergi ke skrin lain, kemudian kembali: kerusi mesti masih di sana.
        $this->actingAs($u)->get(route('dashboard'));

        $this->actingAs($u)->get(route('pilihanraya.scoreboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rememberedFilters.kawasan_type', 'dun')
                ->where('rememberedFilters.kawasan_id', (string) $dun->id));
    }

    /**
     * Empat skop yang baru diaktifkan. Yang penting bukan sekadar "diingat",
     * tetapi Set Semula masih BERFUNGSI — itulah sebab keempat-empatnya
     * pernah dimatikan.
     *
     * @param  array<string, string>  $tapis
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('skopBaharuProvider')]
    public function test_newly_enabled_scopes_remember_then_forget_on_reset(
        string $namaLaluan,
        array $tapis,
        string $kunciDiuji,
    ): void {
        $u = $this->user();
        $sessionKey = \App\Support\FilterScopes::sessionKey(
            \App\Support\FilterScopes::forRoute($namaLaluan)['scope'],
        );

        $this->actingAs($u)->get(route($namaLaluan, $tapis))->assertOk();
        $this->assertSame($tapis[$kunciDiuji], session($sessionKey)[$kunciDiuji]);

        // Navigasi kosong -> dipulihkan.
        $this->actingAs($u)->get(route($namaLaluan))->assertOk();
        $this->assertSame($tapis[$kunciDiuji], session($sessionKey)[$kunciDiuji]);

        // Set Semula -> dilupakan, dan KEKAL dilupakan pada lawatan berikutnya.
        $this->actingAs($u)->get(route($namaLaluan, ['reset_filters' => 1]))->assertOk();
        $this->assertNull(session($sessionKey));

        $this->actingAs($u)->get(route($namaLaluan))->assertOk();
        $this->assertNull(session($sessionKey));
    }

    public static function skopBaharuProvider(): array
    {
        return [
            'users' => ['users.index', ['role' => 'admin'], 'role'],
            'data pengundi' => ['reports.data-pengundi.index', ['date_from' => '2026-01-01'], 'date_from'],
            'master data bandar' => ['master-data.bandar.index', ['negeri_id' => '3'], 'negeri_id'],
            'master data parlimen' => ['master-data.parlimen.index', ['negeri_id' => '3'], 'negeri_id'],
        ];
    }

    public function test_every_configured_scope_resolves_and_has_keys(): void
    {
        // Nama laluan yang salah eja gagal SENYAP — skrin itu sekadar tidak
        // mengingat apa-apa. Kunci setiap corak kepada laluan sebenar.
        $daftar = \Illuminate\Support\Facades\Route::getRoutes();

        foreach (config('sticky_filters') as $scope => $def) {
            $this->assertNotEmpty($def['keys'], "Skop {$scope} tiada kunci.");

            foreach ($def['routes'] as $pattern) {
                if (str_contains($pattern, '*')) {
                    continue; // corak wildcard disemak melalui laluan induknya
                }
                $this->assertNotNull(
                    collect($daftar)->first(fn ($r) => $r->getName() === $pattern),
                    "Laluan '{$pattern}' bagi skop '{$scope}' tidak wujud.",
                );
            }
        }
    }
}
