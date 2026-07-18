# Submenu Borang 14 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pecahkan Borang 14 kepada tiga skrin — Upload Scoresheet (AI populate), Papar (sejarah), Keyin (manual) — dengan sokongan Parlimen + DUN, tahun, dan jenis pilihanraya.

**Architecture:** Kunci `borang14_forms` dibentuk semula kepada `UNIQUE(kawasan_type, kawasan_id, jenis_pr, tahun)` dengan `penjuru` jadi atribut. Scoresheet SPR 760 dibaca oleh `ScoresheetExtractor::extractDetailed()` (kaedah baharu; prompt Analisa sedia ada tidak diusik) lalu mencipta draf yang user semak di tab Keyin sebelum publish. Tiga skrin ialah tab dalam satu page induk — nav global tidak disentuh.

**Tech Stack:** Laravel 11, Inertia 2, React 18, Tailwind 3, PHPUnit, DomPDF, Claude API (`ClaudeService`).

**Spec:** `docs/superpowers/specs/2026-07-16-borang14-submenu-design.md` (451 baris, diluluskan)

## Global Constraints

- Branch `feature/borang14-submenu` sudah checked out — jangan rancang langkah branch.
- `.gitignore` mengandungi `*.md` — **jangan** commit fail .md (spec/plan kekal lokal).
- Semua teks user-facing dalam **Bahasa Melayu**.
- **JANGAN** ubah `ScoresheetExtractor::SYSTEM` atau `extract()` — Analisa bergantung padanya. Tambah `extractDetailed()` + prompt constant baharu.
- **JANGAN** tambah nav depth-2 ke `AuthenticatedLayout.jsx` — spec pilih tab.
- `borang14_votes` schema **tidak berubah**. Validation sahaja melebar: slot `in:1,2,3,4,5,6,90,91` (90 = ditolak C, 91 = tidak dimasukkan D).
- `cellKey` = `"{pusat}|{saluran}|{slot}"`, `pusat=''` untuk baris peringkat DUN. Mesti padan JS ↔ PHP.
- Guna token `usePilihanrayaTheme()` (`t.card`, `t.tableHead`, `t.input`, ...) — jangan cipta gaya baharu.
- Fail upload **tidak** disimpan ke disk. Validation: `mimes:xlsx,xls,csv,txt,pdf,jpg,jpeg,png,webp|max:20480`.
- `berdaftar` **tiada** dalam scoresheet — `% Turnout` / `Tak Keluar` papar `—`, **bukan** 0.
- `BULOH_KASAP_KADUN_ID = 41` gabung Undi Awal+Pos — kekalkan pengecualian.
- Arah UI: **Dense Functional**.
- Ujian: `php artisan test --filter=Borang14`.

## Angka penerimaan — scoresheet Juasseh sebenar

```
jumlah_pemilih 13,408 | 40 baris saluran (39 Undi Biasa + 1 Undi Pos) | 11 DM
(A) 9,122 | undi calon 4,471 / 4,549 | jumlah undian 9,020
(C) ditolak 87 | (D) tidak dimasukkan 15
silang-semak: 4,471 + 4,549 + 87 + 15 == 9,122
Undi Pos: A=203, 98/73, jumlah=171, C=18, D=14
```

---

### Task 1: Bentuk semula `borang14_forms` + selaraskan `scoreboards`

Kedua-dua jadual **kosong (0 baris, disahkan 2026-07-16)** — jadi drop+recreate, tiada backfill.

**Files:**
- Create: `database/migrations/2026_07_16_100001_reshape_borang14_forms.php`
- Modify: `app/Http/Controllers/ScoreboardController.php`
- Test: `tests/Feature/Borang14SchemaTest.php`

**Interfaces:**
- Produces: jadual `borang14_forms` dengan `UNIQUE(kawasan_type, kawasan_id, jenis_pr, tahun)`; `scoreboards.borang14_form_id` FK UNIQUE.
- Consumes: jadual `kadun`, `bandar`, `users` sedia ada.

- [ ] **Step 1: Tulis ujian yang gagal**

```php
<?php
// tests/Feature/Borang14SchemaTest.php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class Borang14SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_borang14_forms_unique_key_is_kawasan_jenis_tahun(): void
    {
        $row = [
            'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn',
            'tahun' => 2022, 'penjuru' => 3, 'status' => 'draft',
            'source' => 'manual', 'needs_review' => false,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('borang14_forms')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('borang14_forms')->insert($row);
    }

    public function test_penjuru_is_not_part_of_the_key(): void
    {
        $base = [
            'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn',
            'tahun' => 2022, 'status' => 'draft', 'source' => 'manual',
            'needs_review' => false, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('borang14_forms')->insert($base + ['penjuru' => 3]);

        // penjuru berbeza TIDAK boleh mencipta rekod kedua bagi pilihanraya sama
        $this->expectException(QueryException::class);
        DB::table('borang14_forms')->insert($base + ['penjuru' => 2]);
    }

    public function test_parlimen_and_dun_with_same_id_coexist(): void
    {
        $base = [
            'jenis_pr' => 'pru', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'manual', 'needs_review' => false,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('borang14_forms')->insert($base + ['kawasan_type' => 'parlimen', 'kawasan_id' => 1]);
        DB::table('borang14_forms')->insert($base + ['kawasan_type' => 'dun', 'kawasan_id' => 1]);

        $this->assertSame(2, DB::table('borang14_forms')->count());
    }
}
```

- [ ] **Step 2: Jalankan ujian, sahkan ia GAGAL**

Run: `php artisan test --filter=Borang14SchemaTest`
Expected: FAIL — `SQLSTATE... Unknown column 'kawasan_type'`

- [ ] **Step 3: Tulis migration**

```php
<?php
// database/migrations/2026_07_16_100001_reshape_borang14_forms.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // borang14_forms dan scoreboards kosong (0 baris) — drop+recreate selamat.
        Schema::dropIfExists('scoreboards');
        Schema::dropIfExists('borang14_votes');
        Schema::dropIfExists('borang14_forms');

        // Satu pilihanraya = satu borang. penjuru ialah atribut, bukan kunci.
        Schema::create('borang14_forms', function (Blueprint $table) {
            $table->id();
            // Polymorphic: tiada FK constraint kerana menunjuk ke bandar ATAU kadun.
            $table->string('kawasan_type', 10);            // 'parlimen' | 'dun'
            $table->unsignedBigInteger('kawasan_id');
            $table->string('jenis_pr', 4);                 // 'pru' | 'prn' | 'prk'
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('penjuru')->default(2);
            $table->json('parties')->nullable();           // [{slot, keahlian_parti_id, nama}]
            $table->json('structure')->nullable();         // DM/Pusat/Saluran dari scoresheet
            $table->string('status', 10)->default('draft');    // draft | published
            $table->string('source', 12)->default('manual');   // manual | scoresheet
            $table->string('source_filename')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['kawasan_type', 'kawasan_id', 'jenis_pr', 'tahun'], 'borang14_forms_election_unique');
            $table->index(['kawasan_type', 'kawasan_id']);
            $table->index(['status', 'tahun']);
        });

        // Tidak berubah dari asal — dicipta semula kerana FK menunjuk ke borang14_forms.
        Schema::create('borang14_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->string('pusat')->default('');
            $table->string('saluran');
            $table->unsignedTinyInteger('slot'); // 1..6 parti, 90 = ditolak (C), 91 = tidak dimasukkan (D)
            $table->unsignedInteger('undi')->default(0);
            $table->timestamps();

            $table->unique(['borang14_form_id', 'pusat', 'saluran', 'slot'], 'borang14_votes_cell_unique');
        });

        Schema::create('scoreboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->string('title')->default('SCOREBOARD');
            $table->unsignedInteger('minima')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('candidates')->nullable();
            $table->timestamps();

            $table->unique('borang14_form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoreboards');
        Schema::dropIfExists('borang14_votes');
        Schema::dropIfExists('borang14_forms');
    }
};
```

- [ ] **Step 4: Jalankan migration dan ujian, sahkan LULUS**

Run: `php artisan migrate && php artisan test --filter=Borang14SchemaTest`
Expected: PASS — 3 tests

- [ ] **Step 5: Kemas kini `ScoreboardController` guna `borang14_form_id`**

Baca fail dahulu, kemudian ganti setiap lookup `where('kadun_id', ...)->where('penjuru', ...)` dengan resolve melalui `borang14_form_id`:

```php
// SEBELUM
$scoreboard = Scoreboard::where('kadun_id', $kadunId)->where('penjuru', $penjuru)->first();

// SELEPAS
$form = Borang14Form::where('kawasan_type', $kawasanType)
    ->where('kawasan_id', $kawasanId)
    ->where('jenis_pr', $jenisPr)
    ->where('tahun', $tahun)
    ->first();
$scoreboard = $form ? Scoreboard::where('borang14_form_id', $form->id)->first() : null;
```

- [ ] **Step 6: Jalankan suite penuh, sahkan tiada regresi**

Run: `php artisan test --filter=Scoreboard`
Expected: PASS (atau kemas kini ujian Scoreboard yang merujuk kadun_id/penjuru)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_16_100001_reshape_borang14_forms.php \
        app/Http/Controllers/ScoreboardController.php \
        tests/Feature/Borang14SchemaTest.php
git commit -m "Borang 14: kunci baru (kawasan, jenis_pr, tahun); selaraskan scoreboards"
```

---

### Task 2: Jadual `borang14_snapshots`

Jaring keselamatan untuk keputusan spec #3 — scoresheet menimpa senyap, tetapi boleh di-revert.

**Files:**
- Create: `database/migrations/2026_07_16_100002_create_borang14_snapshots.php`
- Test: tambah ke `tests/Feature/Borang14SchemaTest.php`

**Interfaces:**
- Produces: jadual `borang14_snapshots` dengan FK cascade ke `borang14_forms`.

- [ ] **Step 1: Tulis ujian yang gagal**

```php
// tambah ke tests/Feature/Borang14SchemaTest.php
public function test_snapshot_cascades_when_form_deleted(): void
{
    $formId = DB::table('borang14_forms')->insertGetId([
        'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn',
        'tahun' => 2022, 'penjuru' => 3, 'status' => 'draft', 'source' => 'manual',
        'needs_review' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('borang14_snapshots')->insert([
        'borang14_form_id' => $formId,
        'structure' => json_encode(['a' => 1]),
        'votes' => json_encode([]),
        'reason' => 'before_scoresheet_overwrite',
        'created_at' => now(),
    ]);

    DB::table('borang14_forms')->where('id', $formId)->delete();

    $this->assertSame(0, DB::table('borang14_snapshots')->count());
}
```

- [ ] **Step 2: Jalankan ujian, sahkan ia GAGAL**

Run: `php artisan test --filter=test_snapshot_cascades_when_form_deleted`
Expected: FAIL — `Table 'borang14_snapshots' doesn't exist`

- [ ] **Step 3: Tulis migration**

```php
<?php
// database/migrations/2026_07_16_100002_create_borang14_snapshots.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borang14_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->json('structure')->nullable();
            $table->json('votes');
            $table->json('parties')->nullable();
            $table->string('reason', 40);   // 'before_scoresheet_overwrite'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['borang14_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borang14_snapshots');
    }
};
```

- [ ] **Step 4: Jalankan ujian, sahkan LULUS**

Run: `php artisan migrate && php artisan test --filter=Borang14SchemaTest`
Expected: PASS — 4 tests

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_16_100002_create_borang14_snapshots.php tests/Feature/Borang14SchemaTest.php
git commit -m "Borang 14: jadual snapshot untuk revert selepas overwrite scoresheet"
```

---

### Task 3: Model `Borang14Form` + `Borang14Snapshot`

**Files:**
- Modify: `app/Models/Borang14Form.php`
- Create: `app/Models/Borang14Snapshot.php`
- Test: `tests/Unit/Borang14FormTest.php`

**Interfaces:**
- Produces:
  - `Borang14Form::kawasan(): Bandar|Kadun|null`
  - `Borang14Form::kawasanNama(): string`
  - `Borang14Form::scopePublished(Builder $q): Builder`
  - `Borang14Form::scopeForKawasan(Builder $q, string $type, int $id): Builder`
  - `Borang14Form::snapshots(): HasMany`
  - `Borang14Snapshot` dengan casts `structure|votes|parties => array`
- Consumes: `App\Models\Bandar`, `App\Models\Kadun`.

- [ ] **Step 1: Tulis ujian yang gagal**

```php
<?php
// tests/Unit/Borang14FormTest.php
namespace Tests\Unit;

use App\Models\Borang14Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14FormTest extends TestCase
{
    use RefreshDatabase;

    public function test_kawasan_resolves_to_kadun_for_dun_type(): void
    {
        $kadun = \App\Models\Kadun::first();
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 3,
        ]);

        $this->assertInstanceOf(\App\Models\Kadun::class, $form->kawasan());
        $this->assertSame($kadun->nama, $form->kawasanNama());
    }

    public function test_kawasan_resolves_to_bandar_for_parlimen_type(): void
    {
        $bandar = \App\Models\Bandar::first();
        $form = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'pru', 'tahun' => 2022, 'penjuru' => 2,
        ]);

        $this->assertInstanceOf(\App\Models\Bandar::class, $form->kawasan());
    }

    public function test_published_scope_excludes_drafts(): void
    {
        $kadun = \App\Models\Kadun::first();
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 3, 'status' => 'draft',
        ]);
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2026, 'penjuru' => 3, 'status' => 'published',
        ]);

        $this->assertSame(1, Borang14Form::published()->count());
    }
}
```

- [ ] **Step 2: Jalankan ujian, sahkan ia GAGAL**

Run: `php artisan test --filter=Borang14FormTest`
Expected: FAIL — `Call to undefined method App\Models\Borang14Form::kawasan()`

- [ ] **Step 3: Tulis model**

```php
<?php
// app/Models/Borang14Form.php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Borang14Form extends Model
{
    public const KAWASAN_PARLIMEN = 'parlimen';
    public const KAWASAN_DUN = 'dun';

    protected $fillable = [
        'kawasan_type', 'kawasan_id', 'jenis_pr', 'tahun', 'penjuru',
        'parties', 'structure', 'status', 'source', 'source_filename',
        'needs_review', 'published_at',
    ];

    protected $casts = [
        'parties' => 'array',
        'structure' => 'array',
        'needs_review' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(Borang14Vote::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Borang14Snapshot::class);
    }

    /** Polymorphic tanpa FK — kawasan_type menentukan jadual sasaran. */
    public function kawasan(): Bandar|Kadun|null
    {
        return $this->kawasan_type === self::KAWASAN_PARLIMEN
            ? Bandar::find($this->kawasan_id)
            : Kadun::find($this->kawasan_id);
    }

    public function kawasanNama(): string
    {
        return $this->kawasan()?->nama ?? '—';
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeForKawasan(Builder $q, string $type, int $id): Builder
    {
        return $q->where('kawasan_type', $type)->where('kawasan_id', $id);
    }
}
```

```php
<?php
// app/Models/Borang14Snapshot.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Borang14Snapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['borang14_form_id', 'structure', 'votes', 'parties', 'reason', 'created_by'];

    protected $casts = [
        'structure' => 'array',
        'votes' => 'array',
        'parties' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Borang14Form::class, 'borang14_form_id');
    }
}
```

- [ ] **Step 4: Jalankan ujian, sahkan LULUS**

Run: `php artisan test --filter=Borang14FormTest`
Expected: PASS — 3 tests

- [ ] **Step 5: Commit**

```bash
git add app/Models/Borang14Form.php app/Models/Borang14Snapshot.php tests/Unit/Borang14FormTest.php
git commit -m "Borang 14: model kawasan polymorphic + snapshot"
```

---

### Task 4: `Borang14Reference::forBandar()`

Untuk Borang 14 peringkat Parlimen. `daerah_mengundi.bandar_id` memang sudah menunjuk ke Parlimen, jadi DM dikumpul terus tanpa melalui DUN. **Jangan ubah `forKadun()`.**

**Files:**
- Modify: `app/Support/Borang14Reference.php`
- Test: `tests/Unit/Borang14ReferenceTest.php`

**Interfaces:**
- Produces: `Borang14Reference::forBandar(int $bandarId): ?array` — bentuk pulangan sama dengan `forKadun()`: `{negeri, parlimen, dun, source?, daerah_mengundi[], undi_awal, undi_pos}`.

- [ ] **Step 1: Baca `forKadun()` sepenuhnya**

Run: `sed -n '1,80p' app/Support/Borang14Reference.php`
Tujuan: fahami bentuk pulangan dan `deriveFromDpt()` sebelum mencerminkannya.

- [ ] **Step 2: Tulis ujian yang gagal**

```php
<?php
// tests/Unit/Borang14ReferenceTest.php
namespace Tests\Unit;

