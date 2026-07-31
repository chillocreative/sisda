# Borang 14 Pilihanraya Serentak — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let one PACA at one saluran record both the PRU (Parlimen) and PRN (DUN) ballots on a single Borang 14 form.

**Architecture:** `borang14_votes` gains a `contest` discriminator that goes *inside* the cell unique key. A DUN form links to its Parlimen form via `borang14_form_parlimen_id`; that Parlimen form is the single shared candidate definition. A new `Borang14RollUp` service answers "what is the Parlimen result?" by reading the Parlimen form directly when it has its own structure, else summing its DUNs.

**Tech Stack:** Laravel 12, Inertia + React 18, MySQL (production) / SQLite (CI), PHPUnit.

## Global Constraints

- **All user-facing text and code comments in Bahasa Melayu.** No i18n layer.
- **Unknown is not zero.** Absent data stays `null` and renders `—`. Never `?? 0` on a displayed figure. `null >= 0` is `true` in JS.
- **Migrations run on every production deploy** (`migrate --force`) against live Borang 14 vote data. Never `Schema::drop` + recreate — reshape in place. MySQL error 1553 order: drop FK → drop index → drop/alter column.
- **CI runs SQLite; production runs MySQL.** Raw `ALTER ... MODIFY` needs a driver branch.
- **Authorization lives in controllers, not middleware.**
- **Test baseline is exactly 20 pre-existing failures** (`Tests\Feature\Auth\*`, `Tests\Feature\ProfileTest`). Only worry if that count grows.
- **Full suite needs** `php -d memory_limit=1G vendor/bin/phpunit` — the default 128M exhausts in dompdf's `Cpdf.php`.
- `public/build/` is committed; run `npm run build` after any `.jsx` change.
- Vote slots: `1..6` are party slots, `90` = undi ditolak, `91` = undi tidak dimasukkan.

---

### Task 1: Migration — contest column and widened cell key

**Files:**
- Create: `database/migrations/2026_08_01_100001_add_contest_to_borang14_votes.php`
- Test: `tests/Feature/Borang14SerentakMigrationTest.php`

