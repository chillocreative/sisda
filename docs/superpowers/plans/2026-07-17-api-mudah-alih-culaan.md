# API Mudah Alih Culaan — Implementation Plan (Bahagian 1 / 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Laravel JSON API that the SISDA Flutter app needs to search voters and submit Hasil Culaan safely from the field, including offline-retry (idempotency) support.

**Architecture:** Extract the role-scoping and payload-normalisation logic currently buried in `ReportsController` into two reusable services, then build six mobile endpoints on top of them. The web controller is refactored to call the same services, so web and mobile can never drift. Every response passes through the existing `VoterDataMasker`.

**Tech Stack:** Laravel 12, Sanctum (token auth, already wired), PHPUnit, MySQL (prod) / SQLite (CI).

**Spec:** `docs/superpowers/specs/2026-07-17-aplikasi-mudah-alih-user-design.md`

**Scope note:** This is plan 1 of 2. Plan 2 (the Flutter client) depends on every endpoint here and will be written after this lands. This plan produces working, independently testable software on its own: a tested JSON API.

## Global Constraints

- **All user-facing strings are Bahasa Melayu.** No i18n layer — hardcode inline, match surrounding copy.
- **Response envelope is fixed** by the existing `MobileAuthController`: success is `{"success": true, ...}`; failure is `{"success": false, "errors": {"field": ["mesej BM"]}}`. Every new endpoint follows it exactly.
- **Masking is mandatory.** Every voter payload returned to the client goes through `VoterDataMasker::mask($record, $viewer)`. The relation `submittedBy` MUST be eager-loaded or `isLocked()` silently returns `false` and leaks PII.
- **Unknown is not zero.** Absent numeric data stays `null`. Never coerce `null` → `0`.
- **Migrations run on every deploy** (`migrate --force`) against live Borang 14 / voter data. Reshape in place; never `Schema::drop`+recreate.
- **CI runs SQLite; production is MySQL.** No raw `ALTER ... MODIFY`, no `REGEXP`, no `TIMESTAMPDIFF` in anything that must be tested.
- **Test baseline is 20 failed / 127 passed.** Caused by `UserFactory` not setting the NOT NULL `telephone` column. Do not "fix" `UserFactory` — out of scope, and changing it perturbs the baseline. Every test in this plan passes `telephone`, `role`, `status`, `bandar_id`, `kadun_id` explicitly.
- **Wrap multi-row writes in a transaction.** The Culaan create path fans out through `VoterSyncService`; CLAUDE.md flags the HTTP layer as transaction-free. New code does not repeat that.
- **Route placement:** mobile routes live in the existing `Route::prefix('api/mobile')` group in `routes/web.php:519`. There is no `routes/api.php` in this project.

## Spec correction adopted here

The spec lists `POST /api/mobile/token/refresh` (13 endpoints). **It is dropped — YAGNI.** `config/sanctum.php:50` sets `'expiration' => null`, so tokens never expire; there is nothing to refresh. A 401 can only mean the token was revoked (`MobileAuthController::login` calls `$user->tokens()->delete()` on every login, so logging in on a second device kills the first). A revoked token cannot be refreshed — only re-login fixes it, which the client already handles. Final count: **12 endpoints (6 existing + 6 new)**.

## File Structure

| File | Responsibility |
|---|---|
| `app/Services/CulaanPayloadNormalizer.php` | **Create.** Turns validated Culaan input (checkbox arrays + `*_lain` free-text escapes) into flat DB column values. Pure function, no I/O. |
| `app/Services/VoterScopeService.php` | **Create.** Applies role-based row visibility to a query. Pure query-builder logic. |
| `app/Http/Controllers/Api/MobileVoterController.php` | **Create.** `search`, `show`. Read-only, masked. |
| `app/Http/Controllers/Api/MobileCulaanController.php` | **Create.** `store`, `options`, `mine`. |
| `app/Http/Requests/StoreMobileCulaanRequest.php` | **Create.** Holds the conditional `has_sumbangan` validation rules in one place. |
| `database/migrations/2026_07_18_000001_add_idempotency_key_to_hasil_culaan.php` | **Create.** Nullable unique column. Additive only. |
| `database/factories/HasilCulaanFactory.php` | **Create.** Test data. No factory exists today. |
| `database/factories/DataPengundiFactory.php` | **Create.** Test data. |
| `app/Http/Controllers/ReportsController.php` | **Modify.** `hasilCulaanStore` and the two index methods delegate to the new services instead of inlining the logic. |
| `routes/web.php:519-536` | **Modify.** Register 6 new routes inside the existing `api/mobile` group. |

---

### Task 1: Extract `CulaanPayloadNormalizer`

`ReportsController::hasilCulaanStore` contains ~70 lines (`ReportsController.php:460-525`) that flatten checkbox arrays into comma-separated strings and substitute `*_lain` free-text for "Lain-lain" entries. It is the same block copy-pasted six times with **inconsistent matching**: `pemilik_rumah` and `jenis_pekerjaan` use exact `=== 'Lain-lain'`, while `jenis_sumbangan`, `tujuan_sumbangan`, `bantuan_lain` and `perkeso_bantuan` use fuzzy `stripos($item, 'lain') !== false`.

The mobile endpoint must write **byte-identical** rows to the web. Reimplementing this guarantees drift, so extract it first and make the web call it. This task is a pure refactor: behaviour must not change, including the inconsistency (preserving it is deliberate — changing matching rules would silently rewrite how existing user input is stored, which is a separate decision).

**Files:**
- Create: `app/Services/CulaanPayloadNormalizer.php`
- Create: `tests/Unit/CulaanPayloadNormalizerTest.php`
- Modify: `app/Http/Controllers/ReportsController.php:460-525`

**Interfaces:**
- Consumes: nothing.
- Produces: `CulaanPayloadNormalizer::normalize(array $validated): array` — accepts the output of `$request->validate(...)`, returns an array safe to pass to `HasilCulaan::create()`. Removes every `*_lain` key. Used by Task 5.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/CulaanPayloadNormalizerTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\CulaanPayloadNormalizer;
use PHPUnit\Framework\TestCase;