use App\Support\Borang14Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14ReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_bandar_returns_null_when_no_dm_exists(): void
    {
        $this->assertNull(Borang14Reference::forBandar(999999));
    }

    public function test_for_bandar_groups_daerah_mengundi_under_parlimen(): void
    {
        $bandar = \App\Models\Bandar::whereHas('daerahMengundi')->first();
        if (! $bandar) {
            $this->markTestSkipped('Tiada bandar dengan daerah_mengundi dalam data ujian.');
        }

        $ref = Borang14Reference::forBandar($bandar->id);

        $this->assertIsArray($ref);
        $this->assertSame($bandar->nama, $ref['parlimen']);
        $this->assertNotEmpty($ref['daerah_mengundi']);
        $this->assertArrayHasKey('pusat_mengundi', $ref['daerah_mengundi'][0]);
    }
}
```

- [ ] **Step 3: Jalankan ujian, sahkan ia GAGAL**

Run: `php artisan test --filter=Borang14ReferenceTest`
Expected: FAIL — `Call to undefined method App\Support\Borang14Reference::forBandar()`

- [ ] **Step 4: Tambah `forBandar()`**

```php
// app/Support/Borang14Reference.php — tambah kaedah, jangan ubah forKadun()

/**
 * Struktur rujukan untuk kerusi Parlimen.
 * daerah_mengundi.bandar_id sudah menunjuk ke Parlimen, jadi tiada join melalui kadun.
 */
public static function forBandar(int $bandarId): ?array
{
    $bandar = \App\Models\Bandar::with('negeri')->find($bandarId);
    if (! $bandar) {
        return null;
    }

    $rows = \DB::table('pangkalan_data_pengundi')
        ->select('daerah_mengundi', 'lokaliti', \DB::raw('COUNT(*) as jumlah'))
        ->whereRaw('UPPER(parlimen) = ?', [mb_strtoupper($bandar->nama)])
        ->groupBy('daerah_mengundi', 'lokaliti')
        ->orderBy('daerah_mengundi')
        ->orderBy('lokaliti')
        ->get();

    if ($rows->isEmpty()) {
        return null;
    }

    $dm = [];
    foreach ($rows->groupBy('daerah_mengundi') as $nama => $group) {
        $dm[] = [
            'nama' => (string) $nama,
            'jumlah_berdaftar' => (int) $group->sum('jumlah'),
            'pusat_mengundi' => $group->map(fn ($r) => [
                'nama' => (string) $r->lokaliti,
                'jumlah_berdaftar' => (int) $r->jumlah,
                'saluran' => [['no' => 1, 'berdaftar' => (int) $r->jumlah]],
            ])->values()->all(),
        ];
    }

    return [
        'negeri' => $bandar->negeri?->nama ?? '',
        'parlimen' => $bandar->nama,
        'dun' => null,
        'source' => 'dpt_estimate',
        'daerah_mengundi' => $dm,
        'undi_awal' => ['berdaftar' => 0],
        'undi_pos' => ['berdaftar' => 0],
    ];
}
```

- [ ] **Step 5: Jalankan ujian, sahkan LULUS**

Run: `php artisan test --filter=Borang14ReferenceTest`
Expected: PASS — 2 tests

- [ ] **Step 6: Commit**

```bash
git add app/Support/Borang14Reference.php tests/Unit/Borang14ReferenceTest.php
git commit -m "Borang 14: Borang14Reference::forBandar() untuk kerusi Parlimen"
```

---

### Task 5: `ScoresheetExtractor::extractDetailed()`

**JANGAN** ubah `const SYSTEM` atau `extract()` — Analisa bergantung padanya. Tambah prompt constant kedua dan kaedah baharu.

**Files:**
- Modify: `app/Services/Pilihanraya/ScoresheetExtractor.php`
- Create: `tests/fixtures/scoresheet-juasseh-2023.json`
- Test: `tests/Unit/ScoresheetExtractorDetailedTest.php`

**Interfaces:**
- Produces: `ScoresheetExtractor::extractDetailed(UploadedFile $file): array` → `['ok'=>bool, 'data'=>array|null, 'error'=>?string]`; `ScoresheetExtractor::validateBalance(array $data): array` → senarai baris tidak seimbang.
- Consumes: `ClaudeService::chat()`, `documentModel()`, `extractJson()`.

**Kenapa fixture JSON, bukan PDF:** fail `Score Sheet Juasseh - PRN N9 - 2023.pdf` berada dalam OneDrive peribadi user, bukan repo. Ia juga mengandungi nama calon sebenar. Fixture JSON bentuk-terekstrak membolehkan ujian unit berjalan deterministik tanpa memanggil API Claude (mahal, tidak deterministik) dan tanpa memasukkan PDF peribadi ke dalam repo.

- [ ] **Step 1: Cipta fixture dari angka sheet sebenar yang telah disahkan**

```json
{
  "negeri": "NEGERI SEMBILAN",
  "kawasan_kod": "N.15",
  "kawasan_nama": "JUASSEH",
  "parlimen_kod": "129",
  "jumlah_pemilih": 13408,
  "calon": [
    { "nama": "EDDIN SYAZLEE BIN SHITH", "parti_tekaan": "PN", "yakin": true },
    { "nama": "PUAN SRI BIBI SHARLIZA", "parti_tekaan": null, "yakin": false }
  ],
  "rows": [
    { "dm_kod": null, "dm": null, "pusat": "", "saluran": "UNDI POS",
      "a": 203, "undi": [98, 73], "jumlah_undian": 171, "ditolak": 18, "tidak_dimasukkan": 14 },
    { "dm_kod": "129/15/01", "dm": "KAMPONG TENGKEK", "pusat": "SEKOLAH KEBANGSAAN TENGKEK",
      "saluran": "1", "a": 127, "undi": [48, 76], "jumlah_undian": 124, "ditolak": 3, "tidak_dimasukkan": 0 },
    { "dm_kod": "129/15/01", "dm": "KAMPONG TENGKEK", "pusat": "SEKOLAH KEBANGSAAN TENGKEK",
      "saluran": "2", "a": 211, "undi": [102, 108], "jumlah_undian": 210, "ditolak": 1, "tidak_dimasukkan": 0 },
    { "dm_kod": "129/15/09", "dm": "PEKAN JUASSEH", "pusat": "SEKOLAH KEBANGSAAN PUSAT JUASSEH",
      "saluran": "2", "a": 170, "undi": [65, 103], "jumlah_undian": 168, "ditolak": 1, "tidak_dimasukkan": 1 }
  ],
  "jumlah": { "a": 9122, "undi": [4471, 4549], "jumlah_undian": 9020, "ditolak": 87, "tidak_dimasukkan": 15 }
}
```

- [ ] **Step 2: Tulis ujian yang gagal**

```php
<?php
// tests/Unit/ScoresheetExtractorDetailedTest.php
namespace Tests\Unit;

use App\Services\Pilihanraya\ScoresheetExtractor;
use Tests\TestCase;

class ScoresheetExtractorDetailedTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true);
    }

    public function test_balance_holds_for_every_row(): void
    {
        $bad = ScoresheetExtractor::validateBalance($this->fixture());
        $this->assertSame([], $bad, 'Setiap baris mesti: a == sum(undi) + ditolak + tidak_dimasukkan');
    }

    public function test_grand_total_balances(): void
    {
        $j = $this->fixture()['jumlah'];
        $this->assertSame($j['a'], array_sum($j['undi']) + $j['ditolak'] + $j['tidak_dimasukkan']);
        $this->assertSame(9122, $j['a']);
        $this->assertSame(9020, $j['jumlah_undian']);
    }

    public function test_undi_pos_row_uses_empty_pusat(): void
    {
        $pos = collect($this->fixture()['rows'])->firstWhere('saluran', 'UNDI POS');
        $this->assertNotNull($pos);
        $this->assertSame('', $pos['pusat']);
        $this->assertSame(203, $pos['a']);
        $this->assertSame([98, 73], $pos['undi']);
    }

    public function test_no_undi_awal_row_is_fabricated(): void
    {
        $awal = collect($this->fixture()['rows'])->firstWhere('saluran', 'UNDI AWAL');
        $this->assertNull($awal, 'Sheet Juasseh tiada Undi Awal — jangan reka baris kosong.');
    }

    public function test_berdaftar_is_never_returned(): void
    {
        foreach ($this->fixture()['rows'] as $r) {
            $this->assertArrayNotHasKey('berdaftar', $r, 'Scoresheet tiada berdaftar — (A) bukan berdaftar.');
        }
    }

    public function test_unbalanced_row_is_reported(): void
    {
        $data = $this->fixture();
        $data['rows'][1]['a'] = 999;   // rosakkan dengan sengaja
        $bad = ScoresheetExtractor::validateBalance($data);
        $this->assertCount(1, $bad);
        $this->assertSame(1, $bad[0]['index']);
    }
}
```

- [ ] **Step 3: Jalankan ujian, sahkan ia GAGAL**

Run: `php artisan test --filter=ScoresheetExtractorDetailedTest`
Expected: FAIL — `Call to undefined method ...::validateBalance()`

- [ ] **Step 4: Tambah prompt constant + kaedah**

```php
// app/Services/Pilihanraya/ScoresheetExtractor.php — TAMBAH; jangan sentuh SYSTEM atau extract()

/**
 * Prompt kedua: kekalkan Pusat Mengundi + Saluran (lajur 3 & 4) yang SYSTEM sengaja buang.
 * Borang SPR 760 Pin. 1/99 susunan lajur kiri -> kanan:
 *   Bil | No. Kod Daerah Mengundi | Nama Pusat Mengundi | No. Tempat Mengundi (Saluran)
 *   | Jumlah kertas undi dalam peti (A) | [satu lajur per CALON] | Jumlah undian oleh pemilih
 *   | Bilangan kertas undi ditolak (C) | Jumlah kertas undi tidak dimasukkan ke peti (D)
 */
private const SYSTEM_DETAILED = <<<'TXT'
You read Malaysian SPR "HELAIAN MATA (SCORE SHEET)", Borang SPR 760, and return JSON only.