**Interfaces:**
- Consumes: `borang14_votes` (`borang14_form_id` FK cascade, `pusat`, `saluran`, `slot`, `undi`, UNIQUE `borang14_votes_cell_unique` on `(borang14_form_id, pusat, saluran, slot)`), `borang14_forms` (`kawasan_type`, `kawasan_id`, `jenis_pr`, `tahun`, `structure`, `parties`, `penjuru`).
- Produces: `borang14_votes.contest` (`dun`|`parlimen`, NOT NULL); unique index `borang14_votes_cell_unique` on `(borang14_form_id, contest, pusat, saluran, slot)`; `borang14_forms.borang14_form_parlimen_id` (nullable self-FK, `nullOnDelete`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Borang14SerentakMigrationTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Apabila PRU dan PRN diadakan serentak, SATU saluran menghasilkan DUA set
 * undi. Slot 1 pada saluran yang sama ialah calon BN dalam KEDUA-DUA
 * pertandingan, jadi kunci sel lama (form, pusat, saluran, slot) membuatkan
 * kedua-duanya berlanggar. Ujian ini memaku pembetulan kunci itu.
 */
class Borang14SerentakMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function form(string $kawasanType, int $kawasanId, string $jenisPr): int
    {
        return DB::table('borang14_forms')->insertGetId([
            'kawasan_type' => $kawasanType,
            'kawasan_id' => $kawasanId,
            'jenis_pr' => $jenisPr,
            'tahun' => 2027,
            'penjuru' => 3,
            'parties' => json_encode([]),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_votes_table_has_the_contest_column(): void
    {
        $this->assertTrue(Schema::hasColumn('borang14_votes', 'contest'));
    }

    public function test_forms_table_can_link_to_a_parlimen_form(): void
    {
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'borang14_form_parlimen_id'));
    }

    public function test_both_contests_can_hold_the_same_saluran_and_slot(): void
    {
        $form = $this->form('dun', 34, 'prn');

        // Slot 1 = BN dalam kedua-dua pertandingan pada saluran yang SAMA.
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 224,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'parlimen',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 93,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, DB::table('borang14_votes')->where('borang14_form_id', $form)->count());
        $this->assertSame(224, (int) DB::table('borang14_votes')
            ->where(['borang14_form_id' => $form, 'contest' => 'dun', 'saluran' => '3', 'slot' => 1])->value('undi'));
        $this->assertSame(93, (int) DB::table('borang14_votes')
            ->where(['borang14_form_id' => $form, 'contest' => 'parlimen', 'saluran' => '3', 'slot' => 1])->value('undi'));
    }

    public function test_the_same_cell_within_one_contest_is_still_unique(): void
    {
        $form = $this->form('dun', 34, 'prn');

        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 224,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 999,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_down_refuses_rather_than_lose_data(): void
    {
        $migration = require database_path('migrations/2026_08_01_100001_add_contest_to_borang14_votes.php');

        $form = $this->form('dun', 34, 'prn');
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'parlimen',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 93,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=Borang14SerentakMigrationTest`
Expected: FAIL — `Failed asserting that false is true` (no `contest` column).

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_01_100001_add_contest_to_borang14_votes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihanraya serentak: satu saluran menghasilkan DUA set undi (PRU + PRN).
 *
 * Kunci sel lama ialah (borang14_form_id, pusat, saluran, slot). Slot 1 pada
 * saluran yang sama ialah calon BN dalam KEDUA-DUA pertandingan, jadi kedua-dua
 * baris berlanggar. Lajur `contest` mesti MASUK KE DALAM kunci unik itu, bukan
 * sekadar duduk di sebelahnya.
 *
 * Turutan MySQL (ralat 1553): FK pada borang14_form_id bersandar pada index
 * unik itu, jadi gugur FK -> gugur unique -> tambah unique baharu -> pasang
 * semula FK. BACA 2026_07_16_100001_reshape_borang14_forms.php dahulu — ia
 * mendokumenkan perangkap 1553 dan perangkap rebuild SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Berkunci pada artifak TERAKHIR yang dicipta, bukan yang pertama:
        // larian separa mesti MENYAMBUNG, bukan dilangkau dan direkod berjaya.
        if (Schema::hasColumn('borang14_forms', 'borang14_form_parlimen_id')) {
            return;
        }

        if (! Schema::hasColumn('borang14_votes', 'contest')) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->string('contest', 10)->nullable()->after('borang14_form_id');
            });
        }

        // Isian belakang: pertandingan sesuatu baris ialah kawasan borang itu
        // sendiri. Borang DUN sedia ada -> 'dun'; borang Parlimen -> 'parlimen'.
        // Tiada baris sedia ada bermakna apa-apa yang lain.
        DB::table('borang14_votes')->whereNull('contest')->update([
            'contest' => DB::raw('(SELECT f.kawasan_type FROM borang14_forms f WHERE f.id = borang14_votes.borang14_form_id)'),
        ]);
        // Baris yatim (borang sudah tiada) — tiada nilai boleh diterbitkan.
        DB::table('borang14_votes')->whereNull('contest')->delete();

        if ($this->uniqueWujud('borang14_votes_cell_unique')) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->dropForeign(['borang14_form_id']);
            });
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->dropUnique('borang14_votes_cell_unique');
            });
        }

        Schema::table('borang14_votes', function (Blueprint $table) {
            $table->string('contest', 10)->nullable(false)->change();
        });

        Schema::table('borang14_votes', function (Blueprint $table) {
            $table->unique(
                ['borang14_form_id', 'contest', 'pusat', 'saluran', 'slot'],
                'borang14_votes_cell_unique',
            );
            $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->cascadeOnDelete();
        });

        // Dicipta TERAKHIR — ia ialah pengawal larian-ulang di atas.
        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->foreignId('borang14_form_parlimen_id')->nullable()->after('tahun')
                ->constrained('borang14_forms')->nullOnDelete();
        });
    }

    private function uniqueWujud(string $nama): bool
    {
        foreach (Schema::getIndexes('borang14_votes') as $index) {
            if (($index['name'] ?? null) === $nama) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        if (! Schema::hasColumn('borang14_votes', 'contest')) {
            return;
        }

        $serentak = DB::table('borang14_votes')->where('contest', 'parlimen')
            ->whereIn('borang14_form_id', DB::table('borang14_forms')->where('kawasan_type', 'dun')->pluck('id'))
            ->count();

        throw new \RuntimeException(
            "Migrasi ini TIDAK BOLEH diterbalikkan (not reversible): terdapat {$serentak} baris undi ".
            "pertandingan Parlimen yang direkod pada borang DUN. Skema lama (kunci sel tanpa `contest`) ".
            'TIADA tempat untuk baris tersebut — menukar balik akan memusnahkannya, atau melanggar kunci '.
            'unik lama kerana slot yang sama wujud dua kali bagi saluran yang sama. SANDARKAN data dahulu '.
            'dan tangani migrasi data secara manual jika rollback benar-benar diperlukan.'
        );
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=Borang14SerentakMigrationTest`
Expected: PASS, 5 tests.

- [ ] **Step 5: Confirm existing Borang 14 tests still pass**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=Borang14`
Expected: all green. If `Borang14MigrationGuardTest` fails, it is asserting the pre-migration schema shape — read it and follow the same accommodation pattern it already uses for the 07_16 migration; do NOT weaken any assertion.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_01_100001_add_contest_to_borang14_votes.php tests/Feature/Borang14SerentakMigrationTest.php
git commit -m "Borang 14: lajur contest masuk ke dalam kunci unik sel"
```

---

### Task 2: Models and every vote write path

**Files:**
- Modify: `app/Models/Borang14Vote.php`
- Modify: `app/Models/Borang14Form.php`
- Modify: `app/Http/Controllers/Borang14Controller.php` (`saveVote()` ~:185, `putVote()` ~:1397, `revert()` ~:1457, snapshot captures ~:386, ~:1257, ~:1683)
- Test: `tests/Feature/Borang14SerentakWriteTest.php`

**Interfaces:**
- Consumes: Task 1's `contest` column and `borang14_form_parlimen_id`.
- Produces:
  - `Borang14Vote::CONTEST_DUN` = `'dun'`, `Borang14Vote::CONTEST_PARLIMEN` = `'parlimen'`; `contest` added to `$fillable`
  - `Borang14Form::formParlimen(): BelongsTo`
  - `Borang14Form::votesFor(string $contest): HasMany`
  - `Borang14Form::contestSendiri(): string` — returns the form's own `kawasan_type`
  - `saveVote` accepts and requires a `contest` request field

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Borang14SerentakWriteTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autosimpan MESTI berkunci pada pertandingan. Tanpa `contest` dalam kunci,
 * satu ketukan kekunci PRU menulis ganti sel PRN pada kedudukan yang sama —
 * kelas pepijat tulis-ganti senyap yang sama seperti key-drift.
 */
class Borang14SerentakWriteTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;
    private Bandar $parlimen;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);
    }

    /** Built by hand, not by factory: UserFactory omits the NOT NULL telephone column. */
    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'serentak@example.test'], [
            'name' => 'Penyelia', 'telephone' => '0123456789', 'password' => bcrypt('rahsia'),
            'role' => 'super_admin', 'status' => 'approved',
        ]);
    }

    private function hantarUndi(string $contest, int $slot, int $undi): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2027,
            'penjuru' => 3,
            'contest' => $contest,
            'pusat' => 'SK GEMAS',
            'saluran' => '3',
            'slot' => $slot,
            'undi' => $undi,
        ]);
    }

    public function test_a_pru_keystroke_does_not_overwrite_the_prn_cell(): void
    {
        $this->hantarUndi('dun', 1, 224)->assertOk();
        $this->hantarUndi('parlimen', 1, 93)->assertOk();

        $form = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();

        $this->assertSame(224, (int) $form->votesFor(Borang14Vote::CONTEST_DUN)
            ->where('saluran', '3')->where('slot', 1)->value('undi'));
        $this->assertSame(93, (int) $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)
            ->where('saluran', '3')->where('slot', 1)->value('undi'));
    }

    public function test_rejected_and_undeposited_ballots_are_counted_per_contest(): void
    {
        // Slot 90 = undi ditolak, 91 = tidak dimasukkan. Kertas undi boleh rosak
        // dalam SATU pertandingan sahaja.
        $this->hantarUndi('dun', 90, 5)->assertOk();
        $this->hantarUndi('parlimen', 90, 8)->assertOk();

        $form = Borang14Form::where('kawasan_id', $this->dun->id)->firstOrFail();

        $this->assertSame(5, (int) $form->votesFor(Borang14Vote::CONTEST_DUN)->where('slot', 90)->value('undi'));
        $this->assertSame(8, (int) $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->where('slot', 90)->value('undi'));
    }

    public function test_contest_is_required(): void
    {
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 3,
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 10,
        ])->assertStatus(422);
    }

    public function test_a_form_reports_its_own_contest(): void
    {
        $dunForm = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
        ]);
        $parlimenForm = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'parties' => [],
        ]);

        $this->assertSame('dun', $dunForm->contestSendiri());
        $this->assertSame('parlimen', $parlimenForm->contestSendiri());
    }

    public function test_a_dun_form_can_link_to_its_parlimen_definition(): void
    {
        $parlimenForm = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PN'], ['slot' => 3, 'nama' => 'PH']],
        ]);
        $dunForm = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $parlimenForm->id,
        ]);

        $this->assertSame($parlimenForm->id, $dunForm->formParlimen->id);
        $this->assertCount(3, $dunForm->formParlimen->parties);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=Borang14SerentakWriteTest`
Expected: FAIL — `Call to undefined method votesFor()`.

- [ ] **Step 3a: Update `Borang14Vote`**

In `app/Models/Borang14Vote.php`, add the constants and make `contest` fillable:

```php
class Borang14Vote extends Model
{
    /** Pertandingan mana baris ini milik — lihat migrasi 2026_08_01_100001. */
    public const CONTEST_DUN = 'dun';