class CulaanPayloadNormalizerTest extends TestCase
{
    public function test_flattens_checkbox_array_to_comma_separated_string(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'jenis_sumbangan' => ['Tunai', 'Barangan'],
        ]);

        $this->assertSame('Tunai, Barangan', $out['jenis_sumbangan']);
    }

    public function test_substitutes_lain_free_text_and_drops_the_lain_key(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'jenis_sumbangan' => ['Tunai', 'Lain-lain'],
            'jenis_sumbangan_lain' => 'Baucar buku',
        ]);

        $this->assertSame('Tunai, Baucar buku', $out['jenis_sumbangan']);
        $this->assertArrayNotHasKey('jenis_sumbangan_lain', $out);
    }

    public function test_jenis_pekerjaan_uses_exact_match_not_fuzzy(): void
    {
        // 'Pelbagai lain' contains 'lain' but is NOT the Lain-lain option.
        // jenis_pekerjaan matches exactly, so it must survive untouched.
        $out = CulaanPayloadNormalizer::normalize([
            'jenis_pekerjaan' => ['Pelbagai lain'],
            'jenis_pekerjaan_lain' => 'IGNORED',
        ]);

        $this->assertSame('Pelbagai lain', $out['jenis_pekerjaan']);
    }

    public function test_pemilik_rumah_lain_replaces_value(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'pemilik_rumah' => 'Lain-lain',
            'pemilik_rumah_lain' => 'Rumah pusaka',
        ]);

        $this->assertSame('Rumah pusaka', $out['pemilik_rumah']);
        $this->assertArrayNotHasKey('pemilik_rumah_lain', $out);
    }

    public function test_lain_option_dropped_when_free_text_is_empty(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'jenis_sumbangan' => ['Tunai', 'Lain-lain'],
            'jenis_sumbangan_lain' => '',
        ]);

        // Empty free-text means the Lain-lain entry stays as-is (current behaviour).
        $this->assertSame('Tunai, Lain-lain', $out['jenis_sumbangan']);
    }

    public function test_zpp_jenis_bantuan_flattens_without_lain_handling(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'zpp_jenis_bantuan' => ['A', 'B'],
        ]);

        $this->assertSame('A, B', $out['zpp_jenis_bantuan']);
    }

    public function test_passes_through_unrelated_scalar_fields(): void
    {
        $out = CulaanPayloadNormalizer::normalize(['nama' => 'Ahmad', 'umur' => 40]);

        $this->assertSame('Ahmad', $out['nama']);
        $this->assertSame(40, $out['umur']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/CulaanPayloadNormalizerTest.php`
Expected: FAIL — `Class "App\Services\CulaanPayloadNormalizer" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Services/CulaanPayloadNormalizer.php`:

```php
<?php

namespace App\Services;

/**
 * Flattens Culaan checkbox arrays into the comma-separated strings the
 * hasil_culaan columns actually store, substituting the paired *_lain
 * free-text value wherever the user picked a "Lain-lain" option.
 *
 * Extracted verbatim from ReportsController::hasilCulaanStore so the web
 * form and the mobile API produce identical rows. Behaviour is preserved
 * exactly, including the deliberate inconsistency below.
 *
 * NOTE ON MATCHING: jenis_pekerjaan and pemilik_rumah match the literal
 * string 'Lain-lain'. The other four match any option merely CONTAINING
 * 'lain' (case-insensitive). This is how the live system already stores
 * user input; normalising the rule would retroactively change the meaning
 * of existing submissions, so it stays until that is decided separately.
 */
class CulaanPayloadNormalizer
{
    /** Fields matching any option containing 'lain', case-insensitive. */
    private const FUZZY_LAIN_FIELDS = [
        'jenis_sumbangan',
        'tujuan_sumbangan',
        'bantuan_lain',
        'perkeso_bantuan',
    ];

    public static function normalize(array $validated): array
    {
        // pemilik_rumah: scalar, exact match.
        if (($validated['pemilik_rumah'] ?? null) === 'Lain-lain' && ! empty($validated['pemilik_rumah_lain'])) {
            $validated['pemilik_rumah'] = $validated['pemilik_rumah_lain'];
        }
        unset($validated['pemilik_rumah_lain']);

        // jenis_pekerjaan: array, exact match.
        if (isset($validated['jenis_pekerjaan']) && is_array($validated['jenis_pekerjaan'])) {
            $items = $validated['jenis_pekerjaan'];
            if (in_array('Lain-lain', $items, true) && ! empty($validated['jenis_pekerjaan_lain'])) {
                $items = array_filter($items, fn ($i) => $i !== 'Lain-lain');
                $items[] = $validated['jenis_pekerjaan_lain'];
            }
            $validated['jenis_pekerjaan'] = implode(', ', $items);
            $validated['jenis_pekerjaan_lain'] = null;
        }

        // Four fields sharing the fuzzy 'lain' rule.
        foreach (self::FUZZY_LAIN_FIELDS as $field) {
            if (! isset($validated[$field]) || ! is_array($validated[$field])) {
                continue;
            }
            $items = $validated[$field];
            $lainKey = $field.'_lain';
            $hasLain = count(array_filter($items, fn ($i) => stripos($i, 'lain') !== false)) > 0;

            if ($hasLain && ! empty($validated[$lainKey])) {
                $items = array_filter($items, fn ($i) => stripos($i, 'lain') === false);
                $items[] = $validated[$lainKey];
            }
            $validated[$field] = implode(', ', $items);
        }

        // zpp_jenis_bantuan: plain flatten, no Lain-lain handling.
        if (isset($validated['zpp_jenis_bantuan']) && is_array($validated['zpp_jenis_bantuan'])) {
            $validated['zpp_jenis_bantuan'] = implode(', ', $validated['zpp_jenis_bantuan']);
        }

        unset(
            $validated['jenis_sumbangan_lain'],
            $validated['tujuan_sumbangan_lain'],
            $validated['bantuan_lain_lain'],
            $validated['perkeso_bantuan_lain'],
        );

        return $validated;
    }
}
```

> **Note on `bantuan_lain`:** its free-text key is `bantuan_lain_lain` (field name + `_lain`), so the generic `$field.'_lain'` rule above produces the correct key. Verified against `ReportsController.php:503`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/CulaanPayloadNormalizerTest.php`
Expected: PASS — 7 tests.

- [ ] **Step 5: Make `ReportsController` use the service**

In `app/Http/Controllers/ReportsController.php`, delete the block from the `pemilik_rumah` handling through the `unset(...)` of the `_lain` fields (`ReportsController.php:460-525`) and replace it with:

```php
$validated = \App\Services\CulaanPayloadNormalizer::normalize($validated);
```

Leave the file-upload block (`if ($request->hasFile('kad_pengenalan'))`) and `$validated['submitted_by'] = auth()->id();` exactly where they are — they are I/O and auth, not normalisation, and belong in the controller.

- [ ] **Step 6: Verify the refactor changed no behaviour**

Run: `php artisan test`
Expected: **20 failed / 127 passed** — the unchanged baseline, plus the 7 new unit tests passing (so 134 passed). If the failure count is above 20, the refactor broke something. Stop and diff against the original block rather than pressing on.

- [ ] **Step 7: Commit**

```bash
git add app/Services/CulaanPayloadNormalizer.php tests/Unit/CulaanPayloadNormalizerTest.php app/Http/Controllers/ReportsController.php
git commit -m "Refactor: ekstrak CulaanPayloadNormalizer dari ReportsController

Blok ~70 baris yang meratakan checkbox array dan menggantikan teks
Lain-lain diulang enam kali dengan padanan tidak konsisten (dua guna
padanan tepat, empat guna stripos). Ekstrak supaya API mudah alih
menulis baris yang identik dengan web, bukan reimplementasi.

Tingkah laku dikekalkan sepenuhnya, termasuk ketidakkonsistenan itu."
```

---

### Task 2: Extract `VoterScopeService`

The role-based row filter is written out three times — `ReportsController.php:54-66` (Hasil Culaan index), `:1002-1015` (Data Pengundi index), and again in the export path. All three are the same rule over tables that share the `kadun` / `bandar` / `submitted_by` column shape:

- `user` / `super_user` → rows in **their Kadun** OR rows **they submitted**
- `admin` → rows in **their Bandar (Parlimen)** OR rows **they submitted**
- `super_admin` → everything

The `?? '__none__'` fallbacks matter: a user with no Kadun assigned must match **nothing**, not everything. Preserve them exactly.

**Files:**
- Create: `app/Services/VoterScopeService.php`
- Create: `tests/Feature/VoterScopeServiceTest.php`
- Modify: `app/Http/Controllers/ReportsController.php:54-66`, `:1002-1015`

**Interfaces:**
- Consumes: nothing.
- Produces: `VoterScopeService::apply(Builder $query, User $user): Builder` — mutates and returns the query. Used by Tasks 4 and 7.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/VoterScopeServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\VoterScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;
    private Bandar $bandar;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'JOHOR']);
        $this->bandar = Bandar::create(['nama' => 'SEGAMAT', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'BULOH KASAP', 'bandar_id' => $this->bandar->id]);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
    }

    public function test_user_sees_records_in_their_kadun(): void
    {
        $user = $this->makeUser('user');
        HasilCulaan::factory()->create(['kadun' => 'BULOH KASAP', 'bandar' => 'SEGAMAT']);
        HasilCulaan::factory()->create(['kadun' => 'JEMENTAH', 'bandar' => 'SEGAMAT']);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $user)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('BULOH KASAP', $rows->first()->kadun);
    }

    public function test_user_also_sees_records_they_submitted_outside_their_kadun(): void
    {
        $user = $this->makeUser('user');
        HasilCulaan::factory()->create(['kadun' => 'JEMENTAH', 'submitted_by' => $user->id]);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $user)->get();

        $this->assertCount(1, $rows);
    }

    public function test_admin_is_scoped_to_their_bandar_not_their_kadun(): void
    {
        $admin = $this->makeUser('admin');
        HasilCulaan::factory()->create(['kadun' => 'JEMENTAH', 'bandar' => 'SEGAMAT']);
        HasilCulaan::factory()->create(['kadun' => 'LABIS', 'bandar' => 'MUAR']);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $admin)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('SEGAMAT', $rows->first()->bandar);
    }

    public function test_super_admin_sees_everything(): void
    {
        $su = $this->makeUser('super_admin');
        HasilCulaan::factory()->create(['kadun' => 'JEMENTAH', 'bandar' => 'MUAR']);
        HasilCulaan::factory()->create(['kadun' => 'LABIS', 'bandar' => 'JOHOR BAHRU']);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $su)->get();

        $this->assertCount(2, $rows);
    }

    public function test_user_without_a_kadun_sees_nothing_rather_than_everything(): void
    {
        $user = $this->makeUser('user');
        $user->update(['kadun_id' => null]);
        $user->refresh();

        HasilCulaan::factory()->create(['kadun' => 'BULOH KASAP']);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $user->fresh())->get();

        $this->assertCount(0, $rows, 'A user with no Kadun must match nothing, not leak every record.');
    }
}
```

- [ ] **Step 2: Create the factories the test needs**

No `HasilCulaanFactory` exists. Create `database/factories/HasilCulaanFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\HasilCulaan;
use Illuminate\Database\Eloquent\Factories\Factory;