COLUMN ORDER (left to right, fixed):
  Bil | No. Kod Daerah Mengundi | Nama Pusat Mengundi | No. Tempat Mengundi (Saluran)
  | Jumlah kertas undi yang patut berada di dalam peti undi (A)
  | one column PER CANDIDATE under "Bilangan undian oleh pemilih bagi setiap orang calon"
  | Jumlah undian oleh pemilih | Bilangan kertas undi yang ditolak (C)
  | Jumlah kertas undi ... tidak dimasukkan ke dalam peti undi (D)

RULES:
1. PRESERVE "Nama Pusat Mengundi" and "No. Tempat Mengundi (Saluran)" on EVERY row.
   Do NOT aggregate per Daerah Mengundi. One JSON row per saluran row on the sheet.
2. "undi" is a POSITIONAL ARRAY aligned to "calon" left-to-right. Never reorder, merge,
   skip, or shift a column — not even when a small 3-digit value sits between larger ones.
   The count of numbers in "undi" MUST equal the count of entries in "calon" on every row.
3. Rows before the "UNDI BIASA" section header (e.g. "UNDI POS", "UNDI AWAL") have no
   Pusat Mengundi and no Saluran. Emit them as {"pusat":"","saluran":"UNDI POS"} etc.
   Only emit a row that actually appears — never fabricate a missing UNDI AWAL/POS row.
4. Candidate columns are headed by a PERSON'S NAME with a party LOGO IMAGE. Set
   "parti_tekaan" only if the coalition is unambiguous from visible text; otherwise null
   with "yakin": false. Never guess from the candidate's name.
5. "jumlah_pemilih" is the "JUMLAH PEMILIH" figure at the TOP of the sheet. It is NOT
   column (A). There is NO registered-voter ("berdaftar") figure per saluran — never invent one.
6. IGNORE diagonal watermarks ("DRAFT", "JPRP") and footer text.
7. Copy every number verbatim. Never compute, estimate, or invent.
8. Read the seat from the header: "BAHAGIAN PILIHAN RAYA NEGERI : N.15 JUASSEH" ->
   kawasan_kod "N.15", kawasan_nama "JUASSEH". Kod DM "129 / 15 / 01" encodes
   Parlimen 129 / DUN 15 / DM 01 -> parlimen_kod "129".

Return ONLY this JSON:
{"negeri":str,"kawasan_kod":str,"kawasan_nama":str,"parlimen_kod":str|null,
 "jumlah_pemilih":int,
 "calon":[{"nama":str,"parti_tekaan":str|null,"yakin":bool}],
 "rows":[{"dm_kod":str|null,"dm":str|null,"pusat":str,"saluran":str,
          "a":int,"undi":[int],"jumlah_undian":int,"ditolak":int,"tidak_dimasukkan":int}],
 "jumlah":{"a":int,"undi":[int],"jumlah_undian":int,"ditolak":int,"tidak_dimasukkan":int}}
TXT;

/**
 * Baca scoresheet dengan mengekalkan Pusat Mengundi + Saluran.
 * Guna semula penghantaran media milik extract() — PDF/imej dihantar native ke Claude.
 */
public function extractDetailed(\Illuminate\Http\UploadedFile $file): array
{
    $content = $this->buildContentBlocks($file);   // kaedah sedia ada yang dipakai extract()
    if ($content === null) {
        return ['ok' => false, 'data' => null, 'error' => 'Format fail tidak disokong.'];
    }

    $res = $this->claude->chat(
        self::SYSTEM_DETAILED,
        $content,
        maxTokens: 8000,
        timeout: 180,
        context: 'scoresheet_extract_detailed',
        model: $this->claude->documentModel(),
    );

    if (! ($res['ok'] ?? false)) {
        return ['ok' => false, 'data' => null, 'error' => $res['error'] ?? 'Bacaan AI gagal.'];
    }

    $data = $this->claude->extractJson($res['content'] ?? '');
    if (! is_array($data) || empty($data['rows'])) {
        return ['ok' => false, 'data' => null, 'error' => 'AI tidak memulangkan baris yang sah.'];
    }

    return ['ok' => true, 'data' => $data, 'error' => null];
}

/**
 * Silang-semak setiap baris: (A) == jumlah undi calon + (C) + (D).
 * Disahkan pada sheet Juasseh sebenar: 4471 + 4549 + 87 + 15 == 9122.
 * @return array<int, array{index:int, pusat:string, saluran:string, jangka:int, dapat:int}>
 */
public static function validateBalance(array $data): array
{
    $bad = [];
    foreach (($data['rows'] ?? []) as $i => $r) {
        $jangka = array_sum($r['undi'] ?? []) + (int) ($r['ditolak'] ?? 0) + (int) ($r['tidak_dimasukkan'] ?? 0);
        if ($jangka !== (int) ($r['a'] ?? 0)) {
            $bad[] = [
                'index' => $i,
                'pusat' => (string) ($r['pusat'] ?? ''),
                'saluran' => (string) ($r['saluran'] ?? ''),
                'jangka' => $jangka,
                'dapat' => (int) ($r['a'] ?? 0),
            ];
        }
    }
    return $bad;
}
```

- [ ] **Step 5: Jika `buildContentBlocks()` tiada, ekstraknya dari `extract()`**

Baca `extract()` dan asingkan logik penghantaran media (base64 image/document block) ke `private function buildContentBlocks(UploadedFile $file): ?array`, kemudian panggil ia dari **kedua-dua** `extract()` dan `extractDetailed()`. Kelakuan `extract()` mesti kekal sama — jalankan ujian Analisa sedia ada untuk sahkan.

Run: `php artisan test --filter=Analisa`
Expected: PASS — tiada regresi

- [ ] **Step 6: Jalankan ujian, sahkan LULUS**

Run: `php artisan test --filter=ScoresheetExtractorDetailedTest`
Expected: PASS — 6 tests

- [ ] **Step 7: Commit**

```bash
git add app/Services/Pilihanraya/ScoresheetExtractor.php \
        tests/fixtures/scoresheet-juasseh-2023.json \
        tests/Unit/ScoresheetExtractorDetailedTest.php
git commit -m "Borang 14: extractDetailed() kekalkan Pusat Mengundi + Saluran; silang-semak (A)=undi+C+D"
```

---

### Task 6: Controller, routes, dan cipta-kawasan

**Files:**
- Create: `app/Services/Pilihanraya/KawasanResolver.php`
- Modify: `app/Http/Controllers/Borang14Controller.php`, `routes/web.php` (baris 441-447)
- Test: `tests/Feature/Borang14SubmenuTest.php`

**Interfaces:**
- Produces:
  - `KawasanResolver::resolve(array $extracted): array` → `['ok'=>bool, 'kawasan_type'=>?string, 'kawasan_id'=>?int, 'created'=>array, 'error'=>?string]`
  - Routes: `pilihanraya.borang-14.upload|publish|revert|senarai`
- Consumes: `ScoresheetExtractor::extractDetailed()`, `validateBalance()`, `Borang14Form`, `Borang14Snapshot`.

- [ ] **Step 1: Tulis ujian yang gagal**

```php
<?php
// tests/Feature/Borang14SubmenuTest.php
namespace Tests\Feature;

use App\Models\Borang14Form;
use App\Models\User;
use App\Services\Pilihanraya\KawasanResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14SubmenuTest extends TestCase
{
    use RefreshDatabase;

    private function extracted(array $over = []): array
    {
        return array_merge(
            json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true),
            $over
        );
    }

    public function test_unknown_negeri_is_rejected_and_creates_nothing(): void
    {
        $before = \DB::table('negeri')->count();

        $res = KawasanResolver::resolve($this->extracted(['negeri' => 'NEGERI REKAAN']));

        $this->assertFalse($res['ok']);
        $this->assertSame($before, \DB::table('negeri')->count(), 'Negeri TIDAK boleh dicipta.');
    }

    public function test_missing_kawasan_is_created_under_matched_negeri(): void
    {
        // Juasseh tiada dalam sistem — Negeri Sembilan ada 0 DUN.
        $res = KawasanResolver::resolve($this->extracted());

        $this->assertTrue($res['ok']);
        $this->assertSame('dun', $res['kawasan_type']);
        $this->assertDatabaseHas('kadun', ['nama' => 'JUASSEH']);
        $this->assertDatabaseHas('bandar', ['nama' => 'P.129']);
    }

    public function test_publish_moves_draft_into_senarai(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $kadun = \App\Models\Kadun::first();
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.publish'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertSame('published', $form->fresh()->status);
        $this->assertNotNull($form->fresh()->published_at);
    }

    public function test_senarai_returns_drafts_and_published(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $kadun = \App\Models\Kadun::first();
        foreach ([['prn', 2022, 'draft'], ['prn', 2026, 'published']] as [$j, $t, $s]) {
            Borang14Form::create([
                'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
                'jenis_pr' => $j, 'tahun' => $t, 'penjuru' => 2, 'status' => $s,
            ]);
        }

        $res = $this->actingAs($user)->getJson(route('pilihanraya.borang-14.senarai', [
            'negeri_id' => $kadun->bandar->negeri_id,
        ]));

        $res->assertOk()->assertJsonCount(2, 'rows');
    }
}
```

- [ ] **Step 2: Jalankan ujian, sahkan ia GAGAL**

Run: `php artisan test --filter=Borang14SubmenuTest`
Expected: FAIL — `Class "App\Services\Pilihanraya\KawasanResolver" not found`

- [ ] **Step 3: Tulis `KawasanResolver`**

```php
<?php
// app/Services/Pilihanraya/KawasanResolver.php
namespace App\Services\Pilihanraya;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;

/**
 * Padan kawasan dari header scoresheet; cipta jika tiada.
 * Hanya Johor (56) + Pulau Pinang (40) ada DUN — 14 negeri lain kosong, jadi
 * kebanyakan upload memerlukan penciptaan kawasan.
 *
 * Kekangan keselamatan: negeri MESTI dipadankan dengan 16 negeri sedia ada.
 * Negeri TIDAK PERNAH dicipta — itu menghalang bacaan AI tersasar mencemarkan
 * data geografi induk.
 */
class KawasanResolver
{
    public static function resolve(array $extracted): array
    {
        $negeri = Negeri::whereRaw('UPPER(nama) = ?', [mb_strtoupper(trim($extracted['negeri'] ?? ''))])->first();
        if (! $negeri) {
            return [
                'ok' => false, 'kawasan_type' => null, 'kawasan_id' => null, 'created' => [],
                'error' => 'Negeri "' . ($extracted['negeri'] ?? '') . '" tidak dikenali dalam sistem.',
            ];
        }

        $created = [];

        // Kod DM '129/15/01' -> Parlimen 129. Nama Parlimen jarang ada pada sheet.
        $parlimenKod = trim((string) ($extracted['parlimen_kod'] ?? ''));
        if ($parlimenKod === '') {
            return [
                'ok' => false, 'kawasan_type' => null, 'kawasan_id' => null, 'created' => [],
                'error' => 'Kod Parlimen tidak dapat dibaca dari scoresheet.',
            ];
        }

        $namaParlimen = 'P.' . $parlimenKod;   // placeholder — jangan teka nama sebenar
        $bandar = Bandar::where('negeri_id', $negeri->id)
            ->whereRaw('UPPER(nama) = ?', [mb_strtoupper($namaParlimen)])
            ->first();

        if (! $bandar) {
            $bandar = Bandar::create(['nama' => $namaParlimen, 'negeri_id' => $negeri->id]);
            $created[] = ['jenis' => 'parlimen', 'nama' => $namaParlimen];
        }

        $namaDun = trim((string) ($extracted['kawasan_nama'] ?? ''));
        if ($namaDun === '') {
            return [
                'ok' => false, 'kawasan_type' => null, 'kawasan_id' => null, 'created' => $created,
                'error' => 'Nama kawasan tidak dapat dibaca dari scoresheet.',
            ];
        }

        $kadun = Kadun::where('bandar_id', $bandar->id)
            ->whereRaw('UPPER(nama) = ?', [mb_strtoupper($namaDun)])
            ->first();

        if (! $kadun) {
            $kadun = Kadun::create([
                'nama' => $namaDun,
                'kod_dun' => $extracted['kawasan_kod'] ?? null,
                'bandar_id' => $bandar->id,
            ]);
            $created[] = ['jenis' => 'dun', 'nama' => $namaDun];
        }

        return [
            'ok' => true,
            'kawasan_type' => Borang14Form::KAWASAN_DUN,
            'kawasan_id' => $kadun->id,
            'created' => $created,
            'error' => null,
        ];
    }
}
```

Import yang diperlukan di kepala fail: `use App\Models\Borang14Form;` (pemalar
`KAWASAN_DUN` ditakrif dalam Task 3), di samping `Bandar`, `Kadun`, `Negeri`.

- [ ] **Step 4: Tambah kaedah controller**

```php
// app/Http/Controllers/Borang14Controller.php — TAMBAH