    public const CONTEST_PARLIMEN = 'parlimen';

    protected $fillable = ['borang14_form_id', 'contest', 'pusat', 'saluran', 'slot', 'undi'];

    protected $casts = [
        'slot' => 'integer',
        'undi' => 'integer',
    ];
    // ... hubungan form() sedia ada kekal
}
```

- [ ] **Step 3b: Update `Borang14Form`**

In `app/Models/Borang14Form.php`, add `borang14_form_parlimen_id` to `$fillable`, then add:

```php
    /**
     * Borang Parlimen yang menakrifkan pertandingan PRU bagi borang DUN ini.
     * Null bermakna borang satu pertandingan sahaja (kes biasa).
     */
    public function formParlimen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'borang14_form_parlimen_id');
    }

    /** Borang DUN yang merekod pertandingan Parlimen ini. */
    public function borangDun(): HasMany
    {
        return $this->hasMany(self::class, 'borang14_form_parlimen_id');
    }

    /**
     * Undi bagi SATU pertandingan sahaja.
     *
     * Gunakan ini dan BUKAN votes() di mana-mana yang mengira angka: pada borang
     * serentak, votes() memulangkan undi PRU DAN PRN bercampur, lalu menjumlahkan
     * kira-kira dua kali ganda.
     */
    public function votesFor(string $contest): HasMany
    {
        return $this->votes()->where('contest', $contest);
    }

    /** Pertandingan borang ini sendiri — sama dengan kawasannya. */
    public function contestSendiri(): string
    {
        return $this->kawasan_type === self::KAWASAN_PARLIMEN
            ? Borang14Vote::CONTEST_PARLIMEN
            : Borang14Vote::CONTEST_DUN;
    }