class HasilCulaanFactory extends Factory
{
    protected $model = HasilCulaan::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'no_ic' => fake()->unique()->numerify('############'),
            'umur' => fake()->numberBetween(18, 90),
            'no_tel' => '01'.fake()->numerify('########'),
            'bangsa' => 'Melayu',
            'alamat' => fake()->address(),
            'poskod' => fake()->numerify('#####'),
            'negeri' => 'JOHOR',
            'bandar' => 'SEGAMAT',
            'parlimen' => 'SEGAMAT',
            'kadun' => 'BULOH KASAP',
            'submitted_by' => null,
        ];
    }
}
```

Create `database/factories/DataPengundiFactory.php` with the identical `definition()` body but `protected $model = \App\Models\DataPengundi::class;` — it is needed by Task 4 and the two tables share this column shape.

Confirm both models use the `HasFactory` trait; add `use Illuminate\Database\Eloquent\Factories\HasFactory;` and `use HasFactory;` to `app/Models/HasilCulaan.php` and `app/Models/DataPengundi.php` if absent.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/VoterScopeServiceTest.php`
Expected: FAIL — `Class "App\Services\VoterScopeService" not found`.

- [ ] **Step 4: Write the implementation**

Create `app/Services/VoterScopeService.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies role-based row visibility to hasil_culaan / data_pengundi
 * queries. Both tables carry free-text `kadun` / `bandar` columns and a
 * `submitted_by` FK, so one rule serves both.
 *
 * Extracted from ReportsController, where it was written out three times.
 *
 * The '__none__' sentinels are load-bearing: a user with no Kadun assigned
 * must match zero rows. Without them the where() collapses to `kadun = null`
 * and, combined with orWhere(submitted_by), quietly widens visibility.
 */
class VoterScopeService
{
    public static function apply(Builder $query, User $user): Builder
    {
        if ($user->isUser() || $user->isSuperUser()) {
            return $query->where(function ($q) use ($user) {
                $q->where('kadun', $user->kadun->nama ?? '__none__')
                  ->orWhere('submitted_by', $user->id);
            });
        }

        if ($user->isAdmin()) {
            return $query->where(function ($q) use ($user) {
                $q->where('bandar', $user->bandar->nama ?? '__none__')
                  ->orWhere('submitted_by', $user->id);
            });
        }

        // super_admin: unrestricted.
        return $query;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/VoterScopeServiceTest.php`
Expected: PASS — 5 tests.

- [ ] **Step 6: Make `ReportsController` use the service**

Replace the scope block at `ReportsController.php:54-66` with:

```php
\App\Services\VoterScopeService::apply($filterQuery, $user);
```

Replace the block at `ReportsController.php:1002-1015` with:

```php
\App\Services\VoterScopeService::apply($query, $user);
```

Both call sites keep their surrounding code unchanged.