public function upload(Request $request, ScoresheetExtractor $extractor)
{
    $data = $request->validate([
        'fail' => 'required|file|mimes:xlsx,xls,csv,txt,pdf,jpg,jpeg,png,webp|max:20480',
        'jenis_pr' => 'required|in:pru,prn,prk',
        'tahun' => 'required|integer|between:1959,2100',
    ]);

    @set_time_limit(200);

    $res = $extractor->extractDetailed($request->file('fail'));
    if (! $res['ok']) {
        return response()->json(['message' => $res['error'] ?: 'Bacaan scoresheet gagal. Semak Tetapan → Claude.'], 422);
    }

    $kawasan = KawasanResolver::resolve($res['data']);
    if (! $kawasan['ok']) {
        return response()->json(['message' => $kawasan['error']], 422);
    }

    $form = Borang14Form::firstOrNew([
        'kawasan_type' => $kawasan['kawasan_type'],
        'kawasan_id' => $kawasan['kawasan_id'],
        'jenis_pr' => $data['jenis_pr'],
        'tahun' => $data['tahun'],
    ]);

    // Scoresheet menang — tetapi simpan keadaan lama dahulu supaya boleh revert.
    if ($form->exists) {
        Borang14Snapshot::create([
            'borang14_form_id' => $form->id,
            'structure' => $form->structure,
            'votes' => $form->votes()->get(['pusat', 'saluran', 'slot', 'undi'])->toArray(),
            'parties' => $form->parties,
            'reason' => 'before_scoresheet_overwrite',
            'created_by' => $request->user()?->id,
        ]);
        $form->votes()->delete();
    }

    $unbalanced = ScoresheetExtractor::validateBalance($res['data']);
    $anyGuess = collect($res['data']['calon'] ?? [])->contains(fn ($c) => ! ($c['yakin'] ?? false));
    $noSaluran = collect($res['data']['rows'] ?? [])
        ->contains(fn ($r) => ($r['pusat'] ?? '') !== '' && blank($r['saluran'] ?? null));

    $form->fill([
        'penjuru' => max(2, count($res['data']['calon'] ?? [])),
        'parties' => collect($res['data']['calon'] ?? [])->values()
            ->map(fn ($c, $i) => ['slot' => $i + 1, 'keahlian_parti_id' => null, 'nama' => $c['nama']])->all(),
        'structure' => $res['data'],
        'status' => 'draft',
        'source' => 'scoresheet',
        'source_filename' => $request->file('fail')->getClientOriginalName(),
        'needs_review' => $unbalanced !== [] || $anyGuess || $noSaluran,
    ])->save();

    foreach ($res['data']['rows'] as $r) {
        foreach (($r['undi'] ?? []) as $i => $undi) {
            $this->putVote($form, $r, $i + 1, (int) $undi);
        }
        $this->putVote($form, $r, 90, (int) ($r['ditolak'] ?? 0));
        $this->putVote($form, $r, 91, (int) ($r['tidak_dimasukkan'] ?? 0));
    }

    return response()->json([
        'ok' => true,
        'form_id' => $form->id,
        'created' => $kawasan['created'],
        'unbalanced' => $unbalanced,
        'needs_review' => $form->needs_review,
    ]);
}

/** Satu baris per sel. Baris Undi Pos/Awal guna pusat=''. */
private function putVote(Borang14Form $form, array $row, int $slot, int $undi): void
{
    Borang14Vote::updateOrCreate(
        [
            'borang14_form_id' => $form->id,
            'pusat' => (string) ($row['pusat'] ?? ''),
            'saluran' => (string) ($row['saluran'] ?? '1'),
            'slot' => $slot,
        ],
        ['undi' => $undi],
    );
}

public function publish(Request $request)
{
    $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);
    $form = Borang14Form::findOrFail($data['form_id']);
    $form->update(['status' => 'published', 'published_at' => now()]);

    return response()->json(['ok' => true, 'published_at' => $form->published_at]);
}

public function revert(Request $request)
{
    $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);
    $form = Borang14Form::findOrFail($data['form_id']);

    $snap = $form->snapshots()->latest('created_at')->first();
    if (! $snap) {
        return response()->json(['message' => 'Tiada snapshot untuk dipulihkan.'], 422);
    }

    $form->votes()->delete();
    foreach ($snap->votes as $v) {
        Borang14Vote::create([
            'borang14_form_id' => $form->id,
            'pusat' => $v['pusat'], 'saluran' => $v['saluran'],
            'slot' => $v['slot'], 'undi' => $v['undi'],
        ]);
    }
    $form->update(['structure' => $snap->structure, 'parties' => $snap->parties]);

    return response()->json(['ok' => true]);
}

public function senarai(Request $request)
{
    $data = $request->validate([
        'negeri_id' => 'required|integer|exists:negeri,id',
        'bandar_id' => 'nullable|integer|exists:bandar,id',
        'kadun_id' => 'nullable|integer|exists:kadun,id',
    ]);

    // Semantik penapis (spec):
    //   Negeri sahaja      -> semua rekod dalam negeri (Parlimen DAN DUN)
    //   + Parlimen         -> rekod Parlimen itu DAN semua DUN di bawahnya
    //   + DUN              -> DUN itu sahaja
    $bandarIds = Bandar::where('negeri_id', $data['negeri_id'])->pluck('id');
    if (! empty($data['bandar_id'])) {
        $bandarIds = collect([$data['bandar_id']]);
    }
    $kadunIds = ! empty($data['kadun_id'])
        ? collect([$data['kadun_id']])
        : Kadun::whereIn('bandar_id', $bandarIds)->pluck('id');

    $rows = Borang14Form::query()
        ->where(function ($q) use ($bandarIds, $kadunIds, $data) {
            if (empty($data['kadun_id'])) {
                $q->orWhere(fn ($w) => $w->where('kawasan_type', 'parlimen')->whereIn('kawasan_id', $bandarIds));
            }
            $q->orWhere(fn ($w) => $w->where('kawasan_type', 'dun')->whereIn('kawasan_id', $kadunIds));
        })
        ->orderByDesc('tahun')->orderBy('jenis_pr')->orderBy('kawasan_type')
        ->get()
        ->map(fn (Borang14Form $f) => [
            'id' => $f->id, 'tahun' => $f->tahun, 'jenis_pr' => $f->jenis_pr,
            'kawasan_type' => $f->kawasan_type, 'kawasan_nama' => $f->kawasanNama(),
            'penjuru' => $f->penjuru, 'status' => $f->status, 'source' => $f->source,
            'source_filename' => $f->source_filename, 'needs_review' => $f->needs_review,
            'published_at' => $f->published_at,
        ]);

    return response()->json(['rows' => $rows]);
}
```

- [ ] **Step 5: Lebarkan validation `slot` dalam `saveVote()`**

```php
// app/Http/Controllers/Borang14Controller.php — dalam saveVote()
// SEBELUM: 'slot' => 'required|integer|between:1,6',
'slot' => 'required|integer|in:1,2,3,4,5,6,90,91',   // 90 = ditolak (C), 91 = tidak dimasukkan (D)
```

Juga tukar `kadun_id` → `kawasan_type` + `kawasan_id` + `jenis_pr` + `tahun` dalam `data()`, `saveVote()`, `saveParties()`, `pdf()`, dan gantikan `firstOrCreate(['kadun_id','penjuru'])` dengan `firstOrCreate(['kawasan_type','kawasan_id','jenis_pr','tahun'])`.

- [ ] **Step 6: Daftar routes**

```php
// routes/web.php — dalam kumpulan bernama 'pilihanraya.' (sekitar baris 441-447)
Route::post('/borang-14/upload',  [Borang14Controller::class, 'upload'])  ->name('borang-14.upload') ->middleware('throttle:10,1');
Route::post('/borang-14/publish', [Borang14Controller::class, 'publish']) ->name('borang-14.publish')->middleware('throttle:30,1');
Route::post('/borang-14/revert',  [Borang14Controller::class, 'revert'])  ->name('borang-14.revert') ->middleware('throttle:10,1');
Route::get ('/borang-14/senarai', [Borang14Controller::class, 'senarai']) ->name('borang-14.senarai');
```

- [ ] **Step 7: Jalankan ujian, sahkan LULUS**

Run: `php artisan test --filter=Borang14SubmenuTest`
Expected: PASS — 4 tests

- [ ] **Step 8: Jalankan suite penuh, sahkan tiada regresi**

Run: `php artisan test --filter=Borang14 && php artisan test --filter=Analisa`
Expected: PASS semua

- [ ] **Step 9: Commit**

```bash
git add app/Services/Pilihanraya/KawasanResolver.php \
        app/Http/Controllers/Borang14Controller.php \
        routes/web.php tests/Feature/Borang14SubmenuTest.php
git commit -m "Borang 14: upload/publish/revert/senarai + cipta kawasan dari scoresheet"
```

---

### Task 7: Extract `Borang14Form.jsx` — pure refactor of the 621-line page

**Files:**
- Create: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/components/Borang14Form.jsx`
- Modify: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/Borang14.jsx`
- Delete (code, not file): local `EditableCell` in `Borang14.jsx` lines 32-65

**Interfaces:**
- Produces (named exports from `components/Borang14Form.jsx`):
  - `cellKey(pusat, saluran, slot): string` — `` `${pusat ?? ''}|${saluran}|${slot}` `` (must stay byte-identical to PHP key)
  - `fmt(n): string`, `pct(num, den): string`, `toBlocks(reference): Block[]`, `leadStatus(values): ('lead'|'low'|'none')[]`
  - `BULOH_KASAP_KADUN_ID = 41`
  - `VoteTable({ block, partyNames, votes, onSave, anchorId })`
  - `UndiAwalPosTable({ partyNames, votes, onSave, rows })` — `rows: [{label, berdaftar}]`
  - `GrandSummary({ partyNames, totals })` — `totals: {partyTotals: number[], keluar, berdaftar}`
- Consumes: `components/EditableCell.jsx` default export `EditableCell({ value, onCommit, mode='int', max=null, invalid=false, className='' })` — prop names already match the call sites in `VoteTable`/`UndiAwalPosTable` (`value`, `max`, `onCommit`), so no call-site changes needed.

- [ ] **Step 1:** Create `components/Borang14Form.jsx` with this header, then move code verbatim:

```jsx
import { usePilihanrayaTheme } from './PilihanrayaShell';
import EditableCell from './EditableCell';
import DragScroll from '../analisa/DragScroll';

/* ------------------------------- helpers ------------------------------- */

export const fmt = (n) => (n == null || Number.isNaN(n) ? '0' : Number(n).toLocaleString('en-MY'));
export const pct = (num, den) => (den > 0 ? `${((num / den) * 100).toFixed(1)}%` : '—');
export const cellKey = (pusat, saluran, slot) => `${pusat ?? ''}|${saluran}|${slot}`;

// Undi Awal & Undi Pos are combined into a single row only for DUN Buloh Kasap.
export const BULOH_KASAP_KADUN_ID = 41;
```

Then move these blocks from `Borang14.jsx` **unchanged except for adding `export`**:
- `toBlocks` (lines 16-26) → `export function toBlocks(reference) { ... }`
- `leadStatus` (lines 71-75), `LeadSquare` (lines 77-85), `totalBgClass` (lines 87-91) → export all three
- `VoteTable` (lines 95-173) → `export function VoteTable(...)`
- `UndiAwalPosTable` (lines 181-237) → `export function UndiAwalPosTable(...)`
- `GrandSummary` (lines 243-287) → `export function GrandSummary(...)`

Do **not** move the local `EditableCell` (lines 32-65) — it is deleted; the shared `./EditableCell` import replaces it. The shared cell has identical commit semantics (clamps to `max`, commits only on change); only cosmetic deltas are `w-24` vs `w-20` and Enter-to-commit, both acceptable per spec cleanup #1.

- [ ] **Step 2:** Rewrite the top of `Borang14.jsx` — delete lines 9-287 (helpers, local `EditableCell`, lead helpers, the three tables) and replace the import block with:

```jsx
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Download, Info, Landmark, MapPin, Vote, Loader2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import {
    VoteTable, UndiAwalPosTable, GrandSummary,
    toBlocks, cellKey, BULOH_KASAP_KADUN_ID,
} from './components/Borang14Form';
```

`DragScroll` and `useRef` are no longer used in this file — remove those imports. `Borang14Body` (lines 308-621) stays byte-identical.

- [ ] **Step 3:** Verify — build and smoke-test:

```bash
npm run build
```
Expected: `✓ built in …s` with no `Rollup failed to resolve` / undefined-export errors.

Manual: open `/pilihanraya/borang-14`, pick Negeri → Parlimen → DUN → Penjuru, type into a cell, blur — value persists after reload (autosave unchanged), PDF button still opens.

- [ ] **Step 4:** Commit:

```bash
git add -A && git commit -m "Borang 14: extract VoteTable/UndiAwalPosTable/GrandSummary into components/Borang14Form, drop duplicate EditableCell"
```

---

### Task 8: Per-cell save-status indicator (`saving | saved | error`)

**Files:**
- Modify: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/components/Borang14Form.jsx`
- Modify: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/Borang14.jsx`

**Interfaces:**
- Produces: `SaveStatusDot({ status })` exported from `Borang14Form.jsx`; `VoteTable` and `UndiAwalPosTable` gain optional prop `cellStatus = {}` — shape `{ [cellKey]: 'saving' | 'saved' | 'error' }`.
- Consumes: existing `POST route('pilihanraya.borang-14.vote')` promise result (no backend change).

- [ ] **Step 1:** In `Borang14Form.jsx` add the indicator (import `Loader2, Check, AlertCircle` from `lucide-react`):

```jsx
export function SaveStatusDot({ status }) {
    if (!status) return <span className="inline-block w-3.5" aria-hidden="true" />;
    if (status === 'saving') return <Loader2 className="h-3.5 w-3.5 animate-spin text-slate-400" aria-label="Menyimpan…" />;
    if (status === 'saved') return <Check className="h-3.5 w-3.5 text-emerald-500" aria-label="Disimpan" />;
    return (
        <AlertCircle
            className="h-3.5 w-3.5 text-rose-500"
            aria-label="Gagal disimpan"
            title="Gagal disimpan — ubah nilai sel ini untuk cuba semula"
        />
    );
}
```

- [ ] **Step 2:** Thread it through `VoteTable` (`function VoteTable({ block, partyNames, votes, onSave, anchorId, cellStatus = {} })`). Replace the party-cell render:

```jsx
{r.slots.map((v, i) => {
    const key = cellKey(block.pusat, String(r.no), i + 1);
    return (
        <td key={i} className="px-2 py-1">
            <div className="flex items-center justify-end gap-1.5">
                <LeadSquare status={r.status[i]} />
                <SaveStatusDot status={cellStatus[key]} />
                <EditableCell
                    value={v}
                    invalid={cellStatus[key] === 'error'}
                    max={r.berdaftar > 0 ? Math.max(0, r.berdaftar - (r.keluar - v)) : null}
                    onCommit={(undi) => onSave(block.pusat, String(r.no), i + 1, undi)}
                />
            </div>
        </td>
    );
})}
```

Apply the identical pattern in `UndiAwalPosTable` with `const key = cellKey('', label, i + 1);` and `invalid={cellStatus[key] === 'error'}`.

- [ ] **Step 3:** In `Borang14Body` (Borang14.jsx) add state + rewrite `saveVote` (currently the `.catch(() => {})` at lines 428-433):

```jsx
const [cellStatus, setCellStatus] = useState({});
const statusTimers = useRef({});
useEffect(() => () => Object.values(statusTimers.current).forEach(clearTimeout), []);