```

Add `use Illuminate\Database\Eloquent\Relations\BelongsTo;` if not already imported.

- [ ] **Step 3c: Thread `contest` through every write path**

In `app/Http/Controllers/Borang14Controller.php`:

**`saveVote()`** — add to the validation rules:

```php
            'contest'  => ['required', Rule::in([Borang14Vote::CONTEST_DUN, Borang14Vote::CONTEST_PARLIMEN])],
```

and add `contest` to the `updateOrCreate` **key** (not the values):

```php
        Borang14Vote::updateOrCreate(
            [
                'borang14_form_id' => $form->id,
                'contest' => $validated['contest'],
                'pusat'   => $validated['pusat'] ?? '',
                'saluran' => $validated['saluran'],
                'slot'    => $validated['slot'],
            ],
            ['undi' => $validated['undi'] ?? 0],
        );
```

**`putVote()`** — take the contest as a parameter and put it in the key:

```php
    private function putVote(Borang14Form $form, array $row, int $slot, int $undi, ?string $contest = null): void
    {
        $key = [
            'borang14_form_id' => $form->id,
            'contest' => $contest ?? $form->contestSendiri(),
            'pusat' => (string) ($row['pusat'] ?? ''),
            'saluran' => $this->normalizeSaluran($row['saluran'] ?? null),
            'slot' => $slot,
        ];

        $existingUndi = (int) (Borang14Vote::where($key)->value('undi') ?? 0);

        Borang14Vote::updateOrCreate($key, ['undi' => $existingUndi + $undi]);
    }
```

Existing callers pass no `$contest` and therefore keep writing the form's own contest — unchanged behaviour for single-contest uploads.

**`revert()`** — carry `contest` through the snapshot restore:

```php
        $form->votes()->delete();
        foreach ($snap->votes as $v) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id,
                // Snapshot lama tiada `contest` — anggap ia pertandingan borang
                // itu sendiri, iaitu satu-satunya yang wujud ketika itu.
                'contest' => $v['contest'] ?? $form->contestSendiri(),
                'pusat' => $v['pusat'], 'saluran' => $v['saluran'],
                'slot' => $v['slot'], 'undi' => $v['undi'],
            ]);
        }
```

**All three snapshot captures** (~:386, ~:1257, ~:1683) — include `contest` in the columns pulled, otherwise reverting a concurrent form collapses both contests into one:

```php
                    'votes' => $form->votes()->get(['contest', 'pusat', 'saluran', 'slot', 'undi'])->toArray(),
```

Add `use App\Models\Borang14Vote;` to the controller if it is not already imported.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=Borang14SerentakWriteTest`
Expected: PASS, 5 tests.

- [ ] **Step 5: Confirm no Borang 14 regression**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=Borang14`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Borang14Vote.php app/Models/Borang14Form.php app/Http/Controllers/Borang14Controller.php tests/Feature/Borang14SerentakWriteTest.php
git commit -m "Borang 14: alirkan contest melalui setiap laluan tulis undi"
```

---

### Task 3: Audit every vote reader

**Files:**
- Modify: `app/Services/Pilihanraya/ScoreboardPayload.php`
- Modify: `app/Services/Pilihanraya/Borang14ScenarioMapper.php`
- Modify: `app/Http/Controllers/Borang14Controller.php` (read paths)
- Test: `tests/Feature/Borang14SerentakReaderTest.php`

**Interfaces:**
- Consumes: `Borang14Form::votesFor()`, `contestSendiri()`, `Borang14Vote::CONTEST_*`.
- Produces: no new public API — every existing reader now returns single-contest figures.

**THIS IS THE HIGHEST-RISK TASK.** These readers query `borang14_votes` with no contest filter. On a concurrent form they will sum PRU and PRN together and report roughly double. This is already-shipped code.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Borang14SerentakReaderTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pembaca undi sedia ada menyoal borang14_votes TANPA penapis pertandingan.
 * Pada borang serentak itu menjumlahkan PRU dan PRN bersama — kira-kira dua
 * kali ganda. Ujian ini memaku setiap pembaca kepada SATU pertandingan.
 */