- [ ] **Step 7: Verify no behaviour change, then commit**

Run: `php artisan test`
Expected: **20 failed**, passed count up by 5 from Task 1's total. If failures exceed 20, revert and diff.

```bash
git add app/Services/VoterScopeService.php tests/Feature/VoterScopeServiceTest.php database/factories/HasilCulaanFactory.php database/factories/DataPengundiFactory.php app/Http/Controllers/ReportsController.php app/Models/HasilCulaan.php app/Models/DataPengundi.php
git commit -m "Refactor: ekstrak VoterScopeService dari ReportsController

Peraturan skop peranan ditulis tiga kali dalam ReportsController.
Satukan supaya API mudah alih tidak menyalin peraturan yang halus ini.

Sentinel '__none__' dikekalkan dan diuji: user tanpa Kadun mesti
padan sifar baris, bukan bocorkan semua rekod."
```

---

### Task 3: Idempotency column

The phone POSTs a Culaan, the server writes it, the response dies with the cell signal. The phone retries — and without a key, you get two records. Because `VoterSyncService` fans each Culaan out across `hasil_culaan` and `data_pengundi`, a duplicate corrupts downstream counts rather than merely looking untidy.

Additive migration only: one nullable column plus a unique index. Nullable because every existing production row (and every web submission) has no key. `NULL` values are exempt from unique constraints in both MySQL and SQLite, so unlimited web rows coexist with the index.

**Files:**
- Create: `database/migrations/2026_07_18_000001_add_idempotency_key_to_hasil_culaan.php`
- Create: `tests/Feature/MobileCulaanIdempotencyTest.php` (test written in Task 5, where the endpoint exists)

**Interfaces:**
- Consumes: nothing.
- Produces: `hasil_culaan.idempotency_key` — nullable `string(64)`, unique. Read by Task 5.

- [ ] **Step 1: Write the migration**

Create `database/migrations/2026_07_18_000001_add_idempotency_key_to_hasil_culaan.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-generated idempotency key for offline mobile submissions.
 *
 * Additive and reversible: one nullable column + unique index. Nothing is
 * dropped or reshaped, so this is safe against live Borang 14 / voter data
 * under the deploy's `migrate --force`.
 *
 * Nullable is deliberate — every existing row and every web submission has
 * no key. NULLs are exempt from UNIQUE in both MySQL and SQLite, so any
 * number of keyless rows coexist with the index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            // Drop the index before the column — MySQL error 1553 otherwise.
            // See 2026_07_16_100001_reshape_borang14_forms.php.
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `INFO  Running migrations.` then the migration name with `DONE`.

- [ ] **Step 3: Verify it reverses cleanly**

Run: `php artisan migrate:rollback --step=1 && php artisan migrate`
Expected: both succeed. This proves the index-before-column drop order is right — the trap documented in `2026_07_16_100001_reshape_borang14_forms.php`.

- [ ] **Step 4: Add the column to the model**

In `app/Models/HasilCulaan.php`, add `'idempotency_key',` to the top of `$fillable`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_18_000001_add_idempotency_key_to_hasil_culaan.php app/Models/HasilCulaan.php
git commit -m "Tambah idempotency_key pada hasil_culaan

Kunci jana-klien untuk penghantaran luar talian. Telefon POST, server
tulis, respons hilang, telefon cuba semula — tanpa kunci ini jadi dua
rekod. VoterSyncService menyebarkan setiap Culaan merentas dua jadual,
jadi pendua merosakkan kiraan hiliran, bukan sekadar buruk.

Additive: satu lajur nullable + unique index. Tiada apa dibentuk semula."
```

---

### Task 4: `GET /api/mobile/voters/search` and `GET /api/mobile/voters/{ic}`

