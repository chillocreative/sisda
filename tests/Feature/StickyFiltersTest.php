<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        config()->set('sticky_filters.ujian', [
            'routes' => ['ujian.penapis'],
            'keys' => ['negeri_id', 'bandar_id'],
        ]);

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

        config()->set('sticky_filters.ujian.routes', ['ujian.penapis', 'ujian.penapis.post']);
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

        // "Set Semula" menghantar kunci HADIR-TETAPI-KOSONG.
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
}