const saveVote = useCallback((pusat, saluran, slot, undi) => {
    const key = cellKey(pusat, saluran, slot);
    setVotes((prev) => ({ ...prev, [key]: undi }));
    setCellStatus((prev) => ({ ...prev, [key]: 'saving' }));
    axios.post(route('pilihanraya.borang-14.vote'), {
        kadun_id: kadunId, penjuru: Number(penjuru), pusat, saluran, slot, undi,
    })
        .then(() => {
            setCellStatus((prev) => ({ ...prev, [key]: 'saved' }));
            clearTimeout(statusTimers.current[key]);
            statusTimers.current[key] = setTimeout(() => {
                setCellStatus((prev) => { const next = { ...prev }; delete next[key]; return next; });
            }, 2000);
        })
        .catch(() => setCellStatus((prev) => ({ ...prev, [key]: 'error' })));
}, [kadunId, penjuru]);
```

Re-add `useRef` to the react import. Pass `cellStatus={cellStatus}` to every `<VoteTable …>` and to `<UndiAwalPosTable …>`. Add a page-level error banner just above the tables (inside `canShowTables`):

```jsx
{Object.values(cellStatus).includes('error') && (
    <div className="bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 text-sm mb-4">
        Sesetengah sel gagal disimpan (bertanda merah). Ubah semula nilai sel itu untuk cuba simpan sekali lagi.
    </div>
)}
```

- [ ] **Step 4:** Verify:

```bash
npm run build
```
Expected: `✓ built in …s`.

Manual: edit a cell → spinner then green tick that fades after 2s. Kill `php artisan serve` (or set DevTools offline), edit a cell → red icon + rose banner + rose cell border; re-enable network, change the value → tick returns.

- [ ] **Step 5:** Commit:

```bash
git add -A && git commit -m "Borang 14: per-cell autosave status indicator (saving/saved/error), stop swallowing errors"
```

---

### Task 9: Tab shell — Upload Scoresheet · Papar · Keyin on `/pilihanraya/borang-14`

**Files:**
- Create: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/borang14/KeyinTab.jsx` (Borang14Body moves here)
- Modify: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/Borang14.jsx` (becomes thin tab shell)

**Interfaces:**
- Consumes: existing page props `{ negeriList, parlimenList, kadunList, partiList, penjuruOptions }`; existing `components/TabBar.jsx` → `TabBar({ tabs: [{key, label, icon}], active, onChange })`; theme tokens `t.tabBar/t.tabActive/t.tabInactive` via `usePilihanrayaTheme`.
- Produces: `KeyinTab({ negeriList, parlimenList, kadunList, partiList, penjuruOptions, prefill })` — `prefill: null | { negeriId, parlimenId, kadunId, kawasanType, jenisPr, tahun, formId, nonce }` (kawasan/jenis/tahun fields consumed in Task 12; `nonce` forces re-apply). Shell exposes `openKeyin(prefill)` internally to the other two tabs.
- DO NOT touch `AuthenticatedLayout.jsx` — spec decision #7 chose in-page tabs over depth-2 nav.

- [ ] **Step 1:** Create `borang14/KeyinTab.jsx`: cut the whole `Borang14Body` function out of `Borang14.jsx` (everything from `function Borang14Body(...)` to end of file, as refactored in Tasks 7-8), paste it, rename to `export default function KeyinTab({ negeriList, parlimenList, kadunList, partiList, penjuruOptions, prefill = null })`, and fix imports (paths gain one `../`):

```jsx
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { Download, Info, Landmark, MapPin, Vote, Loader2 } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import {
    VoteTable, UndiAwalPosTable, GrandSummary,
    toBlocks, cellKey, BULOH_KASAP_KADUN_ID,
} from '../components/Borang14Form';
```

Add the prefill hook right after the existing `useState` block (only geography is consumable until Task 12):

```jsx
useEffect(() => {
    if (!prefill) return;
    setNegeriId(String(prefill.negeriId ?? ''));
    setParlimenId(String(prefill.parlimenId ?? ''));
    setKadunId(String(prefill.kadunId ?? ''));
}, [prefill?.nonce]); // eslint-disable-line react-hooks/exhaustive-deps
```

- [ ] **Step 2:** Rewrite `Borang14.jsx` as the shell. Full file:

```jsx
import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { PencilLine, Table2, Upload } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell from './components/PilihanrayaShell';
import TabBar from './components/TabBar';
import KeyinTab from './borang14/KeyinTab';

const TABS = [
    { key: 'upload', label: 'Upload Scoresheet', icon: Upload },
    { key: 'papar', label: 'Papar', icon: Table2 },
    { key: 'keyin', label: 'Keyin', icon: PencilLine },
];