The lookup half of the app. Searches `data_pengundi` (the voter roll — this is what the masked-create flow prefills from, per `ReportsController::hasilCulaanStore`'s `locked_source_id` → `DataPengundi::find`).

**The masking test is the important one here.** If `submittedBy` is not eager-loaded, `VoterDataMasker::isLocked()` reads a null relation, returns `false`, and every sensitive field ships to the phone unmasked. That failure is silent and invisible in manual testing — which is exactly why it gets an explicit test.

**Files:**
- Create: `app/Http/Controllers/Api/MobileVoterController.php`
- Create: `tests/Feature/MobileVoterSearchTest.php`
- Modify: `routes/web.php` (inside the `api/mobile` group, `:533-535`)

**Interfaces:**
- Consumes: `VoterScopeService::apply()` (Task 2), `DataPengundiFactory` (Task 2).
- Produces: `GET /api/mobile/voters/search?q=<string>` → `{"success": true, "voters": [<masked array>]}`. `GET /api/mobile/voters/{ic}` → `{"success": true, "voter": <masked array>}` or 404 `{"success": false, "errors": {"no_ic": ["Pengundi tidak dijumpai."]}}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MobileVoterSearchTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileVoterSearchTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;
    private Bandar $bandar;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'JOHOR']);
        $this->bandar = Bandar::create(['nama' => 'SEGAMAT', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'BULOH KASAP', 'bandar_id' => $this->bandar->id]);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/mobile/voters/search?q=Ahmad')->assertStatus(401);
    }

    public function test_search_finds_voter_by_name_within_scope(): void
    {
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['nama' => 'Ahmad bin Ali', 'kadun' => 'BULOH KASAP']);

        $this->getJson('/api/mobile/voters/search?q=Ahmad')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'voters')
            ->assertJsonPath('voters.0.nama', 'Ahmad bin Ali');
    }

    public function test_search_finds_voter_by_ic(): void
    {
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['no_ic' => '800101015555', 'kadun' => 'BULOH KASAP']);

        $this->getJson('/api/mobile/voters/search?q=800101015555')
            ->assertOk()
            ->assertJsonCount(1, 'voters');
    }

    public function test_search_does_not_leak_records_outside_the_users_kadun(): void
    {
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['nama' => 'Ahmad Luar', 'kadun' => 'JEMENTAH']);

        $this->getJson('/api/mobile/voters/search?q=Ahmad')
            ->assertOk()
            ->assertJsonCount(0, 'voters');
    }

    public function test_sensitive_fields_are_masked_for_records_submitted_by_a_user(): void
    {
        $viewer = $this->makeUser('user');
        $submitter = $this->makeUser('user');
        Sanctum::actingAs($viewer);

        DataPengundi::factory()->create([
            'nama' => 'Ahmad bin Ali',
            'no_ic' => '800101015555',
            'no_tel' => '0123456789',
            'kadun' => 'BULOH KASAP',
            'submitted_by' => $submitter->id,
        ]);

        $res = $this->getJson('/api/mobile/voters/search?q=Ahmad')->assertOk();

        // Nama is never masked; the sensitive set always is.
        $res->assertJsonPath('voters.0.nama', 'Ahmad bin Ali');
        $res->assertJsonPath('voters.0.no_ic', '****');
        $res->assertJsonPath('voters.0.no_tel', '****');
        $res->assertJsonPath('voters.0.alamat', '****');

        // Belt and braces: the real values must not appear anywhere in the body.
        $body = $res->getContent();
        $this->assertStringNotContainsString('800101015555', $body);
        $this->assertStringNotContainsString('0123456789', $body);
    }

    public function test_admin_viewer_sees_unmasked_values(): void
    {
        $submitter = $this->makeUser('user');
        Sanctum::actingAs($this->makeUser('admin'));

        DataPengundi::factory()->create([
            'nama' => 'Ahmad bin Ali',
            'no_ic' => '800101015555',
            'bandar' => 'SEGAMAT',
            'submitted_by' => $submitter->id,
        ]);

        $this->getJson('/api/mobile/voters/search?q=Ahmad')
            ->assertOk()
            ->assertJsonPath('voters.0.no_ic', '800101015555');
    }

    public function test_show_returns_404_in_bm_when_ic_not_found(): void
    {
        Sanctum::actingAs($this->makeUser('user'));

        $this->getJson('/api/mobile/voters/999999999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.no_ic.0', 'Pengundi tidak dijumpai.');
    }

    public function test_search_requires_a_query_of_at_least_three_characters(): void
    {
        Sanctum::actingAs($this->makeUser('user'));

        $this->getJson('/api/mobile/voters/search?q=Ah')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MobileVoterSearchTest.php`
Expected: FAIL — 404s on every route; the routes do not exist yet.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Api/MobileVoterController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataPengundi;
use App\Services\VoterDataMasker;
use App\Services\VoterScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileVoterController extends Controller
{
    private const MAX_RESULTS = 30;

    /**
     * Search the voter roll by name or IC, scoped to the caller's role.
     *
     * Every payload goes through VoterDataMasker. The submittedBy relation
     * MUST stay eager-loaded: without it isLocked() sees a null relation,
     * returns false, and ships unmasked PII to the phone silently.
     */
    public function search(Request $request): JsonResponse
    {
        $validator = validator($request->all(), ['q' => 'required|string|min:3']);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => ['q' => ['Sila masukkan sekurang-kurangnya 3 aksara.']],
            ], 422);
        }

        $q = $request->query('q');
        $user = $request->user();

        $query = DataPengundi::with('submittedBy');
        VoterScopeService::apply($query, $user);

        $query->where(function ($sub) use ($q) {
            $sub->where('nama', 'like', "%{$q}%")
                ->orWhere('no_ic', 'like', "%{$q}%");
        });

        $voters = $query->limit(self::MAX_RESULTS)->get()
            ->map(fn ($row) => VoterDataMasker::mask($row, $user))
            ->values();

        return response()->json(['success' => true, 'voters' => $voters]);
    }

    public function show(Request $request, string $ic): JsonResponse
    {
        $user = $request->user();

        $query = DataPengundi::with('submittedBy')->where('no_ic', $ic);
        VoterScopeService::apply($query, $user);

        $voter = $query->first();

        if (! $voter) {
            return response()->json([
                'success' => false,
                'errors' => ['no_ic' => ['Pengundi tidak dijumpai.']],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'voter' => VoterDataMasker::mask($voter, $user),
        ]);
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/web.php`, inside the `Route::prefix('api/mobile')` group, below the existing `web-auth-token` line (`routes/web.php:534`), add:

```php
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/voters/search', [\App\Http\Controllers\Api\MobileVoterController::class, 'search']);
        Route::get('/voters/{ic}', [\App\Http\Controllers\Api\MobileVoterController::class, 'show']);
    });
```

Order matters: `/voters/search` must be registered **before** `/voters/{ic}`, or the wildcard swallows the literal `search` and every search request 404s as a missing voter.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/MobileVoterSearchTest.php`
Expected: PASS — 8 tests.

- [ ] **Step 6: Run the full suite and commit**

Run: `php artisan test`
Expected: **20 failed** (unchanged baseline).

```bash
git add app/Http/Controllers/Api/MobileVoterController.php tests/Feature/MobileVoterSearchTest.php routes/web.php
git commit -m "Tambah endpoint carian pengundi mudah alih

GET /api/mobile/voters/search dan /voters/{ic}, berskop peranan dan
dimask. Ujian mengesahkan nilai sensitif tidak muncul di mana-mana
dalam badan respons, bukan sekadar medan yang dijangka — relasi
submittedBy yang tidak dimuat akan mendiamkan masking sepenuhnya."
```

---

### Task 5: `POST /api/mobile/culaan` — the offline write path

The endpoint the whole app exists for. Three things must hold:

1. **Idempotent** — a replayed key returns the original record and writes nothing.
2. **Transactional** — the record plus the `VoterSyncService` fan-out either all land or none do.
3. **Failure-classifiable** — the client sorts responses into transient / auth / permanent buckets, so status codes must be honest: 403 Parlimen, 422 validation, 409 duplicate. Never 500 for a rejection the client could act on.