class Borang14SerentakReaderTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;
    private Borang14Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $bandar->id]);

        // Roll DPT supaya Borang14Reference memulangkan struktur.
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '990101010101', 'nama' => 'PENGUNDI', 'kadun' => 'GEMAS',
            'daerah_mengundi' => 'PEKAN GEMAS', 'lokaliti' => 'SK GEMAS',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'status' => 'published',
            'parties' => [['slot' => 1, 'nama' => 'PN'], ['slot' => 2, 'nama' => 'BN']],
        ]);

        // PRN: 63 + 224 = 287.  PRU: 93 + 27 = 120.  Bercampur salah: 407.
        foreach ([[Borang14Vote::CONTEST_DUN, 1, 63], [Borang14Vote::CONTEST_DUN, 2, 224],
                  [Borang14Vote::CONTEST_PARLIMEN, 1, 93], [Borang14Vote::CONTEST_PARLIMEN, 2, 27]] as [$c, $slot, $undi]) {
            Borang14Vote::create([
                'borang14_form_id' => $this->form->id, 'contest' => $c,
                'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => $slot, 'undi' => $undi,
            ]);
        }
    }

    public function test_the_dun_scoreboard_counts_only_the_dun_contest(): void
    {
        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $this->form->id,
            'title' => 'GEMAS', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $payload = ScoreboardPayload::forSeat('dun', $this->dun->id);

        $this->assertSame(287, $payload['total_keluar'], 'Undi PRU tidak boleh dicampur ke dalam papan DUN.');
        $this->assertNotSame(407, $payload['total_keluar']);
        $this->assertSame([63, 224], array_column($payload['rows'], 'undi'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=Borang14SerentakReaderTest`
Expected: FAIL — `287` expected, `407` actual (both contests summed).

- [ ] **Step 3: Fix each reader**

**`ScoreboardPayload::forSeat()`** — the tally query currently reads `$form->votes()`. Scope it to the seat's own contest:

```php
        $kontes = $type === SeatScope::PARLIMEN
            ? Borang14Vote::CONTEST_PARLIMEN
            : Borang14Vote::CONTEST_DUN;

        $sums = $form->votesFor($kontes)->where('slot', '>=', 1)
            ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')->pluck('total', 'slot');
```

Add `use App\Models\Borang14Vote;`.

**`Borang14ScenarioMapper`** — wherever it loads a form's votes, pass the contest it is mapping. Read the file, find every `->votes()` or vote-collection intake, and scope it with `votesFor($form->contestSendiri())` unless the caller explicitly supplies a contest. Its slot 90/91 handling needs no change beyond that scoping.

**`Borang14Controller` read paths** — grep the file for `->votes()` and scope each one. Every existing call site is a single-contest screen, so `votesFor($form->contestSendiri())` preserves current behaviour exactly; the concurrent screen in Task 6 passes its contest explicitly.

**`Borang14Reference`** — grep it for vote reads. If it reads only `structure` and the DPT roll (no votes), leave it alone and note that in your report.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=Borang14SerentakReaderTest`
Expected: PASS.

- [ ] **Step 5: Prove no reader was missed**

Run: `grep -rn '\->votes()' app/ | grep -v 'votesFor'`
Expected: every remaining hit is either a write path, a delete, or a snapshot capture that deliberately wants both contests. Justify each survivor in your report; there must be no unscoped *counting* read.

Then run: `php -d memory_limit=1G vendor/bin/phpunit --filter='Borang14|Scoreboard|Analisa'`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Pilihanraya/ScoreboardPayload.php app/Services/Pilihanraya/Borang14ScenarioMapper.php app/Http/Controllers/Borang14Controller.php tests/Feature/Borang14SerentakReaderTest.php
git commit -m "Borang 14: skop setiap pembaca undi kepada satu pertandingan"
```

---

### Task 4: `Borang14RollUp` service

**Files:**
- Create: `app/Services/Pilihanraya/Borang14RollUp.php`
- Test: `tests/Feature/Borang14RollUpTest.php`

**Interfaces:**
- Consumes: `Borang14Form` (`kawasan_type`, `kawasan_id`, `jenis_pr`, `tahun`, `structure`, `parties`, `penjuru`, `borangDun()`, `votesFor()`), `Borang14Vote::CONTEST_PARLIMEN`.
- Produces: `Borang14RollUp::forParlimen(int $bandarId, ?int $tahun = null): ?array` with keys `form_id`, `parties`, `penjuru`, `undi` (`[slot => int]`), `sumber` (`'borang'`|`'kumpulan'`), `liputan` (`['melapor' => int, 'jumlah' => int]` or `null` when read straight from a form).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Borang14RollUpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Services\Pilihanraya\Borang14RollUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_no_parlimen_form_at_all_returns_null_not_zero(): void
    {
        $this->assertNull(Borang14RollUp::forParlimen($this->parlimen->id, 2027));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=Borang14RollUpTest`
Expected: FAIL — `Class "App\Services\Pilihanraya\Borang14RollUp" not found`.

- [ ] **Step 3: Write the service**

Create `app/Services/Pilihanraya/Borang14RollUp.php`:

```php
<?php

namespace App\Services\Pilihanraya;

use App\Models\Borang14Form;
use App\Models\Borang14Vote;

/**
 * Menjawab satu soalan: apakah keputusan pertandingan PARLIMEN?
 *
 * Dua senario, dibezakan oleh `structure` borang Parlimen:
 *  1. Ada struktur sendiri  -> PRU sahaja; baca terus daripada borang itu.
 *  2. Tiada struktur        -> pilihanraya serentak; borang itu hanyalah
 *     TAKRIFAN calon, dan undi sebenar berada pada borang DUN yang memautinya.
 *
 * Kerana senarai calon ditakrifkan SEKALI pada borang Parlimen, slot 1
 * bermakna orang yang SAMA di setiap DUN — jumlah sentiasa serupa-dengan-serupa.
 */
class Borang14RollUp
{
    /**
     * @return array{form_id:int, parties:array, penjuru:int, undi:array<int,int>, sumber:string, liputan:?array{melapor:int,jumlah:int}}|null
     */
    public static function forParlimen(int $bandarId, ?int $tahun = null): ?array
    {
        $form = Borang14Form::where('kawasan_type', Borang14Form::KAWASAN_PARLIMEN)
            ->where('kawasan_id', $bandarId)
            ->when($tahun, fn ($q) => $q->where('tahun', $tahun))
            ->latest('tahun')->first();

        // Tiada borang Parlimen langsung — TIDAK DIKETAHUI, bukan sifar undi.
        if (! $form) {
            return null;
        }

        $asas = [
            'form_id' => $form->id,
            'parties' => $form->parties ?? [],
            'penjuru' => (int) $form->penjuru,
        ];

        if (! empty($form->structure)) {
            return $asas + [
                'undi' => self::jumlahSlot($form->votesFor(Borang14Vote::CONTEST_PARLIMEN)),
                'sumber' => 'borang',
                'liputan' => null,
            ];
        }

        $borangDun = $form->borangDun()->get();
        $undi = [];
        $melapor = 0;

        foreach ($borangDun as $dun) {
            $slotDun = self::jumlahSlot($dun->votesFor(Borang14Vote::CONTEST_PARLIMEN));
            if ($slotDun !== []) {
                $melapor++;
            }
            foreach ($slotDun as $slot => $nilai) {
                $undi[$slot] = ($undi[$slot] ?? 0) + $nilai;
            }
        }

        ksort($undi);

        return $asas + [
            'undi' => $undi,
            'sumber' => 'kumpulan',
            // Kumpulan SEPARA pada malam keputusan tidak boleh kelihatan
            // seperti keputusan muktamad — liputan sentiasa dilaporkan.
            'liputan' => ['melapor' => $melapor, 'jumlah' => $borangDun->count()],
        ];
    }

    /** @return array<int,int> */
    private static function jumlahSlot($query): array
    {
        return $query->where('slot', '>=', 1)
            ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')
            ->pluck('total', 'slot')
            ->map(fn ($v) => (int) $v)->all();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=Borang14RollUpTest`
Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Pilihanraya/Borang14RollUp.php tests/Feature/Borang14RollUpTest.php
git commit -m "Borang 14: perkhidmatan kumpulan keputusan Parlimen"
```

---

### Task 5: Parlimen scoreboard uses the roll-up

**Files:**
- Modify: `app/Services/Pilihanraya/ScoreboardPayload.php`
- Test: `tests/Feature/ScoreboardParlimenRollUpTest.php`

**Interfaces:**
- Consumes: `Borang14RollUp::forParlimen()`.
- Produces: `ScoreboardPayload::forSeat('parlimen', $id)` gains `liputan` in its payload (null when read straight from a form).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ScoreboardParlimenRollUpTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScoreboardParlimenRollUpTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $parlimen;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);

        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '990101010101', 'nama' => 'PENGUNDI', 'kadun' => 'GEMAS',
            'parlimen' => 'JEMPOL', 'daerah_mengundi' => 'PEKAN GEMAS', 'lokaliti' => 'SK GEMAS',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $definisi = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'structure' => null,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PN'], ['slot' => 3, 'nama' => 'PH']],
        ]);
        $dunForm = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $definisi->id,
        ]);
        foreach ([1 => 2282, 2 => 1195, 3 => 412] as $slot => $undi) {
            Borang14Vote::create([
                'borang14_form_id' => $dunForm->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
                'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        Scoreboard::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'borang14_form_id' => $definisi->id,
            'title' => 'P133', 'status' => Scoreboard::STATUS_DRAF,
        ]);
    }

    public function test_a_parlimen_board_aggregates_its_dun_forms(): void
    {
        $payload = ScoreboardPayload::forSeat('parlimen', $this->parlimen->id);

        $this->assertTrue($payload['ready']);
        $this->assertSame([2282, 1195, 412], array_column($payload['rows'], 'undi'));
        $this->assertSame(3889, $payload['total_keluar']);
        $this->assertSame(['melapor' => 1, 'jumlah' => 1], $payload['liputan']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ScoreboardParlimenRollUpTest`
Expected: FAIL — the definition form has no votes of its own, so totals come back 0.

- [ ] **Step 3: Route the Parlimen path through the roll-up**

In `ScoreboardPayload::forSeat()`, when `$type === SeatScope::PARLIMEN`, take the tally and party list from `Borang14RollUp::forParlimen($id, $form->tahun)` instead of from `$form->votesFor(...)`. Use the roll-up's `parties` and `penjuru`, and put its `liputan` on the payload. The DUN path is untouched.

Add `'liputan' => null` to the DUN branch and to the not-ready branches so the key always exists.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ScoreboardParlimenRollUpTest`
Expected: PASS.

Then: `php -d memory_limit=1G vendor/bin/phpunit --filter='Scoreboard|SeatScope|Borang14'`
Expected: all green.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Pilihanraya/ScoreboardPayload.php tests/Feature/ScoreboardParlimenRollUpTest.php
git commit -m "Scoreboard: papan Parlimen guna kumpulan borang DUN"
```

---

### Task 6: Concurrent entry screen

**Files:**
- Modify: `resources/js/Pages/Pilihanraya/components/Borang14Form.jsx`
- Modify: `resources/js/Pages/Pilihanraya/Borang14.jsx` (pass the linked Parlimen contest down)

**Interfaces:**
- Consumes: the form payload; each saluran row renders one cell group per contest. Autosave posts `contest` alongside `pusat`/`saluran`/`slot`.
- Produces: no downstream consumers.

- [ ] **Step 1: Read the current form component in full**

Read `resources/js/Pages/Pilihanraya/components/Borang14Form.jsx` (353 lines) end to end before editing. Identify the saluran row renderer and the autosave call.

- [ ] **Step 2: Add `contest` to every autosave call**

The autosave currently posts `{kawasan_type, kawasan_id, jenis_pr, tahun, penjuru, pusat, saluran, slot, undi}`. Add `contest`. **Every** call site must pass it — a missing `contest` now 422s (Task 2), which is deliberate: failing loudly beats silently overwriting the other contest's cell.

- [ ] **Step 3: Render two banded groups when a Parlimen contest is linked**

When the form has no linked Parlimen contest, render exactly as today — one group, no visual change.

When linked, render two groups per saluran row, each with its own header naming the contest and its seat, in Bahasa Melayu:

```jsx
{/* Jalur pertandingan. Tajuk menamakan pertandingan DAN kerusinya supaya PACA
    pada pukul 11 malam tidak tersilap kertas undi. */}
<th colSpan={penjuruDun + 2} className="text-center bg-red-50 border-x border-slate-300">
    PRN · DUN {namaDun}
</th>
{kontesParlimen && (
    <th colSpan={kontesParlimen.penjuru + 2} className="text-center bg-blue-50 border-x border-slate-300">
        PRU · Parlimen {kontesParlimen.nama}
    </th>
)}
```

Each group holds its contest's party slots plus its own **Tolak** (slot 90) and **T.Msk** (slot 91) cells. The number of party cells comes from that contest's own `penjuru` — the DUN's for the PRN group, the linked Parlimen form's for the PRU group. Reading the wrong one silently drops or rejects cells.

**`Berdaftar` stays outside both groups** — it is shared, and its position carries that meaning.

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Pilihanraya/components/Borang14Form.jsx resources/js/Pages/Pilihanraya/Borang14.jsx public/build
git commit -m "Borang 14: skrin kemasukan dua pertandingan"
```

---

### Task 7: Concurrent-mode toggle in form setup

**Files:**
- Modify: `app/Http/Controllers/Borang14Controller.php` (the form setup/create endpoint around `:155-180`)
- Modify: `resources/js/Pages/Pilihanraya/Borang14.jsx`
- Test: `tests/Feature/Borang14SerentakSetupTest.php`

**Interfaces:**
- Consumes: `Borang14Form`, `Borang14Form::formParlimen()`.
- Produces: the setup endpoint accepts an optional `parlimen_id`; when present it finds-or-creates the `parlimen/{id}/pru/{tahun}` definition form and sets `borang14_form_parlimen_id` on the DUN form.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Borang14SerentakSetupTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14SerentakSetupTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;
    private Bandar $parlimen;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'setup@example.test'], [
            'name' => 'Penyelia', 'telephone' => '0123450000', 'password' => bcrypt('rahsia'),
            'role' => 'super_admin', 'status' => 'approved',
        ]);
    }

    public function test_enabling_concurrent_mode_creates_and_links_the_parlimen_definition(): void
    {
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2027,
            'penjuru' => 2,
            'parlimen_id' => $this->parlimen->id,
        ])->assertSuccessful();

        $dunForm = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();
        $this->assertNotNull($dunForm->borang14_form_parlimen_id);

        $definisi = $dunForm->formParlimen;
        $this->assertSame('parlimen', $definisi->kawasan_type);
        $this->assertSame($this->parlimen->id, $definisi->kawasan_id);
        $this->assertSame(2027, (int) $definisi->tahun);
        $this->assertEmpty($definisi->structure, 'Borang takrifan tiada struktur sendiri.');
    }

    public function test_a_second_dun_reuses_the_same_definition(): void
    {
        $dunB = Kadun::create(['nama' => 'SERTING', 'kod_dun' => 'N33', 'bandar_id' => $this->parlimen->id]);

        foreach ([$this->dun, $dunB] as $dun) {
            $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
                'kawasan_type' => 'dun', 'kawasan_id' => $dun->id,
                'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
                'parlimen_id' => $this->parlimen->id,
            ])->assertSuccessful();
        }

        // SATU takrifan sahaja — jika tidak, slot 1 boleh bermakna calon
        // berbeza di setiap DUN dan kumpulan akan menjumlahkan lajur yang salah.
        $this->assertSame(1, Borang14Form::where('kawasan_type', 'parlimen')
            ->where('kawasan_id', $this->parlimen->id)->where('tahun', 2027)->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=Borang14SerentakSetupTest`
Expected: FAIL — `borang14_form_parlimen_id` is null.

- [ ] **Step 3: Accept and wire `parlimen_id`**

In the setup endpoint, add validation:

```php
            'parlimen_id' => ['nullable', 'integer', Rule::exists('bandar', 'id')],
```

and after the DUN form is resolved:

```php
        // Mod serentak: borang Parlimen itu SENDIRI ialah takrifan calon yang
        // dikongsi semua DUN dalam Parlimen itu. Kunci uniknya sudah menjamin
        // tepat satu baris, jadi firstOrCreate memberi satu takrifan sahaja.
        if (! empty($validated['parlimen_id'])) {
            $definisi = Borang14Form::firstOrCreate(
                [
                    'kawasan_type' => Borang14Form::KAWASAN_PARLIMEN,
                    'kawasan_id' => $validated['parlimen_id'],
                    'jenis_pr' => 'pru',
                    'tahun' => $validated['tahun'],
                ],
                ['penjuru' => 2, 'parties' => []],
            );
            $form->borang14_form_parlimen_id = $definisi->id;
            $form->save();
        }
```

- [ ] **Step 4: Add the toggle to the setup UI**

In `Borang14.jsx`, add a checkbox **"Pilihanraya serentak (PRU + PRN)"**, default unchecked. When checked, show a Parlimen select limited to the DUN's own parent Parlimen and post its id as `parlimen_id`. Helper text in Bahasa Melayu: *"Satu saluran merekod dua kertas undi — PRN untuk DUN ini dan PRU untuk Parlimennya."*

- [ ] **Step 5: Run tests and build**

Run: `php artisan test --filter=Borang14SerentakSetupTest` → PASS
Run: `npm run build` → no errors

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Borang14Controller.php resources/js/Pages/Pilihanraya/Borang14.jsx tests/Feature/Borang14SerentakSetupTest.php public/build
git commit -m "Borang 14: togol mod pilihanraya serentak"
```

---

### Task 8: Full verification

**Files:** none modified unless a regression is found.

- [ ] **Step 1: Full suite**

Run: `php -d memory_limit=1G vendor/bin/phpunit`
Expected: exactly 20 failures, all in `Tests\Feature\Auth\*` and `Tests\Feature\ProfileTest`. Report the exact totals line. Any other failure is a regression — investigate and report it rather than papering over it.

- [ ] **Step 2: Prove no unscoped counting read survives**

Run: `grep -rn '\->votes()' app/ | grep -v votesFor`
Expected: only write paths, deletes, and snapshot captures. List each in your report with its justification.

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 4: Commit any fixes**

```bash
git add -A
git commit -m "Borang 14 serentak: pengesahan penuh"
```

---

## Verification Checklist

After Task 8, confirm by hand:

- [ ] An existing single-contest Borang 14 opens and behaves exactly as before — one band, no visual change.
- [ ] Enabling concurrent mode on DUN Gemas creates one `parlimen/133/pru/2027` definition; enabling it on a second DUN reuses that same row.
- [ ] Keying a PRU cell does not change the PRN cell in the same saluran/slot position, and vice versa.
- [ ] The PRU band shows the Parlimen contest's number of candidates, not the DUN's.
- [ ] The DUN scoreboard total counts only PRN votes.
- [ ] The Parlimen scoreboard sums its DUNs and shows coverage ("1 daripada 3 DUN melapor") rather than presenting a partial total as final.
- [ ] Undi ditolak keyed against PRU only does not appear in the PRN totals.