export default function Borang14({ negeriList, parlimenList, kadunList, partiList, penjuruOptions }) {
    const [tab, setTab] = useState(() =>
        new URLSearchParams(window.location.search).get('tab') || 'keyin');
    const [keyinPrefill, setKeyinPrefill] = useState(null);

    const changeTab = (key) => {
        setTab(key);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', key);
        window.history.replaceState({}, '', url);
    };

    const openKeyin = (prefill) => {
        setKeyinPrefill({ ...prefill, nonce: Date.now() });
        changeTab('keyin');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Borang 14" />
            <PilihanrayaShell title="Borang 14" subtitle="Upload scoresheet SPR, papar sejarah keputusan & keyin undi mengikut saluran">
                <div className="mb-4">
                    <TabBar tabs={TABS} active={tab} onChange={changeTab} />
                </div>

                {/* Keyin stays mounted so half-filled entry is not lost when peeking at other tabs. */}
                <div className={tab === 'keyin' ? '' : 'hidden'}>
                    <KeyinTab
                        negeriList={negeriList}
                        parlimenList={parlimenList}
                        kadunList={kadunList}
                        partiList={partiList}
                        penjuruOptions={penjuruOptions}
                        prefill={keyinPrefill}
                    />
                </div>
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
```

Note: `upload`/`papar` tab bodies land in Tasks 10 and 11 — until then those tabs render only the bar (`openKeyin` is already wired for them). Keyin behaviour is unchanged.

- [ ] **Step 3:** Verify:

```bash
npm run build
```
Expected: `✓ built in …s`.

Manual: `/pilihanraya/borang-14` shows the three-tab bar with Keyin active and fully working; `/pilihanraya/borang-14?tab=upload` opens with the Upload tab highlighted; clicking tabs updates `?tab=` without a page reload; sidebar nav is untouched.

- [ ] **Step 4:** Commit:

```bash
git add -A && git commit -m "Borang 14: three-tab shell (Upload Scoresheet / Papar / Keyin), Keyin extracted to borang14/KeyinTab"
```

---

### Task 10: Tab "Upload Scoresheet" — cascading picker, dropzone, kawasan-create confirmation

**Files:**
- Create: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/borang14/KawasanPicker.jsx`
- Create: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/borang14/ConfirmDialog.jsx`
- Create: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/borang14/UploadTab.jsx`
- Modify: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/Borang14.jsx` (mount the tab)

**Interfaces:**
- Produces:
  - `KawasanPicker({ value, onChange, negeriList, parlimenList, kadunList, allowAuto = false })` — `value: { negeriId, jenisPr, kawasanType, parlimenId, kadunId, tahun }`, all strings; `'__auto__'` sentinel allowed in `parlimenId`/`kadunId` when `allowAuto`. Also exports `JENIS_PR_OPTIONS`, `TAHUN_OPTIONS`, `unusualCombo(jenisPr, kawasanType)`.
  - `ConfirmDialog({ open, title, children, confirmLabel, onConfirm, onClose, busy })`
  - `UploadTab({ negeriList, parlimenList, kadunList, onUploaded })`
- Consumes (backend Task 4 contract): `POST route('pilihanraya.borang-14.upload')`, multipart `{ fail, negeri_id, jenis_pr, tahun, kawasan_type, kawasan_id?, confirm_create? }`. Responses:
  - `200 { ok: true, form: { id, kawasan_type, kawasan_id, negeri_id, parlimen_id, kadun_id, jenis_pr, tahun, penjuru } }`
  - `200 { requires_confirmation: true, cadangan: { negeri, parlimen: { kod, nama, exists }, kadun: { kod, nama, exists } | null } }`
  - `422 { message }` (negeri tak dikenali / AI gagal / fail > 20MB) — pattern from `AnalisaComparisonController::storeScenario()`.

- [ ] **Step 1:** Create `borang14/KawasanPicker.jsx`:

```jsx
import { CalendarDays, Landmark, ListFilter, MapPin, Vote } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

export const JENIS_PR_OPTIONS = [
    { value: 'pru', label: 'PRU — Pilihanraya Umum' },
    { value: 'prn', label: 'PRN — Pilihanraya Negeri' },
    { value: 'prk', label: 'PRK — Pilihanraya Kecil' },
];

// 1959 = pilihanraya umum pertama Malaysia; +1 tahun hadapan untuk PR akan datang.
export const TAHUN_OPTIONS = (() => {
    const max = new Date().getFullYear() + 1;
    const list = [];
    for (let y = max; y >= 1959; y--) list.push(y);
    return list;
})();

export function unusualCombo(jenisPr, kawasanType) {
    if (jenisPr === 'pru' && kawasanType === 'dun') {
        return 'Gabungan luar biasa: PRU lazimnya kerusi Parlimen. Teruskan hanya jika ini memang betul (cth. PRU serentak PRN).';
    }
    if (jenisPr === 'prn' && kawasanType === 'parlimen') {
        return 'Gabungan luar biasa: PRN lazimnya kerusi DUN. Teruskan hanya jika ini memang betul.';
    }
    return null;
}

const AUTO = '__auto__';

export default function KawasanPicker({ value, onChange, negeriList, parlimenList, kadunList, allowAuto = false }) {
    const { t } = usePilihanrayaTheme();
    const { negeriId, jenisPr, kawasanType, parlimenId, kadunId, tahun } = value;
    const set = (patch) => onChange({ ...value, ...patch });

    const parlimenOptions = negeriId
        ? parlimenList.filter((p) => String(p.negeri_id) === String(negeriId)) : [];
    const kadunOptions = parlimenId && parlimenId !== AUTO
        ? kadunList.filter((k) => String(k.bandar_id) === String(parlimenId)) : [];
    const note = jenisPr && kawasanType ? unusualCombo(jenisPr, kawasanType) : null;

    return (
        <>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div>
                    <label className={t.label}><span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> Negeri</span></label>
                    <select value={negeriId} className={t.input}
                        onChange={(e) => set({ negeriId: e.target.value, parlimenId: '', kadunId: '' })}>
                        <option value="">Pilih Negeri</option>
                        {negeriList.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                    </select>
                </div>
                <div>
                    <label className={t.label}><span className="inline-flex items-center gap-1"><ListFilter className="h-3.5 w-3.5" /> Jenis PR</span></label>
                    <select value={jenisPr} className={t.input} disabled={!negeriId}
                        onChange={(e) => set({ jenisPr: e.target.value })}>
                        <option value="">Pilih Jenis</option>
                        {JENIS_PR_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                </div>
                <div>
                    <label className={t.label}>Peringkat Kawasan</label>
                    <select value={kawasanType} className={t.input} disabled={!jenisPr}
                        onChange={(e) => set({ kawasanType: e.target.value, parlimenId: '', kadunId: '' })}>
                        <option value="">Pilih Peringkat</option>
                        <option value="parlimen">Parlimen</option>
                        <option value="dun">DUN</option>
                    </select>
                </div>
                <div>
                    <label className={t.label}><span className="inline-flex items-center gap-1"><Landmark className="h-3.5 w-3.5" /> Parlimen</span></label>
                    <select value={parlimenId} className={t.input} disabled={!kawasanType}
                        onChange={(e) => set({
                            parlimenId: e.target.value,
                            kadunId: e.target.value === AUTO ? AUTO : '',
                        })}>
                        <option value="">Pilih Parlimen</option>
                        {parlimenOptions.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                        {allowAuto && <option value={AUTO}>— Tiada dalam senarai (kesan dari scoresheet) —</option>}
                    </select>
                </div>
                {kawasanType === 'dun' && (
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Vote className="h-3.5 w-3.5" /> DUN</span></label>
                        <select value={kadunId} className={t.input} disabled={!parlimenId || parlimenId === AUTO}
                            onChange={(e) => set({ kadunId: e.target.value })}>
                            <option value="">Pilih DUN</option>
                            {kadunOptions.map((k) => <option key={k.id} value={k.id}>{k.nama}</option>)}
                            {allowAuto && <option value={AUTO}>— Tiada dalam senarai (kesan dari scoresheet) —</option>}
                        </select>
                    </div>
                )}
                <div>
                    <label className={t.label}><span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" /> Tahun</span></label>
                    <select value={tahun} className={t.input} disabled={!kawasanType}
                        onChange={(e) => set({ tahun: e.target.value })}>
                        <option value="">Pilih Tahun</option>
                        {TAHUN_OPTIONS.map((y) => <option key={y} value={y}>{y}</option>)}
                    </select>
                </div>
            </div>
            {note && <div className={`${t.banner} mt-3`}>{note}</div>}
        </>
    );
}
```

- [ ] **Step 2:** Create `borang14/ConfirmDialog.jsx` (@headlessui/react is available):

```jsx
import { Dialog } from '@headlessui/react';
import { Loader2 } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

export default function ConfirmDialog({ open, title, children, confirmLabel = 'Sahkan', onConfirm, onClose, busy = false }) {
    const { t } = usePilihanrayaTheme();
    return (
        <Dialog open={open} onClose={busy ? () => {} : onClose} className="relative z-50">
            <div className="fixed inset-0 bg-slate-900/40" aria-hidden="true" />
            <div className="fixed inset-0 flex items-center justify-center p-4">
                <Dialog.Panel className="w-full max-w-md rounded-xl bg-white border border-slate-200 shadow-lg p-6">
                    <Dialog.Title className="text-base font-semibold text-slate-900">{title}</Dialog.Title>
                    <div className="mt-3 text-sm text-slate-600">{children}</div>
                    <div className="mt-5 flex justify-end gap-2">
                        <button type="button" onClick={onClose} disabled={busy} className={t.buttonSecondary}>Batal</button>
                        <button type="button" onClick={onConfirm} disabled={busy} className={t.buttonPrimary}>
                            {busy && <Loader2 className="h-4 w-4 animate-spin" />} {confirmLabel}
                        </button>
                    </div>
                </Dialog.Panel>
            </div>
        </Dialog>
    );
}
```

- [ ] **Step 3:** Create `borang14/UploadTab.jsx` — dropzone pattern copied from `analisa/ComparisonBuilder.jsx` `AddScenarioForm` (lines 66-88):

```jsx
import { useRef, useState } from 'react';
import axios from 'axios';
import { FileSpreadsheet, Loader2, Upload, X } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import KawasanPicker from './KawasanPicker';
import ConfirmDialog from './ConfirmDialog';

const AUTO = '__auto__';
const EMPTY = { negeriId: '', jenisPr: '', kawasanType: '', parlimenId: '', kadunId: '', tahun: '' };

export default function UploadTab({ negeriList, parlimenList, kadunList, onUploaded }) {
    const { t } = usePilihanrayaTheme();
    const inputRef = useRef(null);
    const [picker, setPicker] = useState(EMPTY);
    const [file, setFile] = useState(null);
    const [drag, setDrag] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [cadangan, setCadangan] = useState(null); // kawasan-create confirmation payload

    const kawasanId = picker.kawasanType === 'parlimen' ? picker.parlimenId : picker.kadunId;
    const ready = picker.negeriId && picker.jenisPr && picker.kawasanType && kawasanId && picker.tahun && file;

    const post = async (confirmCreate) => {
        const form = new FormData();
        form.append('fail', file);
        form.append('negeri_id', picker.negeriId);
        form.append('jenis_pr', picker.jenisPr);
        form.append('tahun', picker.tahun);
        form.append('kawasan_type', picker.kawasanType);
        if (kawasanId !== AUTO) form.append('kawasan_id', kawasanId);
        if (confirmCreate) form.append('confirm_create', '1');
        return axios.post(route('pilihanraya.borang-14.upload'), form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            timeout: 300000, // extractDetailed reads a 3-page PDF — allow AI time
        });
    };

    const submit = async (confirmCreate = false) => {
        setError(null);
        if (!ready) { setError('Lengkapkan Negeri, Jenis PR, Kawasan, Tahun dan muat naik scoresheet.'); return; }
        setBusy(true);
        try {
            const { data } = await post(confirmCreate);
            if (data.requires_confirmation) { setCadangan(data.cadangan); return; }
            setCadangan(null);
            setFile(null);
            onUploaded(data.form); // shell switches to Keyin, prefilled
        } catch (e) {
            setCadangan(null);
            setError(e.response?.data?.message || 'Muat naik gagal. Cuba semula.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className={`${t.card} space-y-4`}>
            <div>
                <div className={`text-sm font-bold ${t.text}`}>Upload Scoresheet SPR (Borang 760)</div>
                <div className={`text-xs ${t.subtext}`}>AI membaca sheet dan mengisi draf Keyin — fail tidak disimpan ke pelayan.</div>
            </div>

            <KawasanPicker value={picker} onChange={setPicker}
                negeriList={negeriList} parlimenList={parlimenList} kadunList={kadunList} allowAuto />

            <div
                onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
                onDragLeave={() => setDrag(false)}
                onDrop={(e) => { e.preventDefault(); setDrag(false); setFile(e.dataTransfer.files?.[0] || null); }}
                onClick={() => inputRef.current?.click()}
                className={`cursor-pointer rounded-lg border-2 border-dashed px-4 py-6 text-center transition ${
                    drag ? 'border-emerald-500 bg-emerald-500/5' : 'border-slate-200 hover:border-emerald-400'
                }`}
            >
                <input ref={inputRef} type="file" accept=".xlsx,.xls,.csv,.txt,.pdf,.jpg,.jpeg,.png,.webp"
                    className="hidden" onChange={(e) => setFile(e.target.files?.[0] || null)} />
                {file ? (
                    <div className="flex items-center justify-center gap-2 text-sm text-emerald-600">
                        <FileSpreadsheet className="h-4 w-4" /> {file.name}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-1 text-slate-500">
                        <Upload className="h-6 w-6" />
                        <span className="text-sm">Klik atau seret scoresheet (XLSX / XLS / CSV / TXT / PDF / imej, max 20MB)</span>
                        <span className="text-xs text-slate-400">Fail PDF &amp; imej dibaca terus oleh Claude AI</span>
                    </div>
                )}
            </div>

            {error && <div className="flex items-center gap-1.5 text-sm text-red-500"><X className="h-4 w-4" /> {error}</div>}

            <div className="flex items-center gap-3">
                <button type="button" onClick={() => submit(false)} disabled={busy || !ready} className={t.buttonPrimary}>
                    {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />} Muat Naik &amp; Baca
                </button>
                {busy && <span className={`text-sm ${t.subtext}`}>AI sedang membaca scoresheet… (1–3 minit)</span>}
            </div>

            <ConfirmDialog
                open={!!cadangan}
                title="Kawasan belum wujud dalam sistem"
                confirmLabel="Cipta & Teruskan"
                busy={busy}
                onClose={() => setCadangan(null)}
                onConfirm={() => submit(true)}
            >
                {cadangan && (
                    <ul className="space-y-1">
                        <li>Negeri: <strong>{cadangan.negeri}</strong> (sedia ada)</li>
                        {cadangan.parlimen && (
                            <li>Parlimen: <strong>{cadangan.parlimen.nama}</strong> (kod {cadangan.parlimen.kod})
                                {cadangan.parlimen.exists ? ' — sedia ada' : ' — AKAN DICIPTA'}</li>
                        )}
                        {cadangan.kadun && (
                            <li>DUN: <strong>{cadangan.kadun.nama}</strong> (kod {cadangan.kadun.kod})
                                {cadangan.kadun.exists ? ' — sedia ada' : ' — AKAN DICIPTA'}</li>
                        )}
                        <li className="pt-2 text-xs text-slate-500">Rekod baharu ditanda source: scoresheet dan boleh dibetulkan kemudian.</li>
                    </ul>
                )}
            </ConfirmDialog>
        </div>
    );
}
```

- [ ] **Step 4:** Mount in the shell (`Borang14.jsx`) — add import and render inside `<PilihanrayaShell>` below the TabBar:

```jsx
import UploadTab from './borang14/UploadTab';
```
```jsx
{tab === 'upload' && (
    <UploadTab
        negeriList={negeriList}
        parlimenList={parlimenList}
        kadunList={kadunList}
        onUploaded={(form) => openKeyin({
            negeriId: form.negeri_id,
            parlimenId: form.parlimen_id,
            kadunId: form.kadun_id,
            kawasanType: form.kawasan_type,
            jenisPr: form.jenis_pr,
            tahun: form.tahun,
            formId: form.id,
        })}
    />
)}
```

- [ ] **Step 5:** Verify:

```bash
npm run build
```
Expected: `✓ built in …s`.

Manual (after backend Tasks 3-4 merged): upload `Score Sheet Juasseh - PRN N9 - 2023.pdf` with Negeri Sembilan / PRN / DUN `__auto__` / 2023 → confirmation dialog lists `P.129 — AKAN DICIPTA`, `JUASSEH — AKAN DICIPTA` → confirm → lands on Keyin tab prefilled. Uploading with an empty picker keeps the button disabled.

- [ ] **Step 6:** Commit:

```bash
git add -A && git commit -m "Borang 14: Upload Scoresheet tab — cascading kawasan/jenis/tahun picker, dropzone, kawasan-create confirmation"
```

---

### Task 11: Tab "Papar" — cascading filter + history table

**Files:**
- Create: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/borang14/PaparTab.jsx`
- Modify: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/Borang14.jsx` (mount the tab)

**Interfaces:**
- Produces: `PaparTab({ negeriList, parlimenList, kadunList, onOpenKeyin })`.
- Consumes (backend Task 5/6 contract): `GET route('pilihanraya.borang-14.senarai')` params `{ negeri_id (required), parlimen_id?, kadun_id? }` → `{ rows: [{ id, tahun, jenis_pr, kawasan_type, kawasan_id, kawasan_nama, negeri_id, parlimen_id, kadun_id, penjuru, status, source, needs_review, has_snapshot, published_at }] }` (backend applies the spec filter semantics: Negeri only → all Parlimen+DUN records in the state; +Parlimen → that Parlimen's own record AND all its DUNs; +DUN → that DUN only). `POST route('pilihanraya.borang-14.revert')` `{ form_id }` → `{ ok: true }`. `GET route('pilihanraya.borang-14.pdf')` with `{ kawasan_type, kawasan_id, jenis_pr, tahun, penjuru }`.

- [ ] **Step 1:** Create `borang14/PaparTab.jsx`:

```jsx
import { useEffect, useState } from 'react';
import axios from 'axios';
import { Download, Eye, Info, Landmark, Loader2, MapPin, RotateCcw, Vote } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import ConfirmDialog from './ConfirmDialog';

const JENIS_LABEL = { pru: 'PRU', prn: 'PRN', prk: 'PRK' };
const STATUS_BADGE = {
    draft: 'bg-amber-100 text-amber-800',
    published: 'bg-emerald-100 text-emerald-800',
};
const KAWASAN_BADGE = {
    parlimen: 'bg-sky-100 text-sky-800',
    dun: 'bg-violet-100 text-violet-800',
};
const SUMBER_LABEL = { manual: 'Manual', scoresheet: 'Scoresheet' };

export default function PaparTab({ negeriList, parlimenList, kadunList, onOpenKeyin }) {
    const { t } = usePilihanrayaTheme();
    const [negeriId, setNegeriId] = useState('');
    const [parlimenId, setParlimenId] = useState('');
    const [kadunId, setKadunId] = useState('');
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [revertTarget, setRevertTarget] = useState(null);
    const [reverting, setReverting] = useState(false);

    const parlimenOptions = negeriId
        ? parlimenList.filter((p) => String(p.negeri_id) === String(negeriId)) : [];
    const kadunOptions = parlimenId
        ? kadunList.filter((k) => String(k.bandar_id) === String(parlimenId)) : [];

    const load = () => {
        if (!negeriId) { setRows([]); return; }
        setLoading(true); setError(null);
        axios.get(route('pilihanraya.borang-14.senarai'), {
            params: {
                negeri_id: negeriId,
                parlimen_id: parlimenId || undefined,
                kadun_id: kadunId || undefined,
            },
        })
            .then(({ data }) => setRows(
                // Backend already sorts; keep a stable client-side guard: tahun desc, jenis_pr, kawasan_type.
                [...data.rows].sort((a, b) =>
                    b.tahun - a.tahun
                    || a.jenis_pr.localeCompare(b.jenis_pr)
                    || a.kawasan_type.localeCompare(b.kawasan_type)),
            ))
            .catch(() => setError('Gagal memuatkan senarai Borang 14.'))
            .finally(() => setLoading(false));
    };

    useEffect(load, [negeriId, parlimenId, kadunId]); // eslint-disable-line react-hooks/exhaustive-deps

    const openPdf = (r) => {
        window.open(route('pilihanraya.borang-14.pdf', {
            kawasan_type: r.kawasan_type, kawasan_id: r.kawasan_id,
            jenis_pr: r.jenis_pr, tahun: r.tahun, penjuru: r.penjuru,
        }), '_blank');
    };

    const revert = async () => {
        setReverting(true);
        try {
            await axios.post(route('pilihanraya.borang-14.revert'), { form_id: revertTarget.id });
            setRevertTarget(null);
            load();
        } catch (e) {
            setError(e.response?.data?.message || 'Gagal memulihkan snapshot.');
            setRevertTarget(null);
        } finally {
            setReverting(false);
        }
    };

    return (
        <>
            <div className={`${t.cardTight} mb-4`}>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> Negeri</span></label>
                        <select value={negeriId} className={t.input}
                            onChange={(e) => { setNegeriId(e.target.value); setParlimenId(''); setKadunId(''); }}>
                            <option value="">Pilih Negeri</option>
                            {negeriList.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Landmark className="h-3.5 w-3.5" /> Parlimen (pilihan)</span></label>
                        <select value={parlimenId} className={t.input} disabled={!negeriId}
                            onChange={(e) => { setParlimenId(e.target.value); setKadunId(''); }}>
                            <option value="">Semua Parlimen</option>
                            {parlimenOptions.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Vote className="h-3.5 w-3.5" /> DUN (pilihan)</span></label>
                        <select value={kadunId} className={t.input} disabled={!parlimenId}
                            onChange={(e) => setKadunId(e.target.value)}>
                            <option value="">Semua DUN</option>
                            {kadunOptions.map((k) => <option key={k.id} value={k.id}>{k.nama}</option>)}
                        </select>
                    </div>
                </div>
            </div>

            {!negeriId && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih Negeri untuk memaparkan senarai Borang 14. Parlimen sahaja: rekod Parlimen itu dan semua DUN di bawahnya.</span>
                </div>
            )}
            {error && <div className="bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 text-sm mb-4">{error}</div>}
            {loading && (
                <div className={`flex items-center gap-2 ${t.subtext} py-8 justify-center`}>
                    <Loader2 className="h-5 w-5 animate-spin" /> Memuatkan…
                </div>
            )}

            {negeriId && !loading && (
                <div className={`${t.cardTight} overflow-x-auto`}>
                    <table className="min-w-full border-collapse">
                        <thead>
                            <tr>
                                <th className={t.tableHead}>Tahun</th>
                                <th className={t.tableHead}>Jenis PR</th>
                                <th className={t.tableHead}>Kawasan</th>
                                <th className={`${t.tableHead} text-right`}>Penjuru</th>
                                <th className={t.tableHead}>Status</th>
                                <th className={t.tableHead}>Sumber</th>
                                <th className={`${t.tableHead} text-right`}>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr><td colSpan={7} className={`${t.tableCell} text-center py-8 ${t.subtext}`}>Tiada rekod Borang 14 untuk penapis ini.</td></tr>
                            )}
                            {rows.map((r) => (
                                <tr key={r.id} className={t.tableRow}>
                                    <td className={`${t.tableCell} font-semibold`}>{r.tahun}</td>
                                    <td className={t.tableCell}>{JENIS_LABEL[r.jenis_pr] ?? r.jenis_pr}</td>
                                    <td className={t.tableCell}>
                                        <span className={`${t.badge} ${KAWASAN_BADGE[r.kawasan_type]} mr-2`}>{r.kawasan_type.toUpperCase()}</span>
                                        {r.kawasan_nama}
                                        {r.needs_review && <span className={`${t.badge} bg-amber-100 text-amber-800 ml-2`}>Perlu Semakan</span>}
                                    </td>
                                    <td className={`${t.tableCell} text-right`}>{r.penjuru}</td>
                                    <td className={t.tableCell}>
                                        <span className={`${t.badge} ${STATUS_BADGE[r.status]}`}>{r.status === 'draft' ? 'DRAF' : 'DITERBITKAN'}</span>
                                    </td>
                                    <td className={t.tableCell}>{SUMBER_LABEL[r.source] ?? r.source}</td>
                                    <td className={`${t.tableCell} text-right whitespace-nowrap`}>
                                        <button type="button" title="Papar dalam Keyin"
                                            onClick={() => onOpenKeyin(r)}
                                            className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900 mr-3">
                                            <Eye className="h-4 w-4" /> Papar
                                        </button>
                                        <button type="button" title="Muat turun PDF"
                                            onClick={() => openPdf(r)}
                                            className="inline-flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900 mr-3">
                                            <Download className="h-4 w-4" /> PDF
                                        </button>
                                        {r.has_snapshot && (
                                            <button type="button" title="Pulih keadaan sebelum scoresheet menimpa"
                                                onClick={() => setRevertTarget(r)}
                                                className="inline-flex items-center gap-1 text-sm text-rose-600 hover:text-rose-700">
                                                <RotateCcw className="h-4 w-4" /> Revert
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <ConfirmDialog
                open={!!revertTarget}
                title="Pulih dari snapshot?"
                confirmLabel="Revert"
                busy={reverting}
                onClose={() => setRevertTarget(null)}
                onConfirm={revert}
            >
                {revertTarget && (
                    <p>
                        Undi, struktur dan pemetaan parti untuk <strong>{revertTarget.kawasan_nama} · {JENIS_LABEL[revertTarget.jenis_pr]} {revertTarget.tahun}</strong> akan
                        dikembalikan kepada keadaan sebelum scoresheet menimpanya. Tindakan ini tidak boleh dibuat asal.
                    </p>
                )}
            </ConfirmDialog>
        </>
    );
}
```

- [ ] **Step 2:** Mount in `Borang14.jsx`:

```jsx
import PaparTab from './borang14/PaparTab';
```
```jsx
{tab === 'papar' && (
    <PaparTab
        negeriList={negeriList}
        parlimenList={parlimenList}
        kadunList={kadunList}
        onOpenKeyin={(r) => openKeyin({
            negeriId: r.negeri_id,
            parlimenId: r.parlimen_id,
            kadunId: r.kadun_id,
            kawasanType: r.kawasan_type,
            jenisPr: r.jenis_pr,
            tahun: r.tahun,
            formId: r.id,
        })}
    />
)}
```

- [ ] **Step 3:** Verify:

```bash
npm run build
php artisan test --filter=Borang14
```
Expected: `✓ built in …s`; tests `PASS Tests\Feature\Borang14SubmenuTest` (backend suite) with `OK`.

Manual: pick Negeri only → both PARLIMEN- and DUN-badged rows appear, drafts amber + published emerald; add Parlimen → list narrows to that Parlimen + its DUNs; add DUN → single kawasan; Papar jumps to Keyin prefilled; PDF opens; Revert shows confirm dialog then reloads the list.

- [ ] **Step 4:** Commit:

```bash
git add -A && git commit -m "Borang 14: Papar tab — negeri/parlimen/dun cascading filter, status & kawasan badges, PDF/revert actions"
```

---

### Task 12: Tab "Keyin" — jenis/tahun, slot 90/91, party mapping, publish, cross-check, `—` semantics

**Files:**
- Modify: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/borang14/KeyinTab.jsx`
- Modify: `/Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/components/Borang14Form.jsx`

**Interfaces:**
- Consumes (backend contract):
  - `GET route('pilihanraya.borang-14.data')` params `{ kawasan_type, kawasan_id, jenis_pr, tahun, penjuru? }` → `{ reference, hasData, parties: [{slot, keahlian_parti_id, nama, calon}], votes, form: null | { id, status, source, needs_review, crosscheck_issues: string[], penjuru } }`. `reference` keeps its existing shape (`daerah_mengundi[].pusat_mengundi[].saluran[]`, `undi_awal`, `undi_pos`, `source`) but: `saluran[].berdaftar` and `pusat_mengundi[].jumlah_berdaftar` are `null` (not 0) when only scoresheet structure exists; `undi_awal` / `undi_pos` are `null` when absent from the sheet (never fabricate an empty row).
  - `POST route('pilihanraya.borang-14.vote')` body now `{ kawasan_type, kawasan_id, jenis_pr, tahun, penjuru, pusat, saluran, slot, undi }` with slot `in:1..6,90,91`.
  - `POST route('pilihanraya.borang-14.parties')` body `{ kawasan_type, kawasan_id, jenis_pr, tahun, penjuru, parties }`.
  - `POST route('pilihanraya.borang-14.publish')` `{ form_id }` → `{ ok: true }`.
- Produces: `VoteTable`/`UndiAwalPosTable` render two new editable columns — Ditolak (C) = slot 90, Tak Dimasukkan (D) = slot 91 — and `—` (never 0) for Berdaftar / % Turnout / Tak Keluar / % Tak Keluar when `berdaftar == null`. `GrandSummary` totals shape becomes `{ partyTotals, ditolak, tidakDimasukkan, keluar, berdaftar, berdaftarKnown }`.

- [ ] **Step 1:** In `Borang14Form.jsx` add the dash helper and rework `toBlocks` to preserve `null`:

```js
export const fmtOrDash = (n) => (n == null ? '—' : fmt(n));
```
```js
export function toBlocks(reference) {
    if (!reference) return [];
    return reference.daerah_mengundi.flatMap((dm) =>
        dm.pusat_mengundi.map((p) => {
            const known = p.saluran.some((x) => x.berdaftar != null) || p.jumlah_berdaftar != null;
            return {
                dm: dm.nama,
                pusat: p.nama,
                // null (not 0) when the source is a scoresheet — sheet has no berdaftar.
                berdaftar: known
                    ? (p.jumlah_berdaftar ?? p.saluran.reduce((s, x) => s + (x.berdaftar || 0), 0))
                    : null,
                saluran: p.saluran,
            };
        }),
    );
}
```

- [ ] **Step 2:** Rework `VoteTable` row math for slots 90/91 and `—` semantics:

```jsx
const rows = block.saluran.map((s) => {
    const slots = Array.from({ length: nParties }, (_, i) =>
        votes[cellKey(block.pusat, String(s.no), i + 1)] ?? 0);
    const ditolak = votes[cellKey(block.pusat, String(s.no), 90)] ?? 0;
    const tidakMasuk = votes[cellKey(block.pusat, String(s.no), 91)] ?? 0;
    const undian = slots.reduce((a, b) => a + b, 0);          // JUMLAH UNDIAN = Σ undi calon
    const keluar = undian + ditolak + tidakMasuk;             // (A) = Σ undi + (C) + (D)
    return {
        no: s.no,
        berdaftar: s.berdaftar ?? null,                        // null → render '—', never 0
        slots, ditolak, tidakMasuk, undian, keluar,
        status: leadStatus(slots),
    };
});
```

Header row gains, after the party columns:

```jsx
<th className={`${t.tableHead} whitespace-nowrap text-right`}>Ditolak (C)</th>
<th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Dimasukkan (D)</th>
<th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Undian</th>
```

Body cells after the party cells (each C/D cell reuses the Task 8 pattern — `SaveStatusDot` + `EditableCell`, no `LeadSquare`):

```jsx
{[{ slot: 90, v: r.ditolak }, { slot: 91, v: r.tidakMasuk }].map(({ slot, v }) => {
    const key = cellKey(block.pusat, String(r.no), slot);
    return (
        <td key={slot} className="px-2 py-1">
            <div className="flex items-center justify-end gap-1.5">
                <SaveStatusDot status={cellStatus[key]} />
                <EditableCell
                    value={v}
                    invalid={cellStatus[key] === 'error'}
                    max={r.berdaftar != null ? Math.max(0, r.berdaftar - (r.keluar - v)) : null}
                    onCommit={(undi) => onSave(block.pusat, String(r.no), slot, undi)}
                />
            </div>
        </td>
    );
})}
<td className={`${t.tableCell} text-right`}>{fmt(r.undian)}</td>
```

Party-cell `max` changes from `r.berdaftar > 0 ? …` to `r.berdaftar != null ? Math.max(0, r.berdaftar - (r.keluar - v)) : null` (keluar now includes C+D). Reference/derived cells become:

```jsx
<td className={`${t.tableCell} text-right font-semibold`}>{fmt(r.keluar)}</td>
<td className={`${t.tableCell} text-right`}>{fmtOrDash(r.berdaftar)}</td>
<td className={`${t.tableCell} text-right`}>{r.berdaftar == null ? '—' : pct(r.keluar, r.berdaftar)}</td>
<td className={`${t.tableCell} text-right`}>{r.berdaftar == null ? '—' : fmt(Math.max(0, r.berdaftar - r.keluar))}</td>
<td className={`${t.tableCell} text-right`}>{r.berdaftar == null ? '—' : pct(Math.max(0, r.berdaftar - r.keluar), r.berdaftar)}</td>
```

Totals row: `totals.ditolak`, `totals.tidakMasuk`, `totals.undian` sums; `totals.berdaftarKnown = rows.some((r) => r.berdaftar != null)`; Berdaftar/%/Tak Keluar cells use the same `berdaftarKnown ? … : '—'` guard. Apply the identical column set to `UndiAwalPosTable` (its rows now carry `berdaftar: null` when unknown; drop the `?? 0` coercions).

- [ ] **Step 3:** `GrandSummary` — accept the extended totals and stay honest without berdaftar:

```jsx
<div className={`rounded-xl border ${t.border} bg-slate-50 p-4`}>
    <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext}`}>Ditolak (C) / Tak Dimasukkan (D)</div>
    <div className={`text-2xl font-bold mt-1 ${t.text}`}>{fmt(totals.ditolak)} / {fmt(totals.tidakDimasukkan)}</div>
    <div className={`text-xs ${t.subtext} mt-0.5`}>Kertas undi bermasalah</div>
</div>
```

and the turnout tile:

```jsx
<div className="text-2xl font-bold mt-1 text-sky-800">
    {totals.berdaftarKnown ? pct(totals.keluar, totals.berdaftar) : '—'}
</div>
<div className="text-xs text-sky-700/80 mt-0.5">
    {totals.berdaftarKnown ? `${fmt(totals.keluar)} / ${fmt(totals.berdaftar)} berdaftar` : 'Berdaftar tiada dalam scoresheet — perlukan rujukan SPR'}
</div>
```

- [ ] **Step 4:** `KeyinTab.jsx` — replace the geography row with the shared picker and add jenis/tahun state:

```jsx
import KawasanPicker, { JENIS_PR_OPTIONS } from './KawasanPicker';
```
```jsx
const [picker, setPicker] = useState({ negeriId: '', jenisPr: '', kawasanType: '', parlimenId: '', kadunId: '', tahun: '' });
const { negeriId, jenisPr, kawasanType, parlimenId, kadunId, tahun } = picker;
const kawasanId = kawasanType === 'parlimen' ? parlimenId : kadunId;
const geographyComplete = Boolean(negeriId && jenisPr && kawasanType && kawasanId && tahun);
const [form, setForm] = useState(null);
const [publishing, setPublishing] = useState(false);
const [publishedOk, setPublishedOk] = useState(false);
```

Prefill effect (replaces the Task 9 version):

```jsx
useEffect(() => {
    if (!prefill) return;
    setPicker({
        negeriId: String(prefill.negeriId ?? ''),
        jenisPr: prefill.jenisPr ?? '',
        kawasanType: prefill.kawasanType ?? 'dun',
        parlimenId: String(prefill.parlimenId ?? ''),
        kadunId: String(prefill.kadunId ?? ''),
        tahun: String(prefill.tahun ?? ''),
    });
}, [prefill?.nonce]); // eslint-disable-line react-hooks/exhaustive-deps
```

Data fetch effect (replaces the `kadunId`-only version; syncs penjuru from a loaded draft so a scoresheet upload lands ready-to-edit):

```jsx
useEffect(() => {
    if (!geographyComplete) { setReference(null); setHasData(true); setVotes({}); setForm(null); return; }
    let cancelled = false;
    setLoading(true); setSelectedPusat(''); setPublishedOk(false);
    axios.get(route('pilihanraya.borang-14.data'), {
        params: { kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun, penjuru: penjuru || undefined },
    })
        .then(({ data }) => {
            if (cancelled) return;
            setReference(data.reference);
            setHasData(data.hasData);
            setVotes(data.votes || {});
            setForm(data.form || null);
            if (data.parties?.length) {
                setParties(data.parties);
                setPenjuru(String(data.form?.penjuru ?? data.parties.length));
            }
        })
        .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
}, [geographyComplete, kawasanType, kawasanId, jenisPr, tahun, penjuru]);
```

Render `<KawasanPicker value={picker} onChange={setPicker} negeriList={negeriList} parlimenList={parlimenList} kadunList={kadunList} />` where the three geography selects were (penjuru + party row 2 stays). `isBulohKasap` becomes `kawasanType === 'dun' && Number(kadunId) === BULOH_KASAP_KADUN_ID`.

- [ ] **Step 5:** Conditional Undi Awal/Pos rows — never fabricate an absent row:

```jsx
const undiAwalPosRows = useMemo(() => {
    const awal = reference?.undi_awal;
    const pos = reference?.undi_pos;
    if (isBulohKasap) {
        return [{ label: 'UNDI AWAL & POS', berdaftar: (awal?.berdaftar ?? 0) + (pos?.berdaftar ?? 0) }];
    }
    const rows = [];
    if (awal) rows.push({ label: 'UNDI AWAL', berdaftar: awal.berdaftar ?? null });
    if (pos) rows.push({ label: 'UNDI POS', berdaftar: pos.berdaftar ?? null });
    return rows;
}, [reference, isBulohKasap]);
```

Wrap `<UndiAwalPosTable …>` in `{undiAwalPosRows.length > 0 && (…)}`. Update the `summary` memo to also add `cellKey(..., 90)` and `cellKey(..., 91)` values into `ditolak`/`tidakDimasukkan` accumulators, compute `keluar = Σ partyTotals + ditolak + tidakDimasukkan`, and set `berdaftarKnown = blocks.some((b) => b.berdaftar != null) || undiAwalPosRows.some((r) => r.berdaftar != null)`.

- [ ] **Step 6:** Party-mapping with candidate names + payload updates. `onPickParty` must preserve extra fields (`calon`):

```jsx
const onPickParty = (index, partiId) => {
    const parti = partiList.find((p) => String(p.id) === String(partiId));
    const next = parties.map((p, i) => (i === index
        ? { ...p, slot: i + 1, keahlian_parti_id: parti ? parti.id : '', nama: parti ? parti.nama : (p.calon ?? '') }
        : p));
    setParties(next);
    persistParties(next);
};
```

Under each Parti select, show the scoresheet candidate so the user maps logo-identified people, not guesses:

```jsx
{p.calon && (
    <div className={`text-xs ${t.subtext} mt-0.5`}>Calon: {p.calon}{!p.keahlian_parti_id && ' — belum dipetakan'}</div>
)}
```

`persistParties` and `saveVote` swap `kadun_id` for the polymorphic keys:

```jsx
axios.post(route('pilihanraya.borang-14.parties'), {
    kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun: Number(tahun),
    penjuru: Number(penjuru), parties: next,
}).catch(() => {});
```
(and identically in `saveVote`'s POST body plus `pusat, saluran, slot, undi`; `saveVote` deps become `[kawasanType, kawasanId, jenisPr, tahun, penjuru]`). `downloadPdf` passes `{ kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun, penjuru: Number(penjuru), parti: partyNames }`.

- [ ] **Step 7:** Banners + Save (publish) button. Above `<GrandSummary>`:

```jsx
{form?.needs_review && (
    <div className={`${t.banner} flex items-center gap-2 mb-4`}>
        <Info className="h-4 w-4 shrink-0" />
        <span>Scoresheet ini perlu semakan: sahkan pemetaan parti bagi setiap calon (dan saluran teragregat, jika ada) sebelum publish.</span>
    </div>
)}
{form?.crosscheck_issues?.length > 0 && (
    <div className="bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 text-sm mb-4">
        <div className="font-semibold mb-1">Silang-semak tidak seimbang — (A) ≠ Σ undi + (C) + (D) pada baris berikut:</div>
        <ul className="list-disc pl-5 space-y-0.5">
            {form.crosscheck_issues.map((msg, i) => <li key={i}>{msg}</li>)}
        </ul>
    </div>
)}
{publishedOk && (
    <div className="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-lg px-4 py-3 text-sm mb-4">
        Borang 14 diterbitkan — rekod kini kelihatan dalam tab Papar.
    </div>
)}
```

Header action row (next to the PDF button):

```jsx
const allPartiesMapped = parties.length > 0 && parties.every((p) => p.keahlian_parti_id);
const anySaving = Object.values(cellStatus).some((s) => s === 'saving' || s === 'error');

const publish = async () => {
    if (!form?.id) return;
    setPublishing(true);
    try {
        await axios.post(route('pilihanraya.borang-14.publish'), { form_id: form.id });
        setForm((f) => ({ ...f, status: 'published' }));
        setPublishedOk(true);
    } catch (e) {
        alert(e.response?.data?.message || 'Gagal menerbitkan Borang 14.');
    } finally {
        setPublishing(false);
    }
};
```
```jsx
<div className="flex items-center gap-2">
    {form?.status === 'published' && <span className={`${t.badge} bg-emerald-100 text-emerald-800`}>DITERBITKAN</span>}
    {form?.status === 'draft' && <span className={`${t.badge} bg-amber-100 text-amber-800`}>DRAF</span>}
    <button type="button" onClick={downloadPdf} className={t.buttonSecondary}>
        <Download className="h-4 w-4" /> Muat Turun PDF
    </button>
    <button
        type="button"
        onClick={publish}
        disabled={!form?.id || !allPartiesMapped || anySaving || publishing || form?.status === 'published'}
        title={!allPartiesMapped ? 'Petakan setiap calon kepada parti dahulu' : anySaving ? 'Tunggu autosave selesai / betulkan sel merah' : undefined}
        className={t.buttonPrimary}
    >
        {publishing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Save &amp; Terbit
    </button>
</div>
```
(import `Save` from `lucide-react`; the PDF button drops to `t.buttonSecondary` so publish is the primary action.)

- [ ] **Step 8:** Verify:

```bash
npm run build
php artisan test --filter=Borang14
```
Expected: `✓ built in …s`; `PASS … Borang14` suites, `OK`.

Manual acceptance (Juasseh flow, mirrors spec Verification): upload the Juasseh PDF → Keyin opens with 40 rows across 11 DM incl. one `UNDI POS` row (no fabricated `UNDI AWAL`), Ditolak/Tak Dimasukkan columns populated (row Undi Pos: 98/73, C=18, D=14), Berdaftar / % Turnout / Tak Keluar all show `—` (never 0), publish disabled until both calon mapped via the Parti dropdowns, then Save & Terbit → emerald banner → record shows DITERBITKAN in Papar. Regression: a manual DUN with reference berdaftar still shows numeric turnout, and Buloh Kasap (kadun 41) still shows one combined `UNDI AWAL & POS` row.

- [ ] **Step 9:** Commit:

```bash
git add -A && git commit -m "Borang 14: Keyin tab — jenis PR/tahun, slot 90/91 columns, party mapping gate, publish, cross-check banner, honest '—' when berdaftar unknown"
```

---

### Critical Files for Implementation
- /Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/Borang14.jsx
- /Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/components/Borang14Form.jsx (new)
- /Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/borang14/KeyinTab.jsx (new)
- /Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/components/EditableCell.jsx
- /Volumes/SSD/Projects/sisda/resources/js/Pages/Pilihanraya/analisa/ComparisonBuilder.jsx (dropzone/upload reference pattern)

---

### Task 13: Ujian penerimaan end-to-end + audit UI

Spec menetapkan audit UI selepas page siap, dan sheet Juasseh sebagai ujian
penerimaan utama. Task ini menutup kedua-duanya.

**Files:**
- Test: `tests/Feature/Borang14AcceptanceTest.php`

**Interfaces:**
- Consumes: semua yang dihasilkan Task 1-12.

- [ ] **Step 1: Tulis ujian penerimaan terhadap angka sheet sebenar**

```php
<?php
// tests/Feature/Borang14AcceptanceTest.php
namespace Tests\Feature;

use App\Services\Pilihanraya\ScoresheetExtractor;
use Tests\TestCase;

class Borang14AcceptanceTest extends TestCase
{
    public function test_juasseh_figures_match_the_printed_sheet(): void
    {
        $d = json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true);

        // Angka disahkan manual dari 'Score Sheet Juasseh - PRN N9 - 2023.pdf'
        $this->assertSame(13408, $d['jumlah_pemilih']);
        $this->assertSame(9122,  $d['jumlah']['a']);
        $this->assertSame([4471, 4549], $d['jumlah']['undi']);
        $this->assertSame(9020,  $d['jumlah']['jumlah_undian']);
        $this->assertSame(87,    $d['jumlah']['ditolak']);
        $this->assertSame(15,    $d['jumlah']['tidak_dimasukkan']);

        // Silang-semak: (A) == undi calon + (C) + (D)
        $this->assertSame(
            $d['jumlah']['a'],
            array_sum($d['jumlah']['undi']) + $d['jumlah']['ditolak'] + $d['jumlah']['tidak_dimasukkan'],
        );

        $this->assertSame([], ScoresheetExtractor::validateBalance($d));
    }
}
```

- [ ] **Step 2: Jalankan ujian, sahkan LULUS**

Run: `php artisan test --filter=Borang14AcceptanceTest`
Expected: PASS — 1 test

- [ ] **Step 3: Build frontend dan sahkan tiada ralat**

Run: `npm run build`
Expected: build berjaya, tiada ralat import

- [ ] **Step 4: Manual end-to-end dengan PDF Juasseh sebenar**

Guna fail user di OneDrive. Sahkan setiap perkara ini:

1. Upload → dialog pengesahan cipta kawasan muncul (Juasseh tiada dalam sistem)
2. Sahkan → draf tercipta, lompat ke tab Keyin
3. Tab Keyin papar **40 baris** (39 Undi Biasa + 1 Undi Pos) merentas **11 DM**
4. Baris `UNDI POS` papar `A=203`, undi `98 / 73`, `C=18`, `D=14`
5. `% Turnout`, `Berdaftar`, `Tak Keluar` papar **`—`**, bukan `0`
   (Juasseh tiada reference `berdaftar` — ini yang betul, bukan bug)
6. Banner `needs_review` muncul (calon kedua `yakin: false`)
7. Petakan dua calon ke `keahlian_parti` → banner hilang
8. Save → status `published`
9. Tab Papar → rekod muncul dengan badge `DUN` + `PRN` + `2023`
10. PDF export berjaya

- [ ] **Step 5: Audit UI dengan ui-ux-suite**

Run: `/a11y` kemudian `/colors` pada tiga tab.
Penting kerana jadual undi padat dan dibaca pantas semasa mengira undi —
kontras rendah pada sel adalah risiko sebenar, bukan hiasan.
Betulkan apa-apa isu kontras AA yang dilaporkan.

- [ ] **Step 6: Jalankan suite penuh**

Run: `php artisan test`
Expected: PASS semua — tiada regresi pada Analisa/Scoreboard/Simulasi

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/Borang14AcceptanceTest.php
git commit -m "Borang 14: ujian penerimaan terhadap angka sheet Juasseh sebenar"
```