**Files:**
- Create: `app/Http/Requests/StoreMobileCulaanRequest.php`
- Create: `app/Http/Controllers/Api/MobileCulaanController.php`
- Create: `tests/Feature/MobileCulaanStoreTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `CulaanPayloadNormalizer::normalize()` (Task 1), `hasil_culaan.idempotency_key` (Task 3).
- Produces: `POST /api/mobile/culaan` → 201 `{"success": true, "culaan": {"id": int, "no_ic": string}}`. Errors per the envelope. Used by Plan 2's sync engine.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MobileCulaanStoreTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCulaanStoreTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;
    private Bandar $bandar;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'JOHOR']);
        $this->bandar = Bandar::create(['nama' => 'SEGAMAT', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'BULOH KASAP', 'bandar_id' => $this->bandar->id]);
    }

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'nama' => 'Ahmad bin Ali',
            'no_ic' => '800101015555',
            'umur' => 45,
            'no_tel' => '0123456789',
            'bangsa' => 'Melayu',
            'alamat' => 'No 1, Jalan Besar',
            'poskod' => '85000',
            'negeri' => 'JOHOR',
            'bandar' => 'SEGAMAT',
            'parlimen' => 'SEGAMAT',
            'kadun' => 'BULOH KASAP',
            'has_sumbangan' => false,
        ], $overrides);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/mobile/culaan', $this->payload())->assertStatus(401);
    }

    public function test_creates_a_culaan_record(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('hasil_culaan', ['no_ic' => '800101015555']);
    }

    public function test_replaying_the_same_idempotency_key_does_not_create_a_second_record(): void
    {
        Sanctum::actingAs($this->makeUser());
        $payload = $this->payload();

        $first = $this->postJson('/api/mobile/culaan', $payload)->assertStatus(201);
        $second = $this->postJson('/api/mobile/culaan', $payload)->assertStatus(201);

        // This is the lost-response retry. One row, and the SAME row.
        $this->assertSame(1, HasilCulaan::where('no_ic', '800101015555')->count());
        $this->assertSame(
            $first->json('culaan.id'),
            $second->json('culaan.id'),
            'A replayed key must return the original record, not write a new one.'
        );
    }

    public function test_rejects_a_record_outside_the_users_parlimen_with_403(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload(['parlimen' => 'MUAR']))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.parlimen.0', 'Rekod ini di luar Parlimen anda.');
    }

    public function test_missing_required_field_returns_422_not_500(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload(['nama' => '']))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['nama']]);
    }

    public function test_sumbangan_fields_are_required_only_when_has_sumbangan_is_true(): void
    {
        Sanctum::actingAs($this->makeUser());

        // Without the toggle, the Isi Rumah / Bantuan fields may be absent.
        $this->postJson('/api/mobile/culaan', $this->payload())->assertStatus(201);

        // With it, they are required.
        $this->postJson('/api/mobile/culaan', $this->payload([
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'no_ic' => '800101015556',
            'has_sumbangan' => true,
        ]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['bil_isi_rumah']]);
    }

    public function test_checkbox_arrays_are_flattened_via_the_normalizer(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/mobile/culaan', $this->payload([
            'has_sumbangan' => true,
            'bil_isi_rumah' => 4,
            'pekerjaan' => 'Swasta',
            'jenis_pekerjaan' => ['Pembuatan'],
            'pemilik_rumah' => 'Sendiri',
            'jenis_sumbangan' => ['Tunai', 'Lain-lain'],
            'jenis_sumbangan_lain' => 'Baucar buku',
            'tujuan_sumbangan' => ['Pendidikan'],
            'bantuan_lain' => ['Tiada'],
        ]))->assertStatus(201);

        $this->assertDatabaseHas('hasil_culaan', [
            'no_ic' => '800101015555',
            'jenis_sumbangan' => 'Tunai, Baucar buku',
        ]);
    }

    public function test_idempotency_key_is_required(): void
    {
        Sanctum::actingAs($this->makeUser());
        $payload = $this->payload();
        unset($payload['idempotency_key']);

        $this->postJson('/api/mobile/culaan', $payload)->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MobileCulaanStoreTest.php`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the form request**

Create `app/Http/Requests/StoreMobileCulaanRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors the conditional rules in ReportsController::hasilCulaanStore.
 *
 * The client mirrors these rules again in Dart to validate before queuing
 * offline. That duplication is unavoidable — the phone cannot call this —
 * so any change here MUST be mirrored in the Flutter validator, and the
 * 422 path in the sync inbox is the safety net when they drift.
 */
class StoreMobileCulaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Parlimen check runs in the controller; it needs the record.
    }

    public function rules(): array
    {
        $has = $this->boolean('has_sumbangan');
        $req = fn (string $rule) => $has ? "required|{$rule}" : "nullable|{$rule}";

        return [
            'idempotency_key' => 'required|string|max:64',

            'nama' => 'required|string|max:255',
            'no_ic' => 'required|string|digits:12',
            'umur' => 'required|integer|min:1|max:150',
            'no_tel' => 'required|string|max:255',
            'bangsa' => 'required|string|max:255',
            'alamat' => 'required|string',
            'poskod' => 'required|string|max:255',
            'negeri' => 'required|string|max:255',
            'bandar' => 'required|string|max:255',
            'parlimen' => 'required|string|max:255',
            'kadun' => 'required|string|max:255',

            'mpkk' => 'nullable|string|max:255',
            'daerah_mengundi' => 'nullable|string|max:255',
            'lokaliti' => 'nullable|string|max:255',

            'has_sumbangan' => 'boolean',
            'locked_source_id' => 'nullable|integer|exists:data_pengundi,id',

            'bil_isi_rumah' => $req('integer|min:1'),
            'pendapatan_isi_rumah' => 'nullable|numeric|min:0',
            'pekerjaan' => $req('in:Kerajaan,Swasta,Bekerja Sendiri,Tidak Bekerja'),
            'jenis_pekerjaan' => $req('array|min:1'),
            'jenis_pekerjaan.*' => 'string|max:255',
            'jenis_pekerjaan_lain' => 'nullable|string|max:255',
            'pemilik_rumah' => $req('string|max:255'),
            'pemilik_rumah_lain' => 'nullable|string|max:255',
            'jenis_sumbangan' => $req('array|min:1'),
            'jenis_sumbangan_lain' => 'nullable|string|max:255',
            'tujuan_sumbangan' => $req('array|min:1'),
            'tujuan_sumbangan_lain' => 'nullable|string|max:255',
            'bantuan_lain' => $req('array|min:1'),
            'bantuan_lain_lain' => 'nullable|string|max:255',
            'perkeso_bantuan' => 'nullable|array',
            'perkeso_bantuan_lain' => 'nullable|string|max:255',
            'zpp_jenis_bantuan' => 'nullable|array',
            'isejahtera_program' => 'nullable|string|max:255',
            'bkb_program' => 'nullable|string|max:255',
            'jumlah_bantuan_tunai' => 'nullable|numeric|min:0',
            'jumlah_wang_tunai' => 'nullable|numeric|min:0',

            'keahlian_parti' => 'nullable|string|max:255',
            'kecenderungan_politik' => 'nullable|string|max:255',
            'status_pengundi' => 'nullable|string|max:255',
            'nota' => 'nullable|string',
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

Create `app/Http/Controllers/Api/MobileCulaanController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMobileCulaanRequest;
use App\Models\DataPengundi;
use App\Models\EditHistory;
use App\Models\HasilCulaan;
use App\Services\CulaanPayloadNormalizer;
use App\Services\VoterDataMasker;
use App\Services\VoterSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MobileCulaanController extends Controller
{
    /**
     * Create a Hasil Culaan from the mobile app.
     *
     * Idempotent by client-generated key: a replay returns the original
     * record untouched. This is what makes the client's automatic retry
     * safe after a lost response.
     */
    public function store(StoreMobileCulaanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Replay of a key we have already honoured — return the original.
        $existing = HasilCulaan::where('idempotency_key', $validated['idempotency_key'])->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'culaan' => ['id' => $existing->id, 'no_ic' => $existing->no_ic],
            ], 201);
        }

        // Parlimen restriction, mirroring ReportsController::hasilCulaanStore.
        if (($user->isUser() || $user->isAdmin()) && $validated['parlimen'] !== ($user->bandar->nama ?? '')) {
            return response()->json([
                'success' => false,
                'errors' => ['parlimen' => ['Rekod ini di luar Parlimen anda.']],
            ], 403);
        }

        // Masked-create: the draft carries '****' placeholders for fields the
        // user was never shown. Swap in the truth from the source record so
        // validation ran against the mask but storage gets real values.
        if (! empty($validated['locked_source_id'])) {
            $source = DataPengundi::find($validated['locked_source_id']);
            if (! $source) {
                return response()->json([
                    'success' => false,
                    'errors' => ['locked_source_id' => ['Rekod sumber tidak lagi wujud. Sila cari semula pengundi ini.']],
                ], 409);
            }
            foreach (VoterDataMasker::SENSITIVE_FIELDS as $field) {
                if (($validated[$field] ?? null) === VoterDataMasker::MASK) {
                    $validated[$field] = $source->{$field};
                }
            }
        }

        unset($validated['has_sumbangan'], $validated['locked_source_id']);

        $payload = CulaanPayloadNormalizer::normalize($validated);
        $payload['submitted_by'] = $user->id;
        $payload['sumber'] = 'mobile';

        // The create fans out through VoterSyncService across two tables.
        // CLAUDE.md flags the HTTP layer as transaction-free; this path is not.
        $record = DB::transaction(function () use ($payload) {
            $record = HasilCulaan::create($payload);
            EditHistory::log('hasil_culaan', $record->id, 'created (mobile)');
            VoterSyncService::syncFromHasilCulaan($record->fresh());

            return $record;
        });

        return response()->json([
            'success' => true,
            'culaan' => ['id' => $record->id, 'no_ic' => $record->no_ic],
        ], 201);
    }
}
```

- [ ] **Step 5: Register the route**

Add inside the `auth:sanctum` group created in Task 4:

```php
        Route::post('/culaan', [\App\Http\Controllers\Api\MobileCulaanController::class, 'store']);
