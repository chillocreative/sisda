# Scoreboard Per Kerusi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let every Parlimen/DUN user in Malaysia own and publish one scoreboard for their own seat.

**Architecture:** A scoreboard becomes a property of a *seat* (`kawasan_type` + `kawasan_id`) rather than of a Borang 14 form. One new helper (`SeatScope`) holds the entire seat-permission rule; one new service (`ScoreboardPayload`) holds the read logic; the controller splits into an owner-facing one and a public one.

**Tech Stack:** Laravel 12, Inertia + React 18, MySQL (production) / SQLite (CI), Tailwind, axios, PHPUnit.

## Global Constraints

- **All user-facing text is Bahasa Melayu.** No i18n layer; strings hardcoded inline, matching surrounding copy.
- **Unknown is not zero.** Absent data stays `null` and renders `—`. Never `?? 0` on a figure shown to a user. `null >= 0` is `true` in JS — guard explicitly.
- **Authorization lives in controllers, not middleware.** Routes gated by `auth` only; each method performs its own check. `SeatScope` is a helper the controller calls.
- **Migrations run on every deploy** against live Borang 14 vote data. Never `Schema::drop` + recreate — reshape in place. MySQL error 1553 order: drop FK → drop index → drop column.
- **CI runs SQLite, production runs MySQL.** Raw `ALTER ... MODIFY` needs a driver branch.
- **No DB transactions in the HTTP layer today** — wrap any new multi-row write you add.
- **Test baseline is 20 pre-existing failures** (Breeze `Auth`/`Profile`; `UserFactory` omits the NOT NULL `telephone` column). Only worry if that count grows.
- **Full suite needs more memory:** `php -d memory_limit=1G vendor/bin/phpunit`. The default 128M exhausts in `Cpdf.php`.
- Build assets with `npm run build`; `public/build/` is committed to git.

---

### Task 1: SeatScope — the seat permission rule

**Files:**
- Create: `app/Support/SeatScope.php`
- Test: `tests/Unit/SeatScopeTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (`isSuperAdmin()`, `isAdmin()`, `isSuperUser()`, `isUser()`, `isKetuaPacaDun()`, `isApproved()`, `bandar_id`, `kadun_id`), `App\Models\Bandar`, `App\Models\Kadun`.
- Produces:
  - `SeatScope::DUN` = `'dun'`, `SeatScope::PARLIMEN` = `'parlimen'`
  - `SeatScope::allows(?User $user, string $type, int $id): bool`
  - `SeatScope::assert(?User $user, string $type, int $id): void` — `abort(403, 'Tindakan tidak dibenarkan.')`
  - `SeatScope::seats(?User $user): array` — list of `['type' => string, 'id' => int, 'nama' => string, 'kod' => ?string]`, DUNs then Parlimens, each sorted by `nama`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SeatScopeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Support\SeatScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SeatScope is the ONLY place the seat-permission rule is written. Every
 * scoreboard endpoint calls it, so this matrix is the feature's whole risk
 * surface. Three failure modes matter most: a null seat column must DENY
 * (the July 2026 IDORs were guards gated on nullable fields), an unapproved
 * user gets nothing regardless of role, and seats()/allows() must never
 * disagree — a seat absent from the picker must not be writable by hand.
 */
class SeatScopeTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $bandarA;
    private Bandar $bandarB;
    private Kadun $dunA1;
    private Kadun $dunA2;
    private Kadun $dunB1;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->bandarA = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $this->bandarB = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P130', 'negeri_id' => $negeri->id]);
        $this->dunA1 = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $this->bandarA->id]);
        $this->dunA2 = Kadun::create(['nama' => 'JOHOL', 'kod_dun' => 'N26', 'bandar_id' => $this->bandarA->id]);
        $this->dunB1 = Kadun::create(['nama' => 'BAHAU', 'kod_dun' => 'N31', 'bandar_id' => $this->bandarB->id]);
    }

    /** Built by hand, not by factory: UserFactory omits the NOT NULL telephone column. */
    private function user(string $role, ?int $bandarId = null, ?int $kadunId = null, string $status = 'approved'): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "Pengguna {$n}",
            'email' => "pengguna{$n}@example.test",
            'telephone' => '01300000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => $status,
            'bandar_id' => $bandarId,
            'kadun_id' => $kadunId,
        ]);
    }

    public function test_super_admin_may_touch_every_seat(): void
    {
        $u = $this->user('super_admin');

        $this->assertTrue(SeatScope::allows($u, SeatScope::DUN, $this->dunB1->id));
        $this->assertTrue(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarB->id));
    }

    public function test_admin_is_confined_to_own_parlimen_and_its_duns(): void
    {
        $u = $this->user('admin', bandarId: $this->bandarA->id);

        $this->assertTrue(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarA->id));
        $this->assertTrue(SeatScope::allows($u, SeatScope::DUN, $this->dunA1->id));
        $this->assertTrue(SeatScope::allows($u, SeatScope::DUN, $this->dunA2->id));

        $this->assertFalse(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarB->id));
        $this->assertFalse(SeatScope::allows($u, SeatScope::DUN, $this->dunB1->id));
    }

    public function test_user_and_super_user_get_only_their_own_dun(): void
    {
        foreach (['user', 'super_user'] as $role) {
            $u = $this->user($role, kadunId: $this->dunA1->id);

            $this->assertTrue(SeatScope::allows($u, SeatScope::DUN, $this->dunA1->id), $role);
            $this->assertFalse(SeatScope::allows($u, SeatScope::DUN, $this->dunA2->id), $role);
            $this->assertFalse(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarA->id), $role);
        }
    }

    public function test_ketua_paca_dun_gets_nothing(): void
    {
        $u = $this->user('ketua_paca_dun', kadunId: $this->dunA1->id);

        $this->assertFalse(SeatScope::allows($u, SeatScope::DUN, $this->dunA1->id));
        $this->assertSame([], SeatScope::seats($u));
    }

    public function test_null_seat_column_denies_instead_of_matching_everything(): void
    {
        $admin = $this->user('admin', bandarId: null);
        $this->assertFalse(SeatScope::allows($admin, SeatScope::PARLIMEN, $this->bandarA->id));
        $this->assertFalse(SeatScope::allows($admin, SeatScope::DUN, $this->dunA1->id));
        $this->assertSame([], SeatScope::seats($admin));

        $plain = $this->user('user', kadunId: null);
        $this->assertFalse(SeatScope::allows($plain, SeatScope::DUN, $this->dunA1->id));
        $this->assertSame([], SeatScope::seats($plain));
    }

    public function test_unapproved_user_gets_nothing_regardless_of_role(): void
    {
        $u = $this->user('admin', bandarId: $this->bandarA->id, status: 'pending');

        $this->assertFalse(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarA->id));
        $this->assertSame([], SeatScope::seats($u));
    }

    public function test_guest_and_unknown_seat_type_are_denied(): void
    {
        $this->assertFalse(SeatScope::allows(null, SeatScope::DUN, $this->dunA1->id));
        $this->assertSame([], SeatScope::seats(null));

        $u = $this->user('super_admin');
        $this->assertFalse(SeatScope::allows($u, 'negeri', 1));
    }

    public function test_seats_and_allows_never_disagree(): void
    {
        $users = [
            $this->user('super_admin'),
            $this->user('admin', bandarId: $this->bandarA->id),
            $this->user('super_user', kadunId: $this->dunA1->id),
            $this->user('user', kadunId: $this->dunB1->id),
        ];

        $everySeat = [
            [SeatScope::PARLIMEN, $this->bandarA->id],
            [SeatScope::PARLIMEN, $this->bandarB->id],
            [SeatScope::DUN, $this->dunA1->id],
            [SeatScope::DUN, $this->dunA2->id],
            [SeatScope::DUN, $this->dunB1->id],
        ];

        foreach ($users as $u) {
            $listed = collect(SeatScope::seats($u))->map(fn ($s) => $s['type'].':'.$s['id'])->all();

            foreach ($everySeat as [$type, $id]) {
                $inPicker = in_array($type.':'.$id, $listed, true);
                $this->assertSame(
                    $inPicker,
                    SeatScope::allows($u, $type, $id),
                    "Peranan {$u->role}: seats() dan allows() bercanggah pada {$type}:{$id}",
                );
            }
        }
    }

    public function test_assert_aborts_with_403_for_a_foreign_seat(): void
    {
        $u = $this->user('user', kadunId: $this->dunA1->id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        SeatScope::assert($u, SeatScope::DUN, $this->dunB1->id);
    }

    public function test_seats_carries_the_display_name_and_code(): void
    {
        $u = $this->user('user', kadunId: $this->dunA1->id);

        $this->assertSame(
            [['type' => 'dun', 'id' => $this->dunA1->id, 'nama' => 'PILAH', 'kod' => 'N27']],
            SeatScope::seats($u),
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SeatScopeTest`
Expected: FAIL — `Class "App\Support\SeatScope" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/SeatScope.php`:

```php
<?php

namespace App\Support;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya tempat peraturan kebenaran kerusi ditulis.
 *
 * Peranan → kerusi yang boleh disentuh:
 *   super_admin           semua Parlimen + semua DUN
 *   admin                 Parlimen sendiri + setiap DUN di bawahnya
 *   super_user / user     DUN sendiri sahaja
 *   ketua_paca_dun        tiada (peranannya satu menu: PACA)
 *
 * Tidak khusus kepada Scoreboard — Keanggotaan/Borang 14/PACA boleh
 * menerimanya kemudian, tetapi ITU DI LUAR SKOP kerja semasa.
 *
 * allows() dan seats() SENGAJA diterbitkan daripada tangga peranan yang sama.
 * Jika keduanya bercanggah, kerusi yang tidak muncul dalam pemilih boleh
 * ditulis dengan membina permintaan sendiri — kelas IDOR yang dihotfix pada
 * Julai 2026. SeatScopeTest memaku invarian itu.
 */
class SeatScope
{
    public const DUN = 'dun';

    public const PARLIMEN = 'parlimen';

    public static function allows(?User $user, string $type, int $id): bool
    {
        if (! $user || ! $user->isApproved()) {
            return false;
        }
        if (! in_array($type, [self::DUN, self::PARLIMEN], true)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->isKetuaPacaDun()) {
            return false;
        }

        if ($user->isAdmin()) {
            // Nullable lajur: seorang admin tanpa bandar_id mesti TIDAK padan
            // dengan apa-apa, bukan padan-semua.
            if (! $user->bandar_id) {
                return false;
            }

            return $type === self::PARLIMEN
                ? (int) $user->bandar_id === $id
                : Kadun::whereKey($id)->where('bandar_id', $user->bandar_id)->exists();
        }

        if ($user->isSuperUser() || $user->isUser()) {
            return $type === self::DUN
                && $user->kadun_id
                && (int) $user->kadun_id === $id;
        }

        return false; // Peranan tidak dikenali — tolak.
    }

    public static function assert(?User $user, string $type, int $id): void
    {
        abort_unless(self::allows($user, $type, $id), 403, 'Tindakan tidak dibenarkan.');
    }

    /** @return array<int, array{type: string, id: int, nama: string, kod: ?string}> */
    public static function seats(?User $user): array
    {
        if (! $user || ! $user->isApproved() || $user->isKetuaPacaDun()) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return array_merge(
                self::duns(Kadun::query()),
                self::parlimens(Bandar::query()),
            );
        }

        if ($user->isAdmin()) {
            if (! $user->bandar_id) {
                return [];
            }

            return array_merge(
                self::duns(Kadun::where('bandar_id', $user->bandar_id)),
                self::parlimens(Bandar::whereKey($user->bandar_id)),
            );
        }

        if ($user->isSuperUser() || $user->isUser()) {
            if (! $user->kadun_id) {
                return [];
            }

            return self::duns(Kadun::whereKey($user->kadun_id));
        }

        return [];
    }

    /** @return array<int, array{type: string, id: int, nama: string, kod: ?string}> */
    private static function duns(Builder $q): array
    {
        return $q->orderBy('nama')->get(['id', 'nama', 'kod_dun'])
            ->map(fn ($k) => [
                'type' => self::DUN,
                'id' => (int) $k->id,
                'nama' => (string) $k->nama,
                'kod' => $k->kod_dun ? strtoupper($k->kod_dun) : null,
            ])->all();
    }

    /** @return array<int, array{type: string, id: int, nama: string, kod: ?string}> */
    private static function parlimens(Builder $q): array
    {
        return $q->orderBy('nama')->get(['id', 'nama', 'kod_parlimen'])
            ->map(fn ($b) => [
                'type' => self::PARLIMEN,
                'id' => (int) $b->id,
                'nama' => (string) $b->nama,
                'kod' => $b->kod_parlimen ? strtoupper($b->kod_parlimen) : null,
            ])->all();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SeatScopeTest`
Expected: PASS, 10 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support/SeatScope.php tests/Unit/SeatScopeTest.php
git commit -m "SeatScope: satu tempat untuk peraturan kebenaran kerusi"
```

---

### Task 2: Reshape the `scoreboards` table

**Files:**
- Create: `database/migrations/2026_07_31_100001_reshape_scoreboards_per_kerusi.php`
- Test: `tests/Feature/ScoreboardMigrationTest.php`

**Interfaces:**
- Consumes: existing `scoreboards` (`id`, `borang14_form_id` UNIQUE + FK, `title`, `minima`, `logo_path`, `candidates`, timestamps), `borang14_forms` (`kawasan_type`, `kawasan_id`, `parties` json, `penjuru`).
- Produces: `scoreboards` with `kawasan_type`, `kawasan_id`, `status`, `kod`, `pihak_kami`; `borang14_form_id` nullable and no longer unique; `UNIQUE(kawasan_type, kawasan_id)` and `UNIQUE(kod)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ScoreboardMigrationTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Produksi memegang papan markah sebenar. Migrasi ini meruntuhkan berbilang
 * papan per kerusi kepada satu, jadi ujian ini memaku: bentuk skema baharu,
 * papan mana yang terselamat, dan bahawa down() enggan berjalan.
 */
class ScoreboardMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_has_the_seat_shape(): void
    {
        foreach (['kawasan_type', 'kawasan_id', 'status', 'kod', 'pihak_kami', 'updated_by'] as $col) {
            $this->assertTrue(Schema::hasColumn('scoreboards', $col), "Lajur {$col} tiada.");
        }
    }

    public function test_borang14_form_id_is_nullable_so_a_board_can_exist_before_its_form(): void
    {
        $id = DB::table('scoreboards')->insertGetId([
            'kawasan_type' => 'dun',
            'kawasan_id' => 4242,
            'borang14_form_id' => null,
            'title' => 'SCOREBOARD',
            'status' => 'draf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotNull(DB::table('scoreboards')->find($id));
    }

    public function test_one_board_per_seat_is_enforced(): void
    {
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 5150, 'title' => 'A',
            'status' => 'draf', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 5150, 'title' => 'B',
            'status' => 'draf', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_public_code_is_unique_across_all_boards(): void
    {
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 6001, 'title' => 'A', 'kod' => 'N27',
            'status' => 'tersiar', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'parlimen', 'kawasan_id' => 6002, 'title' => 'B', 'kod' => 'N27',
            'status' => 'tersiar', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_down_refuses_rather_than_lose_data(): void
    {
        $migration = require database_path('migrations/2026_07_31_100001_reshape_scoreboards_per_kerusi.php');

        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 7001, 'title' => 'A',
            'status' => 'draf', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ScoreboardMigrationTest`
Expected: FAIL — `Lajur kawasan_type tiada.`

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_31_100001_reshape_scoreboards_per_kerusi.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Papan markah beralih daripada milik satu Borang 14 kepada milik satu KERUSI.
 *
 * Sebelum: UNIQUE(borang14_form_id) — satu DUN boleh memegang beberapa papan,
 * dan papan yang dipaparkan ialah "Borang 14 dengan updated_at terkini", yang
 * bertukar secara senyap apabila senario lain disunting.
 * Selepas: UNIQUE(kawasan_type, kawasan_id) — satu papan bagi satu kerusi,
 * dengan borang14_form_id sebagai sumber undi PILIHAN pemilik.
 *
 * Turutan MySQL (ralat 1553): gugur FK → gugur index → ubah lajur → pasang
 * semula FK. Menggugurkan index unique yang disandari FK tanpa menggugurkan FK
 * dahulu akan gagal pada MySQL.
 *
 * BACA 2026_07_16_100001_reshape_borang14_forms.php sebelum menyunting fail
 * ini — ia mendokumenkan perangkap 1553 dan perangkap rebuild SQLite.
 */
return new class extends Migration
{
    /** Digunakan sekali sahaja untuk mengisi pihak_kami papan sedia ada. */
    private const PH_PARTIES = ['KEADILAN', 'PKR', 'DAP', 'AMANAH', 'MUDA'];

    public function up(): void
    {
        if (Schema::hasColumn('scoreboards', 'kawasan_type')) {
            return; // Sudah dibentuk semula.
        }

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->string('kawasan_type', 10)->nullable()->after('id');
            $table->unsignedBigInteger('kawasan_id')->nullable()->after('kawasan_type');
            $table->string('status', 10)->default('draf')->after('minima');
            $table->string('kod', 12)->nullable()->after('status');
            $table->json('pihak_kami')->nullable()->after('candidates');
            // Seorang admin Parlimen dan pemilik DUN boleh menyunting papan yang
            // SAMA. Tiada kunci dibina; kita hanya menjadikan perlanggaran
            // KELIHATAN dengan menunjukkan siapa menyimpan terakhir.
            $table->foreignId('updated_by')->nullable()->after('pihak_kami')
                ->constrained('users')->nullOnDelete();
        });

        // Pemadaman fail TIDAK boleh berlaku di dalam transaksi: jika transaksi
        // digulung semula, baris pangkalan data kembali tetapi fail imej sudah
        // hilang selama-lamanya. Kumpul dahulu, padam selepas komit.
        $yatim = [];
        DB::transaction(function () use (&$yatim) {
            $this->backfillSeats();
            $this->backfillPihakKami();
            $yatim = $this->collapseDuplicateBoards();
        });

        foreach ($yatim as $path) {
            if (str_starts_with($path, 'uploads/') && is_file(public_path($path))) {
                @unlink(public_path($path));
            }
        }

        // FK dahulu, kemudian index unique (ralat 1553), kemudian lajur nullable.
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropForeign(['borang14_form_id']);
        });
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropUnique(['borang14_form_id']);
        });
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->unsignedBigInteger('borang14_form_id')->nullable()->change();
        });
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->nullOnDelete();
            $table->unique(['kawasan_type', 'kawasan_id'], 'scoreboards_kerusi_unique');
            $table->unique('kod', 'scoreboards_kod_unique');
        });
    }

    /** Kerusi papan diwarisi daripada Borang 14 yang dipegangnya hari ini. */
    private function backfillSeats(): void
    {
        DB::table('scoreboards')
            ->join('borang14_forms', 'scoreboards.borang14_form_id', '=', 'borang14_forms.id')
            ->update([
                'scoreboards.kawasan_type' => DB::raw('borang14_forms.kawasan_type'),
                'scoreboards.kawasan_id' => DB::raw('borang14_forms.kawasan_id'),
            ]);
    }

    /**
     * Papan sedia ada diserlahkan mengikut PH_PARTIES yang dikekod tetap dalam
     * pengawal. Tanda slot yang sepadan supaya serlahan semasa kekal dan tidak
     * reset kepada kosong apabila kod itu dibuang.
     */
    private function backfillPihakKami(): void
    {
        $rows = DB::table('scoreboards')
            ->join('borang14_forms', 'scoreboards.borang14_form_id', '=', 'borang14_forms.id')
            ->select('scoreboards.id', 'borang14_forms.parties')
            ->get();

        foreach ($rows as $row) {
            $parties = json_decode((string) $row->parties, true) ?: [];
            $slots = [];
            foreach ($parties as $i => $p) {
                $nama = strtoupper((string) ($p['nama'] ?? ''));
                if (in_array($nama, self::PH_PARTIES, true)) {
                    $slots[] = $i + 1;
                }
            }
            DB::table('scoreboards')->where('id', $row->id)->update(['pihak_kami' => json_encode($slots)]);
        }
    }

    /**
     * Satu kerusi boleh memegang beberapa papan hari ini. Kekalkan yang
     * updated_at terkini — itulah yang sedang dipaparkan — dan padam yang lain.
     *
     * Memulangkan senarai fail imej yatim (dirujuk HANYA oleh papan yang
     * dipadam) untuk dinyahpaut oleh pemanggil SELEPAS transaksi komit.
     *
     * @return array<int, string>
     */
    private function collapseDuplicateBoards(): array
    {
        $yatim = [];

        $groups = DB::table('scoreboards')
            ->whereNotNull('kawasan_type')
            ->select('kawasan_type', 'kawasan_id')
            ->groupBy('kawasan_type', 'kawasan_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $g) {
            $boards = DB::table('scoreboards')
                ->where('kawasan_type', $g->kawasan_type)
                ->where('kawasan_id', $g->kawasan_id)
                ->orderByDesc('updated_at')->orderByDesc('id')
                ->get();

            $winner = $boards->shift();
            $keep = $this->imagePaths($winner);

            foreach ($boards as $loser) {
                foreach (array_diff($this->imagePaths($loser), $keep) as $path) {
                    $yatim[] = $path;
                }
                DB::table('scoreboards')->where('id', $loser->id)->delete();
            }
        }

        return array_values(array_unique($yatim));
    }

    /** @return array<int, string> */
    private function imagePaths(object $board): array
    {
        $paths = array_filter([$board->logo_path ?? null]);
        foreach (json_decode((string) ($board->candidates ?? '[]'), true) ?: [] as $c) {
            if (! empty($c['gambar'])) {
                $paths[] = $c['gambar'];
            }
        }

        return array_values(array_unique($paths));
    }

    public function down(): void
    {
        if (! Schema::hasColumn('scoreboards', 'kawasan_type')) {
            return; // Skema baharu belum wujud — tiada apa untuk diterbalikkan.
        }

        $jumlah = DB::table('scoreboards')->count();

        throw new \RuntimeException(
            "Migrasi ini TIDAK BOLEH diterbalikkan (not reversible): terdapat {$jumlah} baris scoreboards. ".
            'Skema lama mengunci papan pada borang14_form_id UNIQUE — papan yang tiada sumber Borang 14 '.
            '(borang14_form_id NULL) tidak boleh diwakili langsung, dan papan yang runtuh semasa up() sudah '.
            'dipadam. SANDARKAN data dahulu dan tangani migrasi data secara manual jika rollback benar-benar '.
            'diperlukan.'
        );
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ScoreboardMigrationTest`
Expected: PASS, 5 tests.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_31_100001_reshape_scoreboards_per_kerusi.php tests/Feature/ScoreboardMigrationTest.php
git commit -m "Scoreboard: bentuk semula jadual kepada satu papan bagi satu kerusi"
```

---

### Task 3: Scoreboard model + ScoreboardPayload service

**Files:**
- Modify: `app/Models/Scoreboard.php`
- Create: `app/Services/Pilihanraya/ScoreboardPayload.php`
- Test: `tests/Feature/ScoreboardPayloadTest.php`

**Interfaces:**
- Consumes: `SeatScope::DUN` / `SeatScope::PARLIMEN`, `Borang14Reference::forKadun(int): ?array`, `Borang14Reference::forBandar(int): ?array`, `Borang14Form` (`kawasan_type`, `kawasan_id`, `penjuru`, `parties`, `votes()`), `Scoreboard`.
- Produces:
  - `Scoreboard::STATUS_DRAF` = `'draf'`, `Scoreboard::STATUS_TERSIAR` = `'tersiar'`
  - `ScoreboardPayload::forSeat(string $type, int $id): array` with keys `hasData`, `ready`, `needsBorang14`, `penjuru`, `penjuru_label`, `title`, `logo_url`, `minima`, `kod`, `status`, `dun`, `parlimen`, `negeri`, `rows` (`slot`, `parti`, `is_kami`, `calon`, `gambar`, `undi`), `undi_kami`, `total_keluar`, `total_berdaftar`, `leader_slot`, `sumber` (`id`, `label`) or `null`

Note: `is_ph` becomes `is_kami` and `ph_votes` becomes `undi_kami`. Both old names are removed — Task 6 and Task 7 update the JSX that reads them.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ScoreboardPayloadTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use App\Support\SeatScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreboardPayloadTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $bandar->id]);
    }

    private function form(): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2026,
            'penjuru' => 3,
            'status' => 'published',
            'parties' => [['nama' => 'KEADILAN'], ['nama' => 'BERSATU'], ['nama' => 'PAS']],
        ]);
    }

    public function test_board_without_a_chosen_source_reports_not_ready_rather_than_zero(): void
    {
        Scoreboard::create([
            'kawasan_type' => SeatScope::DUN,
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => null,
            'title' => 'SCOREBOARD',
            'status' => Scoreboard::STATUS_DRAF,
        ]);

        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertFalse($payload['ready']);
        $this->assertNull($payload['sumber']);
        // Tiada sumber bukan bermakna sifar undi.
        $this->assertArrayNotHasKey('total_keluar', $payload);
    }

    public function test_rows_follow_the_chosen_form_and_tag_only_the_owners_slots(): void
    {
        $form = $this->form();
        Scoreboard::create([
            'kawasan_type' => SeatScope::DUN,
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $form->id,
            'title' => 'PILAH 2026',
            'status' => Scoreboard::STATUS_TERSIAR,
            'kod' => 'N27',
            'pihak_kami' => [1, 3],
        ]);

        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertTrue($payload['ready']);
        $this->assertSame('PILAH 2026', $payload['title']);
        $this->assertSame('N27', $payload['kod']);
        $this->assertSame([true, false, true], array_column($payload['rows'], 'is_kami'));
        $this->assertSame($form->id, $payload['sumber']['id']);
        $this->assertSame('PRN 2026 · 3 Penjuru', $payload['sumber']['label']);
    }

    public function test_undi_kami_sums_only_the_tagged_slots(): void
    {
        $form = $this->form();
        Scoreboard::create([
            'kawasan_type' => SeatScope::DUN,
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $form->id,
            'title' => 'SCOREBOARD',
            'status' => Scoreboard::STATUS_DRAF,
            'pihak_kami' => [1],
        ]);

        // Borang14Vote fillable: borang14_form_id, pusat, saluran, slot, undi.
        $form->votes()->createMany([
            ['pusat' => 'PM1', 'saluran' => 1, 'slot' => 1, 'undi' => 120],
            ['pusat' => 'PM1', 'saluran' => 1, 'slot' => 2, 'undi' => 80],
            ['pusat' => 'PM1', 'saluran' => 1, 'slot' => 3, 'undi' => 30],
        ]);

        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertSame(120, $payload['undi_kami']);
        $this->assertSame(230, $payload['total_keluar']);
        $this->assertSame(1, $payload['leader_slot']);
    }

    public function test_a_seat_with_no_board_at_all_is_not_ready(): void
    {
        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertFalse($payload['ready']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ScoreboardPayloadTest`
Expected: FAIL — `Class "App\Services\Pilihanraya\ScoreboardPayload" not found`.

- [ ] **Step 3a: Update the model**

Replace `app/Models/Scoreboard.php` with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tetapan paparan papan markah bagi SATU KERUSI (Parlimen atau DUN).
 * Angka undi sebenar dibaca daripada borang14_votes; jadual ini hanya memegang
 * konfigurasi persembahan dan pilihan sumber undi.
 */
class Scoreboard extends Model
{
    public const STATUS_DRAF = 'draf';

    public const STATUS_TERSIAR = 'tersiar';

    protected $fillable = [
        'kawasan_type', 'kawasan_id', 'borang14_form_id',
        'title', 'minima', 'status', 'kod', 'logo_path', 'candidates', 'pihak_kami', 'updated_by',
    ];

    protected $casts = [
        'minima' => 'integer',
        'candidates' => 'array',
        'pihak_kami' => 'array',
    ];

    public function borang14Form(): BelongsTo
    {
        return $this->belongsTo(Borang14Form::class, 'borang14_form_id');
    }

    public function penyunting(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isTersiar(): bool
    {
        return $this->status === self::STATUS_TERSIAR;
    }
}
```

Add `use Illuminate\Database\Eloquent\Relations\BelongsTo;` (already present) — no new imports are needed because `User` and `Borang14Form` are in the same `App\Models` namespace.

- [ ] **Step 3b: Write the payload service**

Create `app/Services/Pilihanraya/ScoreboardPayload.php`:

```php
<?php

namespace App\Services\Pilihanraya;

use App\Models\Borang14Form;
use App\Models\Scoreboard;
use App\Support\Borang14Reference;
use App\Support\SeatScope;

/**
 * Membina muatan papan markah langsung bagi satu kerusi. Logik baca tulen —
 * tiada kesedaran tentang Request, dipanggil oleh laluan pemilik dan awam.
 *
 * "Tiada data" BUKAN sifar: apabila pemilik belum memilih sumber Borang 14,
 * muatan mengembalikan ready=false TANPA kunci angka langsung, supaya antara
 * muka memapar "—" dan bukan "0".
 */
class ScoreboardPayload
{
    private const PENJURU = [2 => '1 vs 1', 3 => '3 Penjuru', 4 => '4 Penjuru', 5 => '5 Penjuru', 6 => '6 Penjuru'];

    public static function forSeat(string $type, int $id): array
    {
        $reference = $type === SeatScope::PARLIMEN
            ? Borang14Reference::forBandar($id)
            : Borang14Reference::forKadun($id);

        $board = Scoreboard::where('kawasan_type', $type)->where('kawasan_id', $id)->first();

        if (! $reference) {
            return ['hasData' => false, 'ready' => false, 'sumber' => null];
        }

        $form = $board?->borang14_form_id
            ? Borang14Form::find($board->borang14_form_id)
            : null;

        if (! $form) {
            return [
                'hasData' => true,
                'ready' => false,
                'needsBorang14' => true,
                'sumber' => null,
                'title' => $board?->title ?? 'SCOREBOARD',
                'status' => $board?->status ?? Scoreboard::STATUS_DRAF,
                'kod' => $board?->kod,
            ];
        }

        $penjuru = (int) $form->penjuru;
        $parties = $form->parties ?? [];
        $candidates = collect($board?->candidates ?? [])->keyBy('slot');
        $kami = array_map('intval', $board?->pihak_kami ?? []);

        $tally = array_fill(1, $penjuru, 0);
        $sums = $form->votes()->where('slot', '>=', 1)
            ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')->pluck('total', 'slot');
        foreach ($sums as $slot => $total) {
            if ($slot >= 1 && $slot <= $penjuru) {
                $tally[$slot] = (int) $total;
            }
        }

        $berdaftar = 0;
        foreach ($reference['daerah_mengundi'] as $dm) {
            $berdaftar += (int) ($dm['jumlah_berdaftar'] ?? 0);
        }
        $berdaftar += (int) ($reference['undi_awal']['berdaftar'] ?? 0);
        $berdaftar += (int) ($reference['undi_pos']['berdaftar'] ?? 0);

        $rows = [];
        $undiKami = 0;
        foreach (range(1, $penjuru) as $slot) {
            $isKami = in_array($slot, $kami, true);
            $undi = $tally[$slot] ?? 0;
            if ($isKami) {
                $undiKami += $undi;
            }
            $rows[] = [
                'slot' => $slot,
                'parti' => $parties[$slot - 1]['nama'] ?? "Parti {$slot}",
                'is_kami' => $isKami,
                'calon' => $candidates[$slot]['nama'] ?? null,
                'gambar' => ! empty($candidates[$slot]['gambar']) ? asset($candidates[$slot]['gambar']) : null,
                'undi' => $undi,
            ];
        }

        $totalKeluar = array_sum($tally);

        return [
            'hasData' => true,
            'ready' => true,
            'penjuru' => $penjuru,
            'penjuru_label' => self::PENJURU[$penjuru] ?? '',
            'title' => $board?->title ?? 'SCOREBOARD',
            'logo_url' => $board?->logo_path ? asset($board->logo_path) : asset('images/logo.png'),
            'minima' => $board?->minima,
            'kod' => $board?->kod,
            'status' => $board?->status ?? Scoreboard::STATUS_DRAF,
            'dun' => $reference['dun'] ?? null,
            'parlimen' => $reference['parlimen'] ?? null,
            'negeri' => $reference['negeri'] ?? null,
            'rows' => $rows,
            'undi_kami' => $undiKami,
            'total_keluar' => $totalKeluar,
            'total_berdaftar' => $berdaftar,
            'leader_slot' => $totalKeluar > 0 ? collect($rows)->sortByDesc('undi')->first()['slot'] : null,
            'sumber' => ['id' => $form->id, 'label' => self::labelSumber($form)],
            'dikemaskini' => self::dikemaskini($board),
        ];
    }

    /**
     * Siapa menyimpan tetapan terakhir. Papan DUN boleh disunting oleh pemilik
     * DUN DAN admin Parlimennya — tiada kunci, jadi perlanggaran dibuat
     * kelihatan sahaja.
     */
    private static function dikemaskini(?Scoreboard $board): ?array
    {
        if (! $board?->updated_by) {
            return null; // Belum pernah disimpan melalui borang — bukan "tiada suntingan".
        }

        $board->loadMissing('penyunting');

        return [
            'nama' => $board->penyunting?->name,
            'pada' => $board->updated_at?->toIso8601String(),
        ];
    }

    /** "PRN 2026 · 3 Penjuru" */
    public static function labelSumber(Borang14Form $form): string
    {
        return strtoupper((string) $form->jenis_pr).' '.$form->tahun.' · '.(self::PENJURU[(int) $form->penjuru] ?? $form->penjuru.' Penjuru');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ScoreboardPayloadTest`
Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Scoreboard.php app/Services/Pilihanraya/ScoreboardPayload.php tests/Feature/ScoreboardPayloadTest.php
git commit -m "Scoreboard: asingkan ScoreboardPayload, pihak_kami gantikan PH tetap"
```

---

### Task 4: Owner controller + routes

**Files:**
- Modify: `app/Http/Controllers/ScoreboardController.php` (full rewrite of the owner half)
- Modify: `routes/web.php:459-462` (move out of the admin group)
- Test: `tests/Feature/ScoreboardAccessTest.php`

**Interfaces:**
- Consumes: `SeatScope::allows/assert/seats`, `ScoreboardPayload::forSeat`, `ScoreboardPayload::labelSumber`, `Scoreboard::STATUS_*`.
- Produces: routes `pilihanraya.scoreboard`, `pilihanraya.scoreboard.data`, `pilihanraya.scoreboard.settings`, `pilihanraya.scoreboard.publish`. Inertia page `Pilihanraya/Scoreboard` receives props `seats` (from `SeatScope::seats()`), `board`, `sumberList`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ScoreboardAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap hujung pemilik mesti memanggil SeatScope. Ujian ini memandu setiap
 * peranan melalui kerusi sendiri dan kerusi asing supaya pengawal tidak boleh
 * terlepas satu semakan (kelas IDOR Julai 2026).
 */
class ScoreboardAccessTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dunSendiri;
    private Kadun $dunAsing;
    private Bandar $parlimenSendiri;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimenSendiri = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $lain = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P130', 'negeri_id' => $negeri->id]);
        $this->dunSendiri = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $this->parlimenSendiri->id]);
        $this->dunAsing = Kadun::create(['nama' => 'BAHAU', 'kod_dun' => 'N31', 'bandar_id' => $lain->id]);
    }

    private function user(string $role, ?int $bandarId = null, ?int $kadunId = null): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "Pengguna {$n}",
            'email' => "akses{$n}@example.test",
            'telephone' => '01400000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => 'approved',
            'bandar_id' => $bandarId,
            'kadun_id' => $kadunId,
        ]);
    }

    public function test_plain_user_can_open_the_scoreboard_page(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)->get(route('pilihanraya.scoreboard'))->assertOk();
    }

    public function test_ketua_paca_dun_is_refused(): void
    {
        $u = $this->user('ketua_paca_dun', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)->get(route('pilihanraya.scoreboard'))->assertForbidden();
    }

    public function test_user_may_read_own_seat_but_not_a_foreign_one(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)
            ->getJson(route('pilihanraya.scoreboard.data', ['kawasan_type' => 'dun', 'kawasan_id' => $this->dunSendiri->id]))
            ->assertOk();

        $this->actingAs($u)
            ->getJson(route('pilihanraya.scoreboard.data', ['kawasan_type' => 'dun', 'kawasan_id' => $this->dunAsing->id]))
            ->assertForbidden();
    }

    public function test_user_may_not_write_a_foreign_seat(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.settings'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunAsing->id,
            'title' => 'DIRAMPAS',
        ])->assertForbidden();

        $this->assertDatabaseMissing('scoreboards', ['kawasan_id' => $this->dunAsing->id]);
    }

    public function test_admin_may_write_a_dun_inside_own_parlimen(): void
    {
        $u = $this->user('admin', bandarId: $this->parlimenSendiri->id);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.settings'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
            'title' => 'PILAH 2026',
            'pihak_kami' => [1],
        ])->assertOk();

        $this->assertDatabaseHas('scoreboards', [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
            'title' => 'PILAH 2026',
        ]);
    }

    public function test_publishing_requires_a_seat_code(): void
    {
        $tanpaKod = Kadun::create(['nama' => 'TIADA KOD', 'kod_dun' => null, 'bandar_id' => $this->parlimenSendiri->id]);
        $u = $this->user('admin', bandarId: $this->parlimenSendiri->id);

        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $tanpaKod->id,
            'title' => 'X', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.publish'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $tanpaKod->id,
            'status' => Scoreboard::STATUS_TERSIAR,
        ])->assertStatus(422);
    }

    public function test_publishing_stamps_the_uppercase_seat_code(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunSendiri->id,
            'title' => 'X', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.publish'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
            'status' => Scoreboard::STATUS_TERSIAR,
        ])->assertOk();

        $this->assertDatabaseHas('scoreboards', [
            'kawasan_id' => $this->dunSendiri->id,
            'status' => Scoreboard::STATUS_TERSIAR,
            'kod' => 'N27',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ScoreboardAccessTest`
Expected: FAIL — plain `user` is redirected by `EnsureAdmin`, and `pilihanraya.scoreboard.publish` does not exist.

- [ ] **Step 3a: Move the routes**

In `routes/web.php`, DELETE these three lines from the `['auth','admin']` group (currently at `:459-462`):

```php
    // Scoreboard — live election night board driven by Borang 14 figures
    Route::get('/scoreboard', [\App\Http\Controllers\ScoreboardController::class, 'index'])->name('scoreboard');
    Route::get('/scoreboard/data', [\App\Http\Controllers\ScoreboardController::class, 'data'])->name('scoreboard.data');
    Route::post('/scoreboard/settings', [\App\Http\Controllers\ScoreboardController::class, 'saveSettings'])->name('scoreboard.settings');
```

Then ADD a new group immediately after that group closes. Same prefix and name prefix, so `pilihanraya.scoreboard` keeps working everywhere:

```php
/*
 * Scoreboard — setiap pemilik kerusi (Parlimen/DUN) menguruskan papannya
 * sendiri, jadi ia TIDAK boleh berada dalam kumpulan 'admin': EnsureAdmin
 * akan menyekat peranan user/super_user yang justeru hendak dibenarkan.
 * Kebenaran per-kerusi dibuat dalam pengawal melalui SeatScope.
 */
Route::middleware(['auth'])->prefix('pilihanraya')->name('pilihanraya.')->group(function () {
    Route::get('/scoreboard', [\App\Http\Controllers\ScoreboardController::class, 'index'])->name('scoreboard');
    Route::get('/scoreboard/data', [\App\Http\Controllers\ScoreboardController::class, 'data'])->name('scoreboard.data');
    Route::post('/scoreboard/settings', [\App\Http\Controllers\ScoreboardController::class, 'saveSettings'])->name('scoreboard.settings');
    Route::post('/scoreboard/publish', [\App\Http\Controllers\ScoreboardController::class, 'publish'])->name('scoreboard.publish');
});
```

- [ ] **Step 3b: Rewrite the controller**

Replace `app/Http/Controllers/ScoreboardController.php` with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use App\Support\SeatScope;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Papan markah bagi PEMILIK kerusi. Laluan awam berada dalam
 * PublicScoreboardController.
 *
 * Laluan digelang oleh 'auth' sahaja; setiap kaedah memanggil
 * SeatScope::assert() sendiri, mengikut konvensyen projek.
 */
class ScoreboardController extends Controller
{
    public function index(Request $request)
    {
        $seats = SeatScope::seats($request->user());
        abort_if($seats === [], 403, 'Anda tiada kerusi untuk diuruskan.');

        return Inertia::render('Pilihanraya/Scoreboard', [
            'seats' => $seats,
        ]);
    }

    /** Muatan langsung — ditinjau setiap 4 saat oleh halaman pemilik. */
    public function data(Request $request)
    {
        [$type, $id] = $this->seatFromRequest($request);

        $payload = ScoreboardPayload::forSeat($type, $id);
        $payload['sumberList'] = $this->sumberList($type, $id);

        return $this->liveJson($payload);
    }

    /** Simpan tetapan persembahan + sumber undi + pihak kami. */
    public function saveSettings(Request $request)
    {
        [$type, $id] = $this->seatFromRequest($request);

        $validated = $request->validate([
            'title' => 'nullable|string|max:100',
            'minima' => 'nullable|integer|min:0|max:100000000',
            'borang14_form_id' => 'nullable|integer|exists:borang14_forms,id',
            'pihak_kami' => 'array',
            'pihak_kami.*' => 'integer|min:1|max:6',
            'candidates' => 'array',
            'candidates.*.slot' => 'required|integer|min:1|max:6',
            'candidates.*.nama' => 'nullable|string|max:120',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:4096',
            'photos' => 'array',
            'photos.*' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:4096',
        ]);

        // Sumber undi mesti milik kerusi ini — jika tidak, papan DUN Pilah
        // boleh dipaksa membaca undi DUN lain.
        if (! empty($validated['borang14_form_id'])) {
            $milik = Borang14Form::whereKey($validated['borang14_form_id'])
                ->where('kawasan_type', $type)->where('kawasan_id', $id)->exists();
            abort_unless($milik, 422, 'Borang 14 itu bukan milik kerusi ini.');
        }

        $board = Scoreboard::firstOrNew(['kawasan_type' => $type, 'kawasan_id' => $id]);
        $board->title = $validated['title'] ?: 'SCOREBOARD';
        $board->minima = $validated['minima'] ?? null;
        $board->borang14_form_id = $validated['borang14_form_id'] ?? null;
        $board->pihak_kami = array_values(array_unique(array_map('intval', $validated['pihak_kami'] ?? [])));
        $board->status ??= Scoreboard::STATUS_DRAF;
        $board->updated_by = $request->user()->id;

        if ($request->hasFile('logo')) {
            $this->deletePublic($board->logo_path);
            $board->logo_path = $this->storePublic($request->file('logo'), 'scoreboard/logo');
        }

        $existing = collect($board->candidates ?? [])->keyBy('slot');
        $candidates = [];
        foreach ($validated['candidates'] ?? [] as $c) {
            $slot = (int) $c['slot'];
            $gambar = $existing[$slot]['gambar'] ?? null;

            if ($request->hasFile("photos.{$slot}")) {
                $this->deletePublic($gambar);
                $gambar = $this->storePublic($request->file("photos.{$slot}"), 'scoreboard/calon');
            }

            $candidates[] = ['slot' => $slot, 'nama' => $c['nama'] ?? null, 'gambar' => $gambar];
        }
        $board->candidates = $candidates;

        // Satu baris sahaja ditulis — transaksi tidak diperlukan di sini.
        // (Kekangan projek: balut tulisan BERBILANG baris, bukan tulisan tunggal.)
        $board->save();

        return response()->json(['ok' => true]);
    }

    /** Togol Draf ⇄ Tersiar. Menyiarkan mengecap kod kerusi pada papan. */
    public function publish(Request $request)
    {
        [$type, $id] = $this->seatFromRequest($request);

        $validated = $request->validate([
            'status' => 'required|in:'.Scoreboard::STATUS_DRAF.','.Scoreboard::STATUS_TERSIAR,
        ]);

        $board = Scoreboard::where('kawasan_type', $type)->where('kawasan_id', $id)->first();
        abort_unless($board, 404, 'Papan markah belum wujud bagi kerusi ini.');

        if ($validated['status'] === Scoreboard::STATUS_DRAF) {
            $board->status = Scoreboard::STATUS_DRAF;
            $board->save();

            return response()->json(['ok' => true, 'status' => $board->status, 'kod' => $board->kod]);
        }

        $kod = $this->kodKerusi($type, $id);
        if (! $kod) {
            return response()->json([
                'message' => $type === SeatScope::PARLIMEN
                    ? 'Kerusi ini tiada Kod Parlimen. Isi medan itu dalam Data Induk > Parlimen sebelum menyiarkan.'
                    : 'Kerusi ini tiada Kod DUN. Isi medan itu dalam Data Induk > DUN sebelum menyiarkan.',
            ], 422);
        }

        $dipegang = Scoreboard::where('kod', $kod)
            ->where(fn ($q) => $q->where('kawasan_type', '!=', $type)->orWhere('kawasan_id', '!=', $id))
            ->exists();
        if ($dipegang) {
            return response()->json([
                'message' => "Kod {$kod} sudah digunakan papan markah kerusi lain. Betulkan kod dalam Data Induk.",
            ], 422);
        }

        $board->kod = $kod;
        $board->status = Scoreboard::STATUS_TERSIAR;
        $board->save();

        return response()->json([
            'ok' => true,
            'status' => $board->status,
            'kod' => $board->kod,
            'url' => route('scoreboard.public', ['kod' => strtolower($board->kod)]),
        ]);
    }

    /**
     * Baca kerusi daripada permintaan dan sahkan kebenaran SEBELUM apa-apa
     * kerja lain. Setiap kaedah awam bermula di sini.
     *
     * @return array{0: string, 1: int}
     */
    private function seatFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'kawasan_type' => 'required|in:'.SeatScope::DUN.','.SeatScope::PARLIMEN,
            'kawasan_id' => 'required|integer|min:1',
        ]);

        $type = $validated['kawasan_type'];
        $id = (int) $validated['kawasan_id'];

        SeatScope::assert($request->user(), $type, $id);

        return [$type, $id];
    }

    private function kodKerusi(string $type, int $id): ?string
    {
        $kod = $type === SeatScope::PARLIMEN
            ? Bandar::whereKey($id)->value('kod_parlimen')
            : Kadun::whereKey($id)->value('kod_dun');

        $kod = strtoupper(trim((string) $kod));

        return $kod !== '' ? $kod : null;
    }

    /** Senario Borang 14 kerusi ini, untuk dropdown "Sumber Undi". */
    private function sumberList(string $type, int $id): array
    {
        return Borang14Form::where('kawasan_type', $type)->where('kawasan_id', $id)
            ->orderByDesc('tahun')->orderBy('jenis_pr')->get()
            ->map(fn ($f) => ['id' => $f->id, 'label' => ScoreboardPayload::labelSumber($f)])
            ->all();
    }

    /** JSON yang tidak boleh dicache — ditinjau langsung semasa kemasukan undi. */
    private function liveJson(array $payload)
    {
        return response()->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Simpan imej terus di bawah public/ supaya dihidangkan pelayan web melalui
     * asset() — tiada kebergantungan pada symlink storage:link.
     */
    private function storePublic(UploadedFile $file, string $dir): string
    {
        // Sambungan diterbitkan daripada KANDUNGAN fail (bukan nama daripada
        // pelanggan) dan dipaku pada senarai izin imej, supaya polyglot bernama
        // .php tidak boleh ditulis ke dalam webroot lalu dilaksanakan.
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower($file->guessExtension() ?: '');
        abort_unless(in_array($ext, $allowed, true), 422, 'Format gambar tidak sah.');

        $name = bin2hex(random_bytes(16)).'.'.$ext;
        $file->move(public_path('uploads/'.$dir), $name);
        $this->guardUploadsDir();

        return 'uploads/'.$dir.'/'.$name;
    }

    /** Pertahanan berlapis (Apache): halang fail di bawah uploads/ dijalankan sebagai PHP. */
    private function guardUploadsDir(): void
    {
        $htaccess = public_path('uploads/.htaccess');
        if (! is_file($htaccess)) {
            file_put_contents($htaccess, <<<'HT'
                php_flag engine off
                RemoveHandler .php .phtml .phar .phps
                <FilesMatch "\.(php|phtml|phar|phps)$">
                    Require all denied
                </FilesMatch>
                HT);
        }
    }

    private function deletePublic(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/') && is_file(public_path($path))) {
            @unlink(public_path($path));
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ScoreboardAccessTest`
Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ScoreboardController.php routes/web.php tests/Feature/ScoreboardAccessTest.php
git commit -m "Scoreboard: pengawal pemilik ikut SeatScope, keluar dari kumpulan admin"
```

---

### Task 5: Public controller + code routes

**Files:**
- Create: `app/Http/Controllers/PublicScoreboardController.php`
- Modify: `routes/web.php:14-19`
- Test: `tests/Feature/ScoreboardPublicTest.php`

**Interfaces:**
- Consumes: `ScoreboardPayload::forSeat`, `Scoreboard::STATUS_TERSIAR`.
- Produces: routes `scoreboard.public` (`/scoreboard/{kod}`), `scoreboard.public.data` (`/scoreboard/{kod}/data`), `scoreboard.public.index` (`/scoreboard`), `scoreboard.public.legacy` (`/scoreboard/{kadun}` numeric → 301). Inertia pages `Public/Scoreboard` (props `board`, `kod`) and `Public/ScoreboardIndex` (prop `boards`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ScoreboardPublicTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Papan draf tidak boleh bocor. Kod tidak dikenali dan papan draf mesti 404
 * secara SAMA supaya tiada petunjuk kerusi mana yang wujud.
 */
class ScoreboardPublicTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $bandar->id]);
    }

    private function board(string $status, ?string $kod = 'N27'): Scoreboard
    {
        return Scoreboard::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'title' => 'PILAH',
            'status' => $status,
            'kod' => $kod,
        ]);
    }

    public function test_published_board_is_visible_without_login(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/n27')->assertOk();
        $this->getJson('/scoreboard/n27/data')->assertOk();
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/N27')->assertOk();
    }

    public function test_draft_board_404s_exactly_like_an_unknown_code(): void
    {
        $this->board(Scoreboard::STATUS_DRAF);

        $this->get('/scoreboard/n27')->assertNotFound();
        $this->get('/scoreboard/n99')->assertNotFound();
        $this->getJson('/scoreboard/n27/data')->assertNotFound();
    }

    public function test_legacy_numeric_url_redirects_to_the_code_url(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/'.$this->dun->id)
            ->assertRedirect('/scoreboard/n27');
    }

    public function test_legacy_numeric_url_for_an_unpublished_seat_404s(): void
    {
        $this->board(Scoreboard::STATUS_DRAF);

        $this->get('/scoreboard/'.$this->dun->id)->assertNotFound();
    }

    public function test_bare_index_lists_only_published_boards(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $lain = Kadun::create(['nama' => 'JOHOL', 'kod_dun' => 'N26', 'bandar_id' => $this->dun->bandar_id]);
        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $lain->id,
            'title' => 'JOHOL', 'status' => Scoreboard::STATUS_DRAF, 'kod' => null,
        ]);

        $response = $this->get('/scoreboard')->assertOk();
        $boards = $response->viewData('page')['props']['boards'];

        $this->assertCount(1, $boards);
        $this->assertSame('N27', $boards[0]['kod']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ScoreboardPublicTest`
Expected: FAIL — `/scoreboard/n27` does not match the numeric-only route, returns 404 for the published case too.

- [ ] **Step 3a: Write the public controller**

Create `app/Http/Controllers/PublicScoreboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use App\Support\SeatScope;
use Inertia\Inertia;

/**
 * Papan markah awam — tiada log masuk, tiada tulis.
 *
 * SATU peraturan mengawal segalanya di sini: hanya papan berstatus 'tersiar'
 * boleh diselesaikan. Papan draf dan kod yang tidak wujud memulangkan 404 yang
 * SAMA, supaya tiada petunjuk kerusi mana yang wujud.
 */
class PublicScoreboardController extends Controller
{
    /** Senarai ringkas papan tersiar — hanya apa yang pemilik pilih untuk siarkan. */
    public function index()
    {
        $boards = Scoreboard::where('status', Scoreboard::STATUS_TERSIAR)
            ->whereNotNull('kod')->orderBy('kod')->get(['kawasan_type', 'kawasan_id', 'kod', 'title'])
            ->map(fn ($b) => [
                'kod' => $b->kod,
                'title' => $b->title,
                'nama' => $this->namaKerusi($b->kawasan_type, (int) $b->kawasan_id),
                'url' => route('scoreboard.public', ['kod' => strtolower($b->kod)]),
            ])->values()->all();

        return Inertia::render('Public/ScoreboardIndex', ['boards' => $boards]);
    }

    public function show(string $kod)
    {
        $board = $this->tersiar($kod);

        return Inertia::render('Public/Scoreboard', [
            'kod' => strtolower($board->kod),
            'board' => ScoreboardPayload::forSeat($board->kawasan_type, (int) $board->kawasan_id),
        ]);
    }

    public function data(string $kod)
    {
        $board = $this->tersiar($kod);

        return response()->json(ScoreboardPayload::forSeat($board->kawasan_type, (int) $board->kawasan_id))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** URL lama /scoreboard/{kadun} — kekalkan pautan yang sudah tersebar. */
    public function legacy(int $kadun)
    {
        $board = Scoreboard::where('kawasan_type', SeatScope::DUN)
            ->where('kawasan_id', $kadun)
            ->where('status', Scoreboard::STATUS_TERSIAR)
            ->whereNotNull('kod')
            ->first();

        abort_unless($board, 404);

        return redirect()->route('scoreboard.public', ['kod' => strtolower($board->kod)], 301);
    }

    private function tersiar(string $kod): Scoreboard
    {
        $board = Scoreboard::where('kod', strtoupper($kod))
            ->where('status', Scoreboard::STATUS_TERSIAR)
            ->first();

        abort_unless($board, 404);

        return $board;
    }

    private function namaKerusi(string $type, int $id): string
    {
        return $type === SeatScope::PARLIMEN
            ? (string) Bandar::whereKey($id)->value('nama')
            : (string) Kadun::whereKey($id)->value('nama');
    }
}
```

- [ ] **Step 3b: Replace the public routes**

In `routes/web.php`, REPLACE lines 14-19 (the existing public scoreboard block) with:

```php
/*
 * Papan markah awam — tiada log masuk. Hanya papan 'tersiar' diselesaikan.
 *
 * TURUTAN PENDAFTARAN PENTING: laluan angka (URL lama) mesti didaftarkan
 * SEBELUM laluan kod, kerana kedua-duanya berkongsi satu segmen. Kod kerusi
 * sentiasa bermula dengan huruf (N27, P129) manakala id lama sentiasa angka
 * penuh, jadi kekangan di bawah tidak boleh bertindih.
 */
Route::get('/scoreboard', [\App\Http\Controllers\PublicScoreboardController::class, 'index'])
    ->name('scoreboard.public.index');
Route::get('/scoreboard/{kadun}', [\App\Http\Controllers\PublicScoreboardController::class, 'legacy'])
    ->whereNumber('kadun')->name('scoreboard.public.legacy');
Route::get('/scoreboard/{kod}/data', [\App\Http\Controllers\PublicScoreboardController::class, 'data'])
    ->where('kod', '[A-Za-z]\d+')->name('scoreboard.public.data');
Route::get('/scoreboard/{kod}', [\App\Http\Controllers\PublicScoreboardController::class, 'show'])
    ->where('kod', '[A-Za-z]\d+')->name('scoreboard.public');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ScoreboardPublicTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PublicScoreboardController.php routes/web.php tests/Feature/ScoreboardPublicTest.php
git commit -m "Scoreboard: pengawal awam berasingan, URL guna kod kerusi"
```

---

### Task 6: Owner page UI

**Files:**
- Modify: `resources/js/Pages/Pilihanraya/Scoreboard.jsx`

**Interfaces:**
- Consumes: prop `seats` (`[{type, id, nama, kod}]`); `pilihanraya.scoreboard.data` returns the `ScoreboardPayload::forSeat` shape plus `sumberList`; `pilihanraya.scoreboard.settings`; `pilihanraya.scoreboard.publish`.
- Produces: no downstream consumers.

- [ ] **Step 1: Replace the seat picker in `ScoreboardBody`**

The old three-dropdown Negeri → Parlimen → DUN cascade is driven by `negeriList`/`parlimenList`/`kadunList`, which the controller no longer sends. Replace the component signature and picker state:

```jsx
function ScoreboardBody({ seats }) {
    const [settingsOpen, setSettingsOpen] = useState(false);
    // Satu kerusi → terus dimuatkan, tiada pemilih. Inilah pembetulan skrin
    // tiga dropdown kosong bagi pengguna yang memiliki satu kerusi sahaja.
    const [seat, setSeat] = useState(seats.length === 1 ? seats[0] : null);
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [updatedAt, setUpdatedAt] = useState(null);
    const [fullscreen, setFullscreen] = useState(false);

    const ready = data?.ready;

    const fetchData = useCallback((showSpinner = false) => {
        if (!seat) { setData(null); return; }
        if (showSpinner) setLoading(true);
        // `_t` cache-buster keeps every poll fresh (no stale browser/CDN cache).
        axios.get(route('pilihanraya.scoreboard.data'), {
            params: { kawasan_type: seat.type, kawasan_id: seat.id, _t: Date.now() },
        })
            .then(({ data: d }) => { setData(d); setUpdatedAt(new Date()); })
            .finally(() => setLoading(false));
    }, [seat]);

    useEffect(() => {
        fetchData(true);
        if (!seat) return undefined;
        const id = setInterval(() => fetchData(false), POLL_MS);
        return () => clearInterval(id);
    }, [fetchData, seat]);
```

And the picker markup — a single dropdown, rendered only when the user holds more than one seat:

```jsx
{seats.length > 1 && (
    <div className={`${t.card} mb-5`}>
        <label className="block text-sm font-medium text-slate-700 mb-1">Kerusi</label>
        <select
            value={seat ? `${seat.type}:${seat.id}` : ''}
            onChange={(e) => setSeat(seats.find((s) => `${s.type}:${s.id}` === e.target.value) || null)}
            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
        >
            <option value="">Pilih Kerusi</option>
            {seats.map((s) => (
                <option key={`${s.type}:${s.id}`} value={`${s.type}:${s.id}`}>
                    {s.type === 'parlimen' ? 'Parlimen' : 'DUN'} {s.nama}{s.kod ? ` (${s.kod})` : ''}
                </option>
            ))}
        </select>
    </div>
)}
```

- [ ] **Step 2: Update the `is_ph` reference in `Board`**

At the candidate-card party line (was `resources/js/Pages/Pilihanraya/Scoreboard.jsx:169`), the payload key changed:

```jsx
<div className="text-xs font-bold uppercase tracking-wide" style={{ color }}>{r.parti}{r.is_kami ? ' · KAMI' : ''}</div>
```

- [ ] **Step 3: Extend `SettingsModal`**

Change its signature to `{ seat, board, onClose, onSaved }` and add the three new controls. Replace the `submit` function and add the new fields before the existing "Calon" block:

```jsx
function SettingsModal({ seat, board, onClose, onSaved }) {
    const { t } = usePilihanrayaTheme();
    const rows = board?.rows || [];
    const [title, setTitle] = useState(board?.title || 'SCOREBOARD');
    const [minima, setMinima] = useState(board?.minima ?? '');
    const [sumber, setSumber] = useState(board?.sumber?.id ?? '');
    const [kami, setKami] = useState(() => rows.filter((r) => r.is_kami).map((r) => r.slot));
    const [names, setNames] = useState(() => rows.map((r) => r.calon || ''));
    const [logoFile, setLogoFile] = useState(null);
    const [photoFiles, setPhotoFiles] = useState({});
    const [saving, setSaving] = useState(false);
    const [ralat, setRalat] = useState(null);

    const toggleKami = (slot) =>
        setKami((prev) => (prev.includes(slot) ? prev.filter((s) => s !== slot) : [...prev, slot]));

    const submit = () => {
        setSaving(true);
        setRalat(null);
        const fd = new FormData();
        fd.append('kawasan_type', seat.type);
        fd.append('kawasan_id', seat.id);
        fd.append('title', title || 'SCOREBOARD');
        // Minima kosong bermakna TIADA sasaran, bukan sifar.
        if (minima !== '') fd.append('minima', minima);
        if (sumber !== '') fd.append('borang14_form_id', sumber);
        kami.forEach((slot, i) => fd.append(`pihak_kami[${i}]`, slot));
        rows.forEach((r, i) => {
            fd.append(`candidates[${i}][slot]`, r.slot);
            fd.append(`candidates[${i}][nama]`, names[i] || '');
            if (photoFiles[r.slot]) fd.append(`photos[${r.slot}]`, photoFiles[r.slot]);
        });
        if (logoFile) fd.append('logo', logoFile);

        axios.post(route('pilihanraya.scoreboard.settings'), fd, { headers: { 'Content-Type': 'multipart/form-data' } })
            .then(() => { onSaved(); onClose(); })
            .catch((e) => { setRalat(e.response?.data?.message || 'Gagal menyimpan tetapan.'); setSaving(false); });
    };
```

New form controls, placed inside the existing two-column grid:

```jsx
<div>
    <label className="block text-sm font-medium text-slate-700 mb-1">Sumber Undi</label>
    <select value={sumber} onChange={(e) => setSumber(e.target.value)} className={field}>
        <option value="">Belum pilih sumber</option>
        {(board?.sumberList || []).map((s) => (
            <option key={s.id} value={s.id}>{s.label}</option>
        ))}
    </select>
    <p className="text-xs text-slate-500 mt-1">Papan membaca undi daripada Borang 14 yang dipilih di sini.</p>
</div>
<div>
    <label className="block text-sm font-medium text-slate-700 mb-1">Undi Minima (pilihan)</label>
    <input type="number" min="0" value={minima} onChange={(e) => setMinima(e.target.value)} className={field} placeholder="Kosongkan jika tiada sasaran" />
</div>
```

And a "Pihak Kami" checkbox per slot, inside the existing candidate row after the name input:

```jsx
<label className="flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" checked={kami.includes(r.slot)} onChange={() => toggleKami(r.slot)} className="rounded border-slate-300" />
    Pihak kami
</label>
```

Render `ralat` above the buttons, and — because a DUN board can be edited by both its own owner and their Parlimen admin — show who saved last so a collision is visible:

```jsx
{board?.dikemaskini?.nama && (
    <p className="text-xs text-slate-500 mt-3">
        Dikemaskini oleh {board.dikemaskini.nama}
        {board.dikemaskini.pada ? ` · ${new Date(board.dikemaskini.pada).toLocaleString('ms-MY')}` : ''}
    </p>
)}
{ralat && <p className="text-sm text-red-600 mt-3">{ralat}</p>}
```

- [ ] **Step 4: Add the publish control**

Add this component and render it above the board when `seat` is set:

```jsx
function PenyiaranCard({ seat, board, onChanged }) {
    const { t } = usePilihanrayaTheme();
    const [busy, setBusy] = useState(false);
    const [ralat, setRalat] = useState(null);
    const tersiar = board?.status === 'tersiar';
    const url = board?.kod ? `${window.location.origin}/scoreboard/${board.kod.toLowerCase()}` : null;

    const togol = () => {
        setBusy(true);
        setRalat(null);
        axios.post(route('pilihanraya.scoreboard.publish'), {
            kawasan_type: seat.type,
            kawasan_id: seat.id,
            status: tersiar ? 'draf' : 'tersiar',
        })
            .then(() => onChanged())
            .catch((e) => setRalat(e.response?.data?.message || 'Gagal menukar status penyiaran.'))
            .finally(() => setBusy(false));
    };

    return (
        <div className={`${t.card} mb-5`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-slate-800">
                        Status: {tersiar ? 'Tersiar' : 'Draf'}
                    </p>
                    <p className="text-xs text-slate-500">
                        {tersiar ? 'Sesiapa yang ada pautan boleh melihat papan ini.' : 'Hanya anda nampak papan ini.'}
                    </p>
                </div>
                <button onClick={togol} disabled={busy} className={tersiar ? t.buttonSecondary : t.buttonPrimary}>
                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                    {tersiar ? 'Tarik Balik Siaran' : 'Siarkan'}
                </button>
            </div>
            {tersiar && url && (
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <code className="text-xs bg-slate-100 px-2 py-1 rounded">{url}</code>
                    <button onClick={() => navigator.clipboard.writeText(url)} className="text-xs text-blue-600 hover:underline">
                        Salin Pautan
                    </button>
                </div>
            )}
            {ralat && <p className="text-sm text-red-600 mt-3">{ralat}</p>}
        </div>
    );
}
```

- [ ] **Step 5: Update the SettingsModal call site**

Wherever `<SettingsModal kadunId={kadunId} penjuru={...} … />` is rendered, change it to:

```jsx
<SettingsModal seat={seat} board={data} onClose={() => setSettingsOpen(false)} onSaved={() => fetchData(true)} />
```

- [ ] **Step 6: Build and verify**

Run: `npm run build`
Expected: builds with no errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Pilihanraya/Scoreboard.jsx public/build
git commit -m "Scoreboard: UI pemilik — pemilih kerusi, sumber undi, pihak kami, penyiaran"
```

---

### Task 7: Public page UI

**Files:**
- Modify: `resources/js/Pages/Public/Scoreboard.jsx`
- Create: `resources/js/Pages/Public/ScoreboardIndex.jsx`

**Interfaces:**
- Consumes: props `kod` and `board` on `Public/Scoreboard`; prop `boards` (`[{kod, title, nama, url}]`) on `Public/ScoreboardIndex`; route `/scoreboard/{kod}/data` for polling.
- Produces: no downstream consumers.

- [ ] **Step 1: Rewrite the public board page's data source**

`Public/Scoreboard.jsx` currently receives `negeriList`/`parlimenList`/`kadunList` and renders a picker. A public board is one seat, so delete the picker entirely and poll by code:

```jsx
export default function PublicScoreboard({ kod, board }) {
    const [data, setData] = useState(board);

    useEffect(() => {
        const tick = () => {
            axios.get(`/scoreboard/${kod}/data`, { params: { _t: Date.now() } })
                .then(({ data: d }) => setData(d))
                .catch(() => { /* biarkan paparan terakhir kekal jika rangkaian tersekat */ });
        };
        const id = setInterval(tick, POLL_MS);
        return () => clearInterval(id);
    }, [kod]);

    return (
        <>
            <Head title={data?.title || 'Scoreboard'} />
            {data?.ready ? <Board data={data} /> : <BelumSedia />}
        </>
    );
}

function BelumSedia() {
    return (
        <div className="min-h-screen flex items-center justify-center p-6">
            <p className="text-slate-500 text-sm">Papan markah belum bersedia. Sila cuba sebentar lagi.</p>
        </div>
    );
}
```

This file has its own copy of the `Board` component, and it reads the renamed payload key too. Find the party line inside its candidate card and replace it with:

```jsx
<div className="text-xs font-bold uppercase tracking-wide" style={{ color }}>{r.parti}{r.is_kami ? ' · KAMI' : ''}</div>
```

- [ ] **Step 2: Create the public index**

Create `resources/js/Pages/Public/ScoreboardIndex.jsx`:

```jsx
import { Head, Link } from '@inertiajs/react';

/**
 * Senarai papan markah TERSIAR sahaja. Papan draf tidak pernah muncul di sini,
 * jadi halaman ini hanya mendedahkan apa yang pemilik pilih untuk siarkan.
 */
export default function ScoreboardIndex({ boards = [] }) {
    return (
        <>
            <Head title="Papan Markah" />
            <div className="min-h-screen bg-slate-50 py-10 px-4">
                <div className="max-w-3xl mx-auto">
                    <h1 className="text-2xl font-bold text-slate-900 mb-1">Papan Markah</h1>
                    <p className="text-sm text-slate-500 mb-6">Papan markah pilihan raya yang disiarkan.</p>

                    {boards.length === 0 ? (
                        <p className="text-sm text-slate-500">Tiada papan markah disiarkan buat masa ini.</p>
                    ) : (
                        <ul className="space-y-2">
                            {boards.map((b) => (
                                <li key={b.kod}>
                                    <Link href={b.url} className="block rounded-xl bg-white border border-slate-200 px-4 py-3 hover:border-slate-300">
                                        <span className="text-xs font-mono text-slate-500">{b.kod}</span>
                                        <span className="block text-sm font-semibold text-slate-900">{b.nama}</span>
                                        <span className="block text-xs text-slate-500">{b.title}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}
```

- [ ] **Step 3: Build and verify**

Run: `npm run build`
Expected: builds with no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Public/Scoreboard.jsx resources/js/Pages/Public/ScoreboardIndex.jsx public/build
git commit -m "Scoreboard: halaman awam ikut kod kerusi + senarai papan tersiar"
```

---

### Task 8: Navigation + full verification

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.jsx` (near `:195-217`)

**Interfaces:**
- Consumes: `route('pilihanraya.scoreboard')`, `user.role`.
- Produces: nothing downstream.

- [ ] **Step 1: Add the Scoreboard-only Pilihanraya block**

`AuthenticatedLayout.jsx:196` gates the whole Pilihanraya menu to super_admin/admin. Add a third block after the `ketua_paca_dun` block (`:222`), listing its item explicitly so a future Pilihanraya entry cannot leak in:

```jsx
// Pilihanraya untuk user/super_user — SATU submenu sahaja (Scoreboard).
// Disenaraikan secara eksplisit dengan sengaja: menambah item Pilihanraya
// baharu di blok admin di atas tidak boleh membocorkannya ke sini.
...(user.role === 'user' || user.role === 'super_user' ? [
    {
        name: 'Pilihanraya',
        icon: Swords,
        children: [
            { name: 'Scoreboard', href: route('pilihanraya.scoreboard'), icon: Trophy },
        ],
    },
] : []),
```

Verify `Swords` and `Trophy` are already imported at the top of the file — they are used by the existing blocks. If either is missing, add it to the `lucide-react` import.

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: builds with no errors.

- [ ] **Step 3: Run the full scoreboard suite**

Run: `php artisan test --filter='SeatScope|Scoreboard'`
Expected: PASS — 32 tests across the five new test files.

- [ ] **Step 4: Run the whole suite and confirm the baseline held**

Run: `php -d memory_limit=1G vendor/bin/phpunit`
Expected: exactly 20 failures/errors, all in `Tests\Feature\Auth\*` and `Tests\Feature\ProfileTest`. If any other test fails, fix it before committing.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts/AuthenticatedLayout.jsx public/build
git commit -m "Scoreboard: buka menu Pilihanraya > Scoreboard kepada user/super_user"
```

---

## Verification Checklist

After Task 8, confirm by hand:

- [ ] A `user` with one DUN opens `/pilihanraya/scoreboard` and the board loads with **no picker**.
- [ ] That user cannot reach another DUN's data by editing `kawasan_id` in the request (403).
- [ ] Choosing a Borang 14 under "Sumber Undi" pins the board; editing a *different* Borang 14 scenario no longer changes what the board shows.
- [ ] Publishing without a `kod_dun` returns the Data Induk message, not a 500.
- [ ] A published board is reachable at `/scoreboard/n27` while logged out; unpublishing makes it 404.
- [ ] The old `/scoreboard/{id}` link 301s to the code URL.
- [ ] A candidate ticked "Pihak kami" shows `· KAMI`; nothing is hardcoded to PH.