```

- [ ] **Step 6: Make validation failures return the BM envelope**

A `FormRequest` inside this route group returns Laravel's default `{"message": ..., "errors": ...}` — no `success` key, which the client's classifier depends on. In `app/Http/Requests/StoreMobileCulaanRequest.php`, add:

```php
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
```

and the method:

```php
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/MobileCulaanStoreTest.php`
Expected: PASS — 8 tests.

If `test_replaying_the_same_idempotency_key...` fails with a unique-constraint 500 rather than returning the original row, the early-return lookup is missing or running after the insert. That test is the reason this task exists — do not skip past it.

- [ ] **Step 8: Run the full suite and commit**

Run: `php artisan test`
Expected: **20 failed** (unchanged baseline).

```bash
git add app/Http/Requests/StoreMobileCulaanRequest.php app/Http/Controllers/Api/MobileCulaanController.php tests/Feature/MobileCulaanStoreTest.php routes/web.php
git commit -m "Tambah POST /api/mobile/culaan dengan idempotency

Laluan tulis untuk aplikasi luar talian. Tiga jaminan diuji:
- Kunci diulang memulangkan rekod asal, tidak menulis kedua
- Cipta + fan-out VoterSyncService dibungkus transaksi
- Penolakan pulangkan 403/422/409 yang jujur, bukan 500, supaya
  klien boleh kelaskan kegagalan sementara vs kekal

Aliran masked-create dikekalkan: placeholder '****' ditukar kepada
nilai sebenar dari rekod sumber semasa sync."
```

---

### Task 6: `GET /api/mobile/culaan/options` and `GET /api/mobile/culaan/mine`

Two read endpoints that finish the loop. `options` feeds the form's dropdowns and checkbox lists; `mine` backs the "Rekod Saya" list and lets the client reconcile after a reinstall.

The taxonomy lists currently live as hardcoded JSX arrays in `Create.jsx` (e.g. the `jenis_pekerjaan` list at `Create.jsx:1061`). Serving them from the API means the phone and web share one source and the app can add options without a store release.

**Files:**
- Create: `tests/Feature/MobileCulaanReadTest.php`
- Modify: `app/Http/Controllers/Api/MobileCulaanController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `HasilCulaanFactory` (Task 2), `VoterDataMasker::mask()` (existing). `mine` filters on `submitted_by` directly and does **not** use `VoterScopeService` — a caller's own records are always visible to them regardless of Kadun.
- Produces: `GET /api/mobile/culaan/options` → `{"success": true, "options": {"pekerjaan": [...], "jenis_pekerjaan": [...], "jenis_sumbangan": [...], "tujuan_sumbangan": [...], "bantuan_lain": [...], "pemilik_rumah": [...]}}`. `GET /api/mobile/culaan/mine` → `{"success": true, "culaan": [<masked array>]}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MobileCulaanReadTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCulaanReadTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;
    private Bandar $bandar;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'JOHOR']);
        $this->bandar = Bandar::create(['nama' => 'SEGAMAT', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'BULOH KASAP', 'bandar_id' => $this->bandar->id]);
    }

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
    }

    public function test_options_requires_authentication(): void
    {
        $this->getJson('/api/mobile/culaan/options')->assertStatus(401);
    }

    public function test_options_returns_every_taxonomy_the_form_needs(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/mobile/culaan/options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['options' => [
                'pekerjaan',
                'jenis_pekerjaan',
                'jenis_sumbangan',
                'tujuan_sumbangan',
                'bantuan_lain',
                'pemilik_rumah',
            ]]);
    }

    public function test_pekerjaan_options_match_the_servers_validation_rule(): void
    {
        Sanctum::actingAs($this->makeUser());

        // If these drift from StoreMobileCulaanRequest's in: rule, the app
        // offers choices the server will reject with a 422 the user cannot fix.
        $this->getJson('/api/mobile/culaan/options')
            ->assertOk()
            ->assertJsonPath('options.pekerjaan', ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja']);
    }

    public function test_mine_returns_only_records_submitted_by_the_caller(): void
    {
        $me = $this->makeUser();
        $other = $this->makeUser();
        Sanctum::actingAs($me);

        HasilCulaan::factory()->create(['nama' => 'Rekod Saya', 'submitted_by' => $me->id]);
        HasilCulaan::factory()->create(['nama' => 'Rekod Orang Lain', 'submitted_by' => $other->id]);

        $this->getJson('/api/mobile/culaan/mine')
            ->assertOk()
            ->assertJsonCount(1, 'culaan')
            ->assertJsonPath('culaan.0.nama', 'Rekod Saya');
    }

    public function test_mine_is_newest_first(): void
    {
        $me = $this->makeUser();
        Sanctum::actingAs($me);

        $old = HasilCulaan::factory()->create(['nama' => 'Lama', 'submitted_by' => $me->id]);
        $old->update(['created_at' => now()->subDays(2)]);
        HasilCulaan::factory()->create(['nama' => 'Baru', 'submitted_by' => $me->id]);

        $this->getJson('/api/mobile/culaan/mine')
            ->assertOk()
            ->assertJsonPath('culaan.0.nama', 'Baru');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MobileCulaanReadTest.php`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Add the two methods**

Append to `app/Http/Controllers/Api/MobileCulaanController.php`, and add `use Illuminate\Http\Request;` to the imports (`VoterDataMasker` and `HasilCulaan` are already imported from Task 5):

```php
    /**
     * Taxonomy for the form's dropdowns and checkbox groups.
     *
     * Single source of truth for phone and web. The pekerjaan list MUST
     * stay identical to the in: rule in StoreMobileCulaanRequest — if they
     * drift, the app offers a choice the server rejects with a 422 the user
     * has no way to fix. MobileCulaanReadTest asserts they match.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'options' => [
                'pekerjaan' => ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja'],
                'jenis_pekerjaan' => [
                    'Pentadbiran', 'Pendidikan', 'Kesihatan', 'Keselamatan',
                    'Pembuatan', 'Pertanian', 'Pembinaan', 'Peruncitan',
                    'Pengangkutan', 'Teknologi Maklumat / Digital', 'Lain-lain',
                ],
                'jenis_sumbangan' => ['Tunai', 'Barangan', 'Perkhidmatan', 'Lain-lain'],
                'tujuan_sumbangan' => [
                    'Pendidikan', 'Kesihatan', 'Perumahan', 'Bencana',
                    'Keagamaan', 'Sukan', 'Lain-lain',
                ],
                'bantuan_lain' => ['Tiada', 'PERKESO', 'ZPP', 'iSejahtera', 'BKB', 'JKM', 'Lain-lain'],
                'pemilik_rumah' => ['Sendiri', 'Sewa', 'Keluarga', 'Lain-lain'],
            ],
        ]);
    }

    /**
     * Records submitted by the caller. Backs "Rekod Saya" and lets a
     * reinstalled app reconcile what already reached the server.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = HasilCulaan::with('submittedBy')
            ->where('submitted_by', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($row) => VoterDataMasker::mask($row, $user))
            ->values();

        return response()->json(['success' => true, 'culaan' => $rows]);
    }
```

> **Taxonomy source:** the `jenis_pekerjaan` list is transcribed from `resources/js/Pages/Reports/HasilCulaan/Create.jsx:1061`. Before implementing, open that file and copy the live arrays verbatim — the lists above are the shape, and the JSX is the truth. A mismatch here silently changes what field workers can record.

- [ ] **Step 4: Register the routes**

Add inside the `auth:sanctum` group, **above** any wildcard culaan route:

```php
        Route::get('/culaan/options', [\App\Http\Controllers\Api\MobileCulaanController::class, 'options']);
        Route::get('/culaan/mine', [\App\Http\Controllers\Api\MobileCulaanController::class, 'mine']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/MobileCulaanReadTest.php`
Expected: PASS — 5 tests.

- [ ] **Step 6: Run the full suite and commit**

Run: `php artisan test`
Expected: **20 failed** (unchanged baseline).

```bash
git add app/Http/Controllers/Api/MobileCulaanController.php tests/Feature/MobileCulaanReadTest.php routes/web.php
git commit -m "Tambah endpoint options dan mine untuk Culaan mudah alih

options/ menyajikan taksonomi borang dari server supaya telefon dan web
kongsi satu sumber, dan pilihan boleh ditambah tanpa keluaran store baharu.
Ujian mengunci senarai pekerjaan kepada peraturan in: di server — jika
keduanya menyimpang, app tawarkan pilihan yang server tolak dengan 422
yang user tidak boleh betulkan.

mine/ menyokong Rekod Saya dan rekonsiliasi selepas pasang semula."
```

---

### Task 7: Endpoint contract documentation

Plan 2 is written against these endpoints, and the Flutter engineer will not read `ReportsController`. One page listing every endpoint, its request shape, and — critically — **which HTTP status maps to which client-side failure bucket**, because that mapping is the entire basis of the sync engine's behaviour.

**Files:**
- Create: `docs/superpowers/specs/2026-07-17-kontrak-api-mudah-alih.md`

**Interfaces:**
- Consumes: Tasks 4, 5, 6.
- Produces: the reference Plan 2 builds against.

- [ ] **Step 1: Write the contract doc**

Create `docs/superpowers/specs/2026-07-17-kontrak-api-mudah-alih.md` documenting, for each of the 12 endpoints: method, path, auth requirement, request body, success response, and every error response. Include this table verbatim — it is the contract the sync engine encodes:

```markdown
## Pemetaan status → baldi kegagalan klien

| Status | Maksud | Baldi | Tindakan klien |
|---|---|---|---|
| 200/201 | Berjaya | — | Padam draf tempatan |
| 401 | Token dibatalkan | Auth | Kekal `queued`, minta login semula |
| 403 | Di luar Parlimen | Kekal | → Perlu Perhatian |
| 409 | Pendua / rekod sumber hilang | Kekal | → Perlu Perhatian |
| 422 | Validation | Kekal | → Perlu Perhatian |
| 429 | Rate limit | Sementara | Backoff, cuba semula |
| 5xx | Ralat server | Sementara | Backoff, cuba semula |
| timeout / tiada rangkaian | — | Sementara | Backoff, cuba semula |
```

Note explicitly that **429 is transient, not permanent** — it is the one 4xx that must be retried, and misclassifying it strands a legitimate submission in the inbox forever.

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-07-17-kontrak-api-mudah-alih.md
git commit -m "Dokumentasi kontrak API mudah alih

Rujukan untuk Plan 2. Termasuk pemetaan status → baldi kegagalan,
asas kepada tingkah laku enjin sync. 429 sementara, bukan kekal."
```

---

## Definition of Done

- [ ] `php artisan test` → **20 failed / 160 passed** (baseline 20/127, plus 33 new tests: Task 1 = 7, Task 2 = 5, Task 4 = 8, Task 5 = 8, Task 6 = 5).
- [ ] `php artisan migrate:rollback --step=1 && php artisan migrate` round-trips cleanly.
- [ ] No sensitive value appears unmasked in any response to a `user`-role caller.
- [ ] `ReportsController` contains no inlined scope or normalisation logic.
- [ ] Every endpoint returns the `{"success": bool, ...}` envelope, including validation failures.
- [ ] The contract doc exists and Plan 2 can be written from it alone.

## Deploy note

Pushing this to `main` triggers the GitHub Actions deploy, which runs `migrate --force` against production. Task 3's migration is additive and safe, but the deploy also `git reset --hard`s and reinstalls dependencies. **Do not merge this branch while field work is in progress.** Verify afterwards by hitting `/api/mobile/culaan/options` on the live host with a real token.
