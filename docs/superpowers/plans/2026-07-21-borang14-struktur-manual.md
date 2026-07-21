# Borang 14 — Struktur Manual Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a user hand-build the Pusat Mengundi / saluran structure of any Borang 14 seat, so a blank form can be created and filled on counting night for an upcoming election.

**Architecture:** The hand-built structure is written into the existing `borang14_forms.structure` JSON column in the same `rows` shape a scoresheet produces, marked `origin: 'manual'`. `resolveReference()` already ranks a form's own structure above the DPT estimate, so no priority-chain change is needed. All shape logic (expand a per-Pusat saluran count into per-saluran rows, collapse it back, compute which votes a proposed edit orphans) lives in one pure service class; the controller only guards, transacts, and persists.

**Tech Stack:** Laravel 12, React 18 + Inertia, axios, Tailwind, PHPUnit. MySQL in production, SQLite in CI.

## Global Constraints

- **All user-facing text is Bahasa Melayu.** No i18n layer — strings are hardcoded inline; match surrounding copy.
- **Unknown is not zero.** `berdaftar` and every printed figure stays `null` for manual structures. Never write `?? 0`; never coerce `null` to `0`.
- **Authorization lives in the controller,** not middleware. Exact convention:
  `$user = auth()->user(); if (!$user->isSuperAdmin() && !$user->isAdmin()) { abort(403, 'Unauthorized action.'); }`
- **No `Schema::drop`.** This plan adds **no migration at all** — the structure rides in an existing JSON column.
- **Wrap multi-row writes in `DB::transaction`.** The HTTP layer has historically not done this; new code must.
- **Test baseline is 20 failed / 342 passed.** The 20 failures are pre-existing (`UserFactory` does not set the NOT NULL `telephone` column). Only worry if that count grows. In new tests, create users with an explicit telephone — see the helper in Task 3.
- **Run tests with** `php artisan test`.
- Spec: `docs/superpowers/specs/2026-07-21-borang14-struktur-manual-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `app/Services/Borang14StrukturService.php` *(create)* | Pure shape logic: expand/collapse/rename-map/orphan-detection. No DB writes, no HTTP. |
| `tests/Unit/Borang14StrukturServiceTest.php` *(create)* | Unit tests for the above. |
| `app/Http/Controllers/Borang14Controller.php` *(modify)* | Two new endpoints (`simpanStruktur`, `kesanStruktur`), one new guard, guards on `crosscheckIssues()`/`referenceFromStructure()`, one new block in `data()`. |
| `routes/web.php` *(modify)* | Two routes in the existing Borang 14 group. |
| `tests/Feature/Borang14StrukturManualTest.php` *(create)* | Feature tests: dead-end break, anti-orphan, cascade, priority, permissions, revert. |
| `resources/js/Pages/Pilihanraya/Borang14/StrukturPanel.jsx` *(create)* | The editor panel. Knows only: a Pusat list in, the same list out. |
| `resources/js/Pages/Pilihanraya/Borang14/KeyinTab.jsx` *(modify)* | Button, dead-end CTA, panel swap. Kept small — the file is already 491 lines. |
| `resources/js/Pages/Pilihanraya/Borang14/UploadTab.jsx` *(modify)* | One warning line: uploading a scoresheet overwrites a manual structure. |

**Data shapes used across every task** (copy exactly):

```php
// STORED in borang14_forms.structure — one row PER SALURAN
[
    'origin' => 'manual',
    'calon'  => [],
    'rows'   => [
        ['row_id' => 'pm_a1', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran' => '1'],
        ['row_id' => 'pm_a1', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran' => '2'],
        ['row_id' => 'pm_pos', 'dm' => '', 'pusat' => '', 'saluran' => 'UNDI POS'],
    ],
]

// EXCHANGED with the frontend — one entry PER PUSAT MENGUNDI
['row_id' => 'pm_a1', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 2]
```

---

### Task 1: `Borang14StrukturService` — pure shape logic

**Files:**
- Create: `app/Services/Borang14StrukturService.php`
- Test: `tests/Unit/Borang14StrukturServiceTest.php`

**Interfaces:**
- Consumes: nothing (leaf task).
- Produces, all `public` and all pure:
  - `expand(array $pusatList, bool $undiAwal, bool $undiPos): array` — per-Pusat entries → full `structure` array (with `origin`, `calon`, `rows`).
  - `collapse(?array $structure): array` — a stored `structure` (manual, scoresheet, or inherited) → `['pusat' => array, 'undi_awal' => bool, 'undi_pos' => bool]`.
  - `renameMap(?array $oldStructure, array $pusatList): array` — `['OLD PUSAT NAME' => 'NEW PUSAT NAME', ...]`, matched by `row_id`, only where the name actually changed.
  - `survivingKeys(array $structure): array` — `['PUSAT|SALURAN' => true, ...]` for every row the new structure keeps.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Borang14StrukturServiceTest.php`:

```php
<?php
// tests/Unit/Borang14StrukturServiceTest.php
//
// Logik BENTUK sahaja — tiada DB, tiada HTTP. Kelas ini yang memutuskan undi
// mana akan berpindah dan undi mana akan dipadam apabila struktur disunting,
// jadi setiap keputusan itu dikunci di sini.
namespace Tests\Unit;

use App\Services\Borang14StrukturService;
use PHPUnit\Framework\TestCase;

class Borang14StrukturServiceTest extends TestCase
{
    private Borang14StrukturService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new Borang14StrukturService;
    }

    public function test_expand_writes_one_row_per_saluran(): void
    {
        $out = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 3],
            ['row_id' => 'pm_b', 'dm' => 'JUASSEH', 'pusat' => 'DEWAN ORANG RAMAI', 'saluran_count' => 1],
        ], false, false);

        $this->assertSame('manual', $out['origin']);
        $this->assertSame([], $out['calon']);
        $this->assertCount(4, $out['rows']);
        $this->assertSame(
            ['1', '2', '3'],
            collect($out['rows'])->where('pusat', 'SK TENGKEK')->pluck('saluran')->all(),
        );
        $this->assertSame('pm_a', $out['rows'][0]['row_id']);
    }

    public function test_expand_never_fabricates_printed_figures(): void
    {
        $out = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK A', 'saluran_count' => 1],
        ], false, false);

        // Tiada sheet bercetak untuk dibaca — 'a'/'jumlah_undian'/'undi' MESTI
        // tidak wujud langsung, bukan 0. Sifar di sini akan menjadi angka yang
        // direka dan crosscheck akan menuduh pengguna berbohong.
        $this->assertSame(['row_id', 'dm', 'pusat', 'saluran'], array_keys($out['rows'][0]));
    }

    public function test_expand_adds_undi_awal_and_pos_rows_only_when_flagged(): void
    {
        $none = $this->svc->expand([], false, false);
        $this->assertSame([], $none['rows']);

        $both = $this->svc->expand([], true, true);
        $this->assertSame(['UNDI AWAL', 'UNDI POS'], collect($both['rows'])->pluck('saluran')->all());
        $this->assertSame('', $both['rows'][0]['pusat']);
    }

    public function test_collapse_is_the_inverse_of_expand(): void
    {
        $pusat = [
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 3],
        ];
        $back = $this->svc->collapse($this->svc->expand($pusat, false, true));

        $this->assertSame($pusat, $back['pusat']);
        $this->assertFalse($back['undi_awal']);
        $this->assertTrue($back['undi_pos']);
    }

    public function test_collapse_derives_a_stable_row_id_for_legacy_structures(): void
    {
        // Struktur scoresheet/warisan tiada row_id. Membukanya untuk disunting
        // mesti memberi id yang SAMA setiap kali, jika tidak suntingan kedua
        // akan nampak setiap pusat sebagai "baharu" dan cascade akan memadam
        // undi yang sepatutnya berpindah.
        $legacy = ['rows' => [
            ['dm' => 'DM', 'pusat' => 'SK A', 'saluran' => '1', 'a' => 120],
            ['dm' => 'DM', 'pusat' => 'SK A', 'saluran' => '2', 'a' => 118],
        ]];

        $first = $this->svc->collapse($legacy);
        $second = $this->svc->collapse($legacy);

        $this->assertSame($first['pusat'][0]['row_id'], $second['pusat'][0]['row_id']);
        $this->assertNotSame('', $first['pusat'][0]['row_id']);
        $this->assertSame(2, $first['pusat'][0]['saluran_count']);
    }

    public function test_collapse_of_null_structure_is_empty_not_an_error(): void
    {
        $this->assertSame(
            ['pusat' => [], 'undi_awal' => false, 'undi_pos' => false],
            $this->svc->collapse(null),
        );
    }

    public function test_rename_map_matches_on_row_id_not_on_name(): void
    {
        $old = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK TENGKEK', 'saluran_count' => 1],
            ['row_id' => 'pm_b', 'dm' => 'DM', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ], false, false);

        $map = $this->svc->renameMap($old, [
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SEKOLAH KEBANGSAAN TENGKEK', 'saluran_count' => 1],
            ['row_id' => 'pm_b', 'dm' => 'DM', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ]);

        // Hanya yang benar-benar bertukar nama.
        $this->assertSame(['SK TENGKEK' => 'SEKOLAH KEBANGSAAN TENGKEK'], $map);
    }

    public function test_rename_map_ignores_rows_the_edit_dropped(): void
    {
        $old = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK A', 'saluran_count' => 1],
        ], false, false);

        $this->assertSame([], $this->svc->renameMap($old, []));
    }

    public function test_surviving_keys_lists_every_kept_cell(): void
    {
        $new = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK A', 'saluran_count' => 2],
        ], false, true);

        $this->assertSame(
            ['SK A|1', 'SK A|2', '|UNDI POS'],
            array_keys($this->svc->survivingKeys($new)),
        );
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=Borang14StrukturServiceTest`
Expected: FAIL — `Class "App\Services\Borang14StrukturService" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Services/Borang14StrukturService.php`:

```php
<?php

namespace App\Services;

/**
 * Logik BENTUK bagi struktur Borang 14 yang dibina dengan tangan.
 *
 * UI berfikir "satu entri per Pusat Mengundi + kiraan saluran"; storan
 * berfikir "satu baris per saluran" (bentuk yang dihasilkan scoresheet dan
 * yang sudah dibaca oleh referenceFromStructure()). Kelas ini satu-satunya
 * tempat kedua-dua pandangan itu bertemu.
 *
 * Tiada DB, tiada HTTP, tiada kebergantungan — supaya keputusan "undi mana
 * berpindah, undi mana dipadam" boleh diuji secara langsung.
 */
class Borang14StrukturService
{
    /**
     * Entri per-Pusat → struktur penuh (satu baris per saluran).
     *
     * Baris yang dihasilkan membawa BENTUK sahaja: row_id, dm, pusat, saluran.
     * Tiada 'a', tiada 'undi', tiada 'jumlah_undian' — tiada sheet bercetak
     * wujud untuk dibaca, dan menulis 0 di situ akan mencipta angka palsu yang
     * kemudian dituduh oleh crosscheck.
     *
     * @param  array<int,array{row_id:string,dm:string,pusat:string,saluran_count:int}>  $pusatList
     * @return array{origin:string,calon:array,rows:array<int,array<string,string>>}
     */
    public function expand(array $pusatList, bool $undiAwal, bool $undiPos): array
    {
        $rows = [];

        foreach ($pusatList as $p) {
            $count = max(1, (int) ($p['saluran_count'] ?? 1));
            for ($i = 1; $i <= $count; $i++) {
                $rows[] = [
                    'row_id'  => (string) $p['row_id'],
                    'dm'      => (string) ($p['dm'] ?? ''),
                    'pusat'   => (string) $p['pusat'],
                    'saluran' => (string) $i,
                ];
            }
        }

        // Baris pusat-kosong, bentuk yang sama seperti keluaran scoresheet —
        // itulah yang referenceFromStructure() sudah tahu baca.
        if ($undiAwal) {
            $rows[] = ['row_id' => 'pm_awal', 'dm' => '', 'pusat' => '', 'saluran' => 'UNDI AWAL'];
        }
        if ($undiPos) {
            $rows[] = ['row_id' => 'pm_pos', 'dm' => '', 'pusat' => '', 'saluran' => 'UNDI POS'];
        }

        return ['origin' => 'manual', 'calon' => [], 'rows' => $rows];
    }

    /**
     * Struktur tersimpan → entri per-Pusat untuk panel penyuntingan.
     *
     * Berfungsi untuk struktur manual DAN struktur scoresheet/warisan; yang
     * kedua tiada row_id, jadi satu id TERBITAN yang stabil (md5 dm|pusat)
     * diberikan. Kestabilan itu penting: kalau id berubah setiap kali panel
     * dibuka, suntingan kedua akan melihat setiap pusat sebagai baharu dan
     * cascade akan memadam undi yang sepatutnya hanya berpindah.
     *
     * @return array{pusat:array<int,array<string,mixed>>,undi_awal:bool,undi_pos:bool}
     */
    public function collapse(?array $structure): array
    {
        $pusat = [];
        $undiAwal = false;
        $undiPos = false;

        foreach ($structure['rows'] ?? [] as $r) {
            $nama = (string) ($r['pusat'] ?? '');

            if ($nama === '') {
                $upper = strtoupper((string) ($r['saluran'] ?? ''));
                $undiAwal = $undiAwal || str_contains($upper, 'AWAL');
                $undiPos = $undiPos || str_contains($upper, 'POS');

                continue;
            }

            $dm = (string) ($r['dm'] ?? '');
            $rowId = (string) ($r['row_id'] ?? '') ?: 'pm_'.md5($dm.'|'.$nama);

            if (! isset($pusat[$rowId])) {
                $pusat[$rowId] = ['row_id' => $rowId, 'dm' => $dm, 'pusat' => $nama, 'saluran_count' => 0];
            }
            $pusat[$rowId]['saluran_count']++;
        }

        return ['pusat' => array_values($pusat), 'undi_awal' => $undiAwal, 'undi_pos' => $undiPos];
    }

    /**
     * Nama lama → nama baharu, dipadan melalui row_id sahaja.
     *
     * Sengaja TIDAK meneka melalui persamaan nama: menamakan semula ialah
     * tepat perkara yang memutuskan persamaan itu.
     *
     * @param  array<int,array{row_id:string,pusat:string}>  $pusatList
     * @return array<string,string>
     */
    public function renameMap(?array $oldStructure, array $pusatList): array
    {
        $lama = [];
        foreach ($this->collapse($oldStructure)['pusat'] as $p) {
            $lama[$p['row_id']] = $p['pusat'];
        }

        $map = [];
        foreach ($pusatList as $p) {
            $id = (string) ($p['row_id'] ?? '');
            $baharu = (string) ($p['pusat'] ?? '');
            if (isset($lama[$id]) && $lama[$id] !== '' && $lama[$id] !== $baharu) {
                $map[$lama[$id]] = $baharu;
            }
        }

        return $map;
    }

    /**
     * Setiap kunci 'PUSAT|SALURAN' yang dikekalkan oleh struktur ini.
     * Undi di luar senarai ini ialah undi yang akan hilang.
     *
     * @return array<string,bool>
     */
    public function survivingKeys(array $structure): array
    {
        $keys = [];
        foreach ($structure['rows'] ?? [] as $r) {
            $keys[((string) ($r['pusat'] ?? '')).'|'.((string) ($r['saluran'] ?? ''))] = true;
        }

        return $keys;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=Borang14StrukturServiceTest`
Expected: PASS — 9 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Borang14StrukturService.php tests/Unit/Borang14StrukturServiceTest.php
git commit -m "Borang 14: perkhidmatan bentuk struktur manual (expand/collapse/rename)"
```

---

### Task 2: Stop the crosscheck from accusing manual forms

**Files:**
- Modify: `app/Http/Controllers/Borang14Controller.php` (`crosscheckIssues()` at ~line 418, `referenceFromStructure()` return at ~line 383)
- Test: `tests/Feature/Borang14StrukturManualTest.php` *(create — first two tests go here; later tasks add to the same file)*

**Interfaces:**
- Consumes: the `origin: 'manual'` marker written by `Borang14StrukturService::expand()` (Task 1).
- Produces: `referenceFromStructure()` now returns `source => 'manual'` for manual structures (was always `'scoresheet'`). The frontend reads `reference.source`.

**Why this task exists:** `crosscheckIssues()` compares live votes against the sheet's printed `a` and `jumlah_undian`. A manual row has neither. Left alone, every filled row would report *"(A) dijangka 0, dapat 250"* — the form would look broken the moment it was used correctly.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Borang14StrukturManualTest.php`:

```php
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
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=Borang14StrukturManualTest`
Expected: FAIL — `test_manual_form_reports_no_crosscheck_issues` reports a non-empty array, and `reference.source` is `'scoresheet'`, not `'manual'`.

- [ ] **Step 3: Write the implementation**

In `app/Http/Controllers/Borang14Controller.php`, inside `crosscheckIssues()`, replace the opening guard:

```php
        $structure = $form->structure;
        if (empty($structure['rows'])) {
            return [];
        }
```

with:

```php
        $structure = $form->structure;
        if (empty($structure['rows'])) {
            return [];
        }

        // Struktur yang dibina dengan tangan tiada baris bercetak untuk
        // dibandingkan — tiada lajur (A), tiada 'jumlah_undian'. Menjalankan
        // validateBalance() ke atasnya akan membandingkan setiap sel dengan
        // sifar yang tidak pernah dicetak sesiapa, lalu menuduh borang yang
        // diisi dengan BETUL sebagai tidak seimbang.
        if (($structure['origin'] ?? null) === 'manual') {
            return [];
        }
```

Then in `referenceFromStructure()`, change the signature and the `source` line so the origin travels with it. Replace:

```php
    private function referenceFromStructure(array $structure, Bandar|Kadun|null $kawasan): array
```

with:

```php
    private function referenceFromStructure(array $structure, Bandar|Kadun|null $kawasan): array
    {
        // Asal-usul mesti dilaporkan dengan jujur ke UI: 'manual' bermakna
        // seorang manusia menaip bentuk ini, bukan SPR yang menggazetkannya.
        $origin = ($structure['origin'] ?? null) === 'manual' ? 'manual' : 'scoresheet';
```

…and delete the now-duplicated `{` on the following line, and change the return array's last entry from:

```php
            'source'    => 'scoresheet',
```

to:

```php
            'source'    => $origin,
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=Borang14StrukturManualTest`
Expected: PASS — 2 tests.

Then confirm nothing regressed in the scoresheet path:

Run: `php artisan test --filter="Borang14Crosscheck|Borang14ReferencePriority|Borang14InheritStructure"`
Expected: PASS — all.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Borang14Controller.php tests/Feature/Borang14StrukturManualTest.php
git commit -m "Borang 14: struktur manual dilaporkan sebagai sumber sendiri, bebas crosscheck"
```

---

### Task 3: `POST /borang-14/struktur` — save, with vote cascade

**Files:**
- Modify: `app/Http/Controllers/Borang14Controller.php` (add `simpanStruktur()` + `bolehSuntingStruktur()`)
- Modify: `routes/web.php` (after the `borang-14.reset` line, ~line 458)
- Test: `tests/Feature/Borang14StrukturManualTest.php` (append)

**Interfaces:**
- Consumes: `Borang14StrukturService::expand()`, `::renameMap()`, `::survivingKeys()` (Task 1).
- Produces: route name `pilihanraya.borang-14.struktur`; JSON response `['ok' => true, 'form_id' => int]`. Task 4 reuses `bolehSuntingStruktur(?User, ?Borang14Form): bool`; Task 7 calls this route.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Borang14StrukturManualTest.php` (inside the class):

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=Borang14StrukturManualTest`
Expected: FAIL — `Route [pilihanraya.borang-14.struktur] not defined.`

- [ ] **Step 3: Add the route**

In `routes/web.php`, immediately after the `borang-14.reset` route (~line 458), add:

```php
    // Struktur yang dibina dengan tangan (Pusat Mengundi + bilangan saluran)
    // untuk PR akan datang yang belum ada scoresheet. Menyunting struktur
    // MEMINDAH atau MEMADAM undi sedia ada, jadi semakan peranan ada dalam
    // pengawal dan borang DITERBITKAN disekat sepenuhnya.
    Route::post('/borang-14/struktur', [\App\Http\Controllers\Borang14Controller::class, 'simpanStruktur'])->name('borang-14.struktur')->middleware('throttle:30,1');
    Route::post('/borang-14/struktur/kesan', [\App\Http\Controllers\Borang14Controller::class, 'kesanStruktur'])->name('borang-14.struktur.kesan')->middleware('throttle:60,1');
```

- [ ] **Step 4: Write the controller implementation**

In `app/Http/Controllers/Borang14Controller.php`, add `use App\Services\Borang14StrukturService;` to the imports, then add these methods after `reset()`:

```php
    /**
     * Simpan struktur (Pusat Mengundi + bilangan saluran) yang dibina dengan tangan.
     *
     * Ini satu-satunya jalan mencipta borang bagi kerusi yang tiada DPT dan
     * tiada scoresheet — firstOrCreate di sini yang memecahkan kebuntuan
     * "Data Borang 14 belum tersedia".
     *
     * Undi dikunci pada `pusat|saluran|slot`, jadi menukar struktur mesti
     * menggerakkan undi bersamanya. Urutan dalam transaksi PENTING:
     * snapshot → namakan semula → padam yatim → simpan struktur. Menamakan
     * semula dahulu bermakna langkah padam boleh menilai satu perkara sahaja:
     * "adakah kunci ini masih wujud dalam struktur baharu?"
     */
    public function simpanStruktur(Request $request, Borang14StrukturService $svc)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'pusat'    => 'present|array|max:500',
            'pusat.*.row_id' => 'required|string|max:64',
            'pusat.*.dm'     => 'nullable|string|max:255',
            'pusat.*.pusat'  => 'required|string|max:255',
            'pusat.*.saluran_count' => 'required|integer|min:1|max:20',
            'undi_awal' => 'boolean',
            'undi_pos'  => 'boolean',
        ]);

        $namaList = collect($validated['pusat'])->pluck('pusat')->map(fn ($n) => trim($n));
        if ($namaList->count() !== $namaList->unique()->count()) {
            // Dua pusat senama berkongsi kunci undi yang sama dan akan menulis
            // atas satu sama lain tanpa sebarang amaran.
            throw ValidationException::withMessages([
                'pusat' => 'Nama Pusat Mengundi mesti unik dalam satu borang.',
            ]);
        }

        $form = Borang14Form::forKawasan($validated['kawasan_type'], $validated['kawasan_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        if (! $this->bolehSuntingStruktur($request->user(), $form, $validated)) {
            abort(403, 'Unauthorized action.');
        }

        $baharu = $svc->expand(
            $validated['pusat'],
            (bool) ($validated['undi_awal'] ?? false),
            (bool) ($validated['undi_pos'] ?? false),
        );

        DB::transaction(function () use (&$form, $validated, $baharu, $svc, $request) {
            $form ??= Borang14Form::create([
                'kawasan_type' => $validated['kawasan_type'],
                'kawasan_id'   => $validated['kawasan_id'],
                'jenis_pr'     => $validated['jenis_pr'],
                'tahun'        => $validated['tahun'],
                'penjuru'      => 2,
                'parties'      => [],
                'status'       => 'draft',
                'source'       => 'manual',
            ]);

            if ($form->wasRecentlyCreated === false) {
                Borang14Snapshot::create([
                    'borang14_form_id' => $form->id,
                    'structure' => $form->structure,
                    'votes' => $form->votes()->get(['pusat', 'saluran', 'slot', 'undi'])->toArray(),
                    'parties' => $form->parties,
                    'reason' => 'before_structure_edit',
                    'created_by' => $request->user()?->id,
                ]);
            }

            foreach ($svc->renameMap($form->structure, $validated['pusat']) as $lama => $kini) {
                $form->votes()->where('pusat', $lama)->update(['pusat' => $kini]);
            }

            $kekal = $svc->survivingKeys($baharu);
            foreach ($form->votes()->get(['id', 'pusat', 'saluran']) as $v) {
                if (! isset($kekal[$v->pusat.'|'.$v->saluran])) {
                    Borang14Vote::whereKey($v->id)->delete();
                }
            }

            $form->update(['structure' => $baharu]);
        });

        return response()->json(['ok' => true, 'form_id' => $form->id]);
    }

    /**
     * Menyunting struktur menggerakkan undi sebenar, jadi ia berkongsi tahap
     * kepercayaan yang sama seperti mengisi undi — admin ke atas — tetapi
     * borang DITERBITKAN disekat sepenuhnya, termasuk untuk super_admin:
     * bentuk rekod rasmi tidak boleh berubah di bawah angka yang sudah
     * disiarkan ke Scoreboard. Revert dahulu, kemudian sunting.
     */
    private function bolehSuntingStruktur(?User $user, ?Borang14Form $form, array $validated): bool
    {
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin())) {
            return false;
        }
        if ($form && $form->status === 'published') {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        $bandarId = $validated['kawasan_type'] === Borang14Form::KAWASAN_PARLIMEN
            ? $validated['kawasan_id']
            : Kadun::whereKey($validated['kawasan_id'])->value('bandar_id');

        return $user->bandar_id !== null && (int) $user->bandar_id === (int) $bandarId;
    }
```

If `ValidationException` is not yet imported, add `use Illuminate\Validation\ValidationException;` to the imports.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=Borang14StrukturManualTest`
Expected: PASS — 11 tests (2 from Task 2 + 9 new).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Borang14Controller.php routes/web.php tests/Feature/Borang14StrukturManualTest.php
git commit -m "Borang 14: endpoint simpan struktur manual dengan cascade undi"
```

---

### Task 4: `POST /borang-14/struktur/kesan` — honest preview before destroying votes

**Files:**
- Modify: `app/Http/Controllers/Borang14Controller.php` (add `kesanStruktur()` after `simpanStruktur()`)
- Test: `tests/Feature/Borang14StrukturManualTest.php` (append)

**Interfaces:**
- Consumes: `bolehSuntingStruktur()` (Task 3), `Borang14StrukturService` (Task 1). Route already registered in Task 3.
- Produces: JSON `['baris' => int, 'undi' => int, 'pusat' => string[]]` — row count, total votes about to be deleted, and the names losing them. Task 6's confirm dialog renders these three fields.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Borang14StrukturManualTest.php`:

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=Borang14StrukturManualTest`
Expected: FAIL — `Method App\Http\Controllers\Borang14Controller::kesanStruktur does not exist.`

- [ ] **Step 3: Write the implementation**

Add after `simpanStruktur()` in `app/Http/Controllers/Borang14Controller.php`:

```php
    /**
     * Pratonton tanpa menulis: berapa baris undi dan berapa jumlah undi yang
     * akan DIPADAM oleh cadangan struktur ini.
     *
     * Wujud supaya dialog pengesahan memaparkan angka sebenar. Amaran kabur
     * ("perubahan ini mungkin menjejaskan data") dibaca sebagai bunyi latar
     * dan diklik terus — angka tidak.
     */
    public function kesanStruktur(Request $request, Borang14StrukturService $svc)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'pusat'    => 'present|array|max:500',
            'pusat.*.row_id' => 'required|string|max:64',
            'pusat.*.dm'     => 'nullable|string|max:255',
            'pusat.*.pusat'  => 'required|string|max:255',
            'pusat.*.saluran_count' => 'required|integer|min:1|max:20',
            'undi_awal' => 'boolean',
            'undi_pos'  => 'boolean',
        ]);

        $form = Borang14Form::forKawasan($validated['kawasan_type'], $validated['kawasan_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        if (! $this->bolehSuntingStruktur($request->user(), $form, $validated)) {
            abort(403, 'Unauthorized action.');
        }

        if (! $form) {
            return response()->json(['baris' => 0, 'undi' => 0, 'pusat' => []]);
        }

        // Sama seperti simpanStruktur(): nilai kunci SELEPAS penamaan semula,
        // supaya pusat yang sekadar bertukar nama tidak dilaporkan sebagai
        // kehilangan.
        $rename = $svc->renameMap($form->structure, $validated['pusat']);
        $kekal = $svc->survivingKeys($svc->expand(
            $validated['pusat'],
            (bool) ($validated['undi_awal'] ?? false),
            (bool) ($validated['undi_pos'] ?? false),
        ));

        $hilang = $form->votes()->get(['pusat', 'saluran', 'undi'])
            ->filter(fn ($v) => ! isset($kekal[($rename[$v->pusat] ?? $v->pusat).'|'.$v->saluran]));

        return response()->json([
            'baris' => $hilang->count(),
            'undi'  => (int) $hilang->sum('undi'),
            'pusat' => $hilang->pluck('pusat')->unique()->values()->all(),
        ]);
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=Borang14StrukturManualTest`
Expected: PASS — 14 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Borang14Controller.php tests/Feature/Borang14StrukturManualTest.php
git commit -m "Borang 14: pratonton kesan suntingan struktur ke atas undi"
```

---

### Task 5: `data()` hands the panel its starting state

**Files:**
- Modify: `app/Http/Controllers/Borang14Controller.php` (`data()`, the `$payload` array at ~line 103)
- Test: `tests/Feature/Borang14StrukturManualTest.php` (append)

**Interfaces:**
- Consumes: `Borang14StrukturService::collapse()` (Task 1), `bolehSuntingStruktur()` (Task 3).
- Produces: two new keys in the `data()` JSON — `struktur` (`{pusat: [...], undi_awal: bool, undi_pos: bool}`) and `boleh_sunting_struktur` (bool). Tasks 6 and 7 read exactly these names.

**Why the server computes this:** the panel must open pre-filled from whatever structure exists — manual, scoresheet, or inherited. Collapsing on the client would mean a second implementation of the row_id derivation rule, and the two would drift.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Borang14StrukturManualTest.php`:

```php
    public function test_data_returns_the_collapsed_structure_for_the_editor(): void
    {
        $form = $this->form($this->manualStructure());

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $form->id]));

        $res->assertOk();
        $this->assertSame(
            [['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 2],
             ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1]],
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
        $res = $this->actingAs($this->user('user'))
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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=Borang14StrukturManualTest`
Expected: FAIL — `struktur.pusat` is null.

- [ ] **Step 3: Write the implementation**

Change the `data()` signature from:

```php
    public function data(Request $request)
```

to:

```php
    public function data(Request $request, Borang14StrukturService $svc)
```

Then in the `$payload` array, immediately after the `'resolved' => array_merge(...)` entry, add:

```php
            // Keadaan permulaan bagi panel Sunting Struktur. Dikira di server
            // supaya peraturan penerbitan row_id (termasuk id terbitan bagi
            // struktur scoresheet/warisan yang tiada satu) hidup di SATU
            // tempat sahaja — dua pelaksanaan akan hanyut, dan hanyut di sini
            // bermakna undi dipadam sebagai ganti dipindahkan.
            'struktur' => $svc->collapse($form?->structure),
            'boleh_sunting_struktur' => $this->bolehSuntingStruktur(
                $request->user(),
                $form,
                ['kawasan_type' => $kawasanType, 'kawasan_id' => $kawasanId],
            ),
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=Borang14StrukturManualTest`
Expected: PASS — 17 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Borang14Controller.php tests/Feature/Borang14StrukturManualTest.php
git commit -m "Borang 14: data() bekalkan keadaan permulaan panel struktur"
```

---

### Task 6: `StrukturPanel.jsx` — the editor

**Files:**
- Create: `resources/js/Pages/Pilihanraya/Borang14/StrukturPanel.jsx`

**Interfaces:**
- Consumes: `data()`'s `struktur` block (Task 5); routes `pilihanraya.borang-14.struktur` and `…struktur.kesan` (Tasks 3–4); existing `ConfirmDialog` and `usePilihanrayaTheme`.
- Produces: default-exported component with props
  `{ picker, struktur, onSaved: () => void, onCancel: () => void }`
  where `picker` is KeyinTab's existing object `{ kawasanType, parlimenId, kadunId, jenisPr, tahun }`.

**Note on `ConfirmDialog`:** read `resources/js/Pages/Pilihanraya/Borang14/ConfirmDialog.jsx` (36 lines) before writing — use its real prop names rather than assuming them.

- [ ] **Step 1: Write the component**

Create `resources/js/Pages/Pilihanraya/Borang14/StrukturPanel.jsx`:

```jsx
import { useMemo, useState } from 'react';
import axios from 'axios';
import { Loader2, Plus, Trash2, X } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import ConfirmDialog from './ConfirmDialog';

// Bentuk sahaja — panel ini tidak tahu apa-apa tentang undi. Undi mana yang
// akan hilang dijawab oleh server (endpoint .kesan), kerana hanya server tahu
// apa yang benar-benar tersimpan.
let seq = 0;
const newRowId = () => `pm_new_${Date.now()}_${seq++}`;

export default function StrukturPanel({ picker, struktur, onSaved, onCancel }) {
    const { t } = usePilihanrayaTheme();
    const [pusat, setPusat] = useState(() => struktur?.pusat ?? []);
    const [undiAwal, setUndiAwal] = useState(Boolean(struktur?.undi_awal));
    const [undiPos, setUndiPos] = useState(Boolean(struktur?.undi_pos));
    const [saving, setSaving] = useState(false);
    const [ralat, setRalat] = useState('');
    const [kesan, setKesan] = useState(null); // { baris, undi, pusat[] }

    const params = useMemo(() => ({
        kawasan_type: picker.kawasanType,
        kawasan_id: picker.kawasanType === 'parlimen' ? picker.parlimenId : picker.kadunId,
        jenis_pr: picker.jenisPr,
        tahun: picker.tahun,
    }), [picker]);

    // Dikumpul mengikut Daerah Mengundi untuk paparan sahaja — DM ialah medan
    // teks pada setiap pusat, bukan entiti berasingan.
    const kumpulan = useMemo(() => {
        const out = new Map();
        pusat.forEach((p, i) => {
            const dm = p.dm || '';
            if (!out.has(dm)) out.set(dm, []);
            out.get(dm).push({ ...p, i });
        });
        return [...out.entries()];
    }, [pusat]);

    const ubah = (i, patch) => setPusat((prev) => prev.map((p, j) => (j === i ? { ...p, ...patch } : p)));
    const buang = (i) => setPusat((prev) => prev.filter((_, j) => j !== i));
    const tambahPusat = (dm) => setPusat((prev) => [...prev, { row_id: newRowId(), dm, pusat: '', saluran_count: 1 }]);

    const payload = () => ({
        ...params,
        pusat: pusat.map((p) => ({
            row_id: p.row_id,
            dm: (p.dm || '').trim(),
            pusat: (p.pusat || '').trim(),
            saluran_count: Math.max(1, Math.min(20, Number(p.saluran_count) || 1)),
        })),
        undi_awal: undiAwal,
        undi_pos: undiPos,
    });

    const semak = async () => {
        setRalat('');
        const kosong = pusat.some((p) => !(p.pusat || '').trim());
        if (kosong) { setRalat('Setiap Pusat Mengundi mesti bernama.'); return; }

        const nama = pusat.map((p) => (p.pusat || '').trim());
        if (new Set(nama).size !== nama.length) { setRalat('Nama Pusat Mengundi mesti unik.'); return; }

        setSaving(true);
        try {
            const { data } = await axios.post(route('pilihanraya.borang-14.struktur.kesan'), payload());
            // Tiada undi terjejas → simpan terus; ada → paksa pengesahan
            // berangka dahulu.
            if (data.baris > 0) { setKesan(data); setSaving(false); return; }
            await simpan();
        } catch (e) {
            setRalat(e?.response?.data?.message || 'Gagal menyemak kesan perubahan.');
            setSaving(false);
        }
    };

    const simpan = async () => {
        setSaving(true);
        setKesan(null);
        try {
            await axios.post(route('pilihanraya.borang-14.struktur'), payload());
            onSaved();
        } catch (e) {
            setRalat(e?.response?.data?.message || 'Gagal menyimpan struktur.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className={t.card}>
            <div className="flex items-center justify-between mb-3">
                <h3 className="font-semibold">Struktur Borang 14</h3>
                <button type="button" onClick={onCancel} className={t.btnGhost} aria-label="Tutup">
                    <X className="h-4 w-4" />
                </button>
            </div>

            <p className={`${t.subtext} text-sm mb-4`}>
                Senaraikan Pusat Mengundi dan bilangan saluran bagi setiap satu. Bilangan
                pengundi berdaftar tidak diminta di sini — ia tidak diketahui bagi pilihan
                raya akan datang, dan angka yang tidak diketahui dibiarkan kosong.
            </p>

            {kumpulan.map(([dm, senarai]) => (
                <div key={dm} className="mb-4">
                    <div className="flex items-center justify-between mb-1">
                        <div className={`text-xs font-semibold uppercase ${t.subtext}`}>
                            {dm || 'Tanpa Daerah Mengundi'}
                        </div>
                        <button type="button" onClick={() => tambahPusat(dm)} className={t.btnGhost}>
                            <Plus className="h-3.5 w-3.5" /> Pusat
                        </button>
                    </div>

                    {senarai.map((p) => (
                        <div key={p.row_id} className="flex flex-wrap items-center gap-2 mb-1.5">
                            <input
                                value={p.pusat}
                                onChange={(e) => ubah(p.i, { pusat: e.target.value })}
                                placeholder="Nama Pusat Mengundi"
                                className={`${t.input} flex-1 min-w-[200px]`}
                            />
                            <input
                                value={p.dm}
                                onChange={(e) => ubah(p.i, { dm: e.target.value })}
                                placeholder="Daerah Mengundi"
                                className={`${t.input} w-44`}
                            />
                            <label className={`${t.subtext} text-xs`}>Saluran</label>
                            <input
                                type="number" min="1" max="20"
                                value={p.saluran_count}
                                onChange={(e) => ubah(p.i, { saluran_count: e.target.value })}
                                className={`${t.input} w-20`}
                            />
                            <button type="button" onClick={() => buang(p.i)} className={t.btnGhost} aria-label="Buang pusat">
                                <Trash2 className="h-4 w-4 text-red-600" />
                            </button>
                        </div>
                    ))}
                </div>
            ))}

            <button type="button" onClick={() => tambahPusat('')} className={`${t.btnSecondary} mb-4`}>
                <Plus className="h-4 w-4" /> Tambah Daerah Mengundi
            </button>

            <div className="flex items-center gap-6 mb-4 text-sm">
                <label className="flex items-center gap-2">
                    <input type="checkbox" checked={undiAwal} onChange={(e) => setUndiAwal(e.target.checked)} />
                    UNDI AWAL
                </label>
                <label className="flex items-center gap-2">
                    <input type="checkbox" checked={undiPos} onChange={(e) => setUndiPos(e.target.checked)} />
                    UNDI POS
                </label>
            </div>

            {ralat && <div className="text-sm text-red-600 mb-3">{ralat}</div>}

            <div className="flex justify-end gap-2">
                <button type="button" onClick={onCancel} className={t.btnSecondary}>Batal</button>
                <button type="button" onClick={semak} disabled={saving} className={t.btnPrimary}>
                    {saving && <Loader2 className="h-4 w-4 animate-spin" />} Simpan Struktur
                </button>
            </div>

            {kesan && (
                <ConfirmDialog
                    title="Undi akan dipadam"
                    message={
                        `Perubahan ini akan memadam ${kesan.baris} baris undi ` +
                        `(jumlah ${kesan.undi.toLocaleString('ms-MY')} undi) daripada: ` +
                        `${kesan.pusat.join(', ')}. Teruskan?`
                    }
                    confirmLabel="Ya, teruskan"
                    onConfirm={simpan}
                    onCancel={() => setKesan(null)}
                />
            )}
        </div>
    );
}
```

- [ ] **Step 2: Reconcile with the real `ConfirmDialog` and theme keys**

Run: `cat resources/js/Pages/Pilihanraya/Borang14/ConfirmDialog.jsx`
Then: `grep -n "btnPrimary\|btnSecondary\|btnGhost\|card\b" resources/js/Pages/Pilihanraya/components/PilihanrayaShell.jsx`

Fix the prop names and theme keys used above to match what actually exists. Do not invent new theme keys.

- [ ] **Step 3: Build to verify it compiles**

Run: `npm run build`
Expected: build succeeds, no unresolved-import errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Pilihanraya/Borang14/StrukturPanel.jsx
git commit -m "Borang 14: panel penyuntingan struktur"
```

---

### Task 7: Wire the panel into `KeyinTab`

**Files:**
- Modify: `resources/js/Pages/Pilihanraya/Borang14/KeyinTab.jsx` (imports ~line 5; state ~line 26; banner ~line 332; render ~line 281)

**Interfaces:**
- Consumes: `StrukturPanel` (Task 6); `data()`'s `struktur` and `boleh_sunting_struktur` (Task 5).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Add the import**

After the `import KawasanPicker from './KawasanPicker';` line, add:

```jsx
import StrukturPanel from './StrukturPanel';
```

- [ ] **Step 2: Add state**

After the `const [inheritedFrom, setInheritedFrom] = useState(null);` block, add:

```jsx
    // Keadaan panel Sunting Struktur. `struktur` sentiasa datang dari server —
    // jangan sekali-kali kira semula di client, kerana peraturan row_id yang
    // hanyut bermakna undi dipadam sebagai ganti dipindahkan.
    const [struktur, setStruktur] = useState({ pusat: [], undi_awal: false, undi_pos: false });
    const [bolehSuntingStruktur, setBolehSuntingStruktur] = useState(false);
    const [suntingStruktur, setSuntingStruktur] = useState(false);
```

- [ ] **Step 3: Capture the new fields in both fetch paths**

There are two places that call `setHasData(data.hasData)` (~line 70 and ~line 109). Immediately after **each** of them, add:

```jsx
                    setStruktur(data.struktur || { pusat: [], undi_awal: false, undi_pos: false });
                    setBolehSuntingStruktur(Boolean(data.boleh_sunting_struktur));
```

Match the surrounding indentation at each site.

- [ ] **Step 4: Replace the dead-end banner with a call to action**

Replace this block (~line 332):

```jsx
            {geographyComplete && !hasData && !loading && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Data Borang 14 (saluran &amp; pengundi berdaftar) belum tersedia untuk kawasan ini.</span>
                </div>
            )}
```

with:

```jsx
            {geographyComplete && !hasData && !loading && !suntingStruktur && (
                <div className={`${t.banner} flex flex-wrap items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>
                        Tiada struktur Borang 14 untuk kawasan ini — tiada data DPT dan tiada
                        scoresheet tahun lepas untuk diwarisi.
                    </span>
                    {bolehSuntingStruktur && (
                        <button type="button" onClick={() => setSuntingStruktur(true)} className={t.btnPrimary}>
                            Cipta Borang 14 kosong
                        </button>
                    )}
                </div>
            )}
```

- [ ] **Step 5: Add the edit button and render the panel**

Immediately after the closing `</div>` of the filters card (the one that closes `{/* Filters */}`, just before the `{/* Note when geography incomplete */}` comment), add:

```jsx
            {geographyComplete && hasData && bolehSuntingStruktur && !suntingStruktur && (
                <div className="flex justify-end mb-3">
                    <button type="button" onClick={() => setSuntingStruktur(true)} className={t.btnSecondary}>
                        Sunting Struktur
                    </button>
                </div>
            )}

            {suntingStruktur && (
                <StrukturPanel
                    picker={picker}
                    struktur={struktur}
                    onCancel={() => setSuntingStruktur(false)}
                    onSaved={() => { setSuntingStruktur(false); setReloadNonce((n) => n + 1); }}
                />
            )}
```

The data fetch lives inline in the `useEffect` at line ~97 (the one keyed on
`[geographyComplete, kawasanType, kawasanId, jenisPr, tahun]`). Do **not** extract or
duplicate it — add a nonce instead. With the other state in Step 2, add:

```jsx
    const [reloadNonce, setReloadNonce] = useState(0);
```

and append `reloadNonce` to that effect's dependency array:

```jsx
    }, [geographyComplete, kawasanType, kawasanId, jenisPr, tahun, reloadNonce]); // eslint-disable-line react-hooks/exhaustive-deps
```

- [ ] **Step 6: Suppress the grid while editing**

Find `const canShowTables = geographyComplete && hasData && penjuru && blocks.length > 0;` (~line 279) and change it to:

```jsx
    const canShowTables = geographyComplete && hasData && penjuru && blocks.length > 0 && !suntingStruktur;
```

- [ ] **Step 7: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Pilihanraya/Borang14/KeyinTab.jsx
git commit -m "Borang 14: butang Sunting Struktur dan Cipta Borang 14 kosong"
```

---

### Task 8: Warn that a scoresheet upload overwrites a manual structure, then verify the whole suite

**Files:**
- Modify: `resources/js/Pages/Pilihanraya/Borang14/UploadTab.jsx`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing.

**Why:** `writeForm()` overwrites `structure` unconditionally. That is the correct priority — an official scoresheet outranks a typed structure — and `writeForm()` already snapshots first, so it is recoverable. The user simply must not be surprised by it.

- [ ] **Step 1: Find the dry-run confirmation area**

Run: `grep -n "Sahkan\|Simpan\|dry\|semak" resources/js/Pages/Pilihanraya/Borang14/UploadTab.jsx | head -20`

- [ ] **Step 2: Add the notice**

In the block rendered once a dry-run result exists, immediately above the confirm button, add:

```jsx
                <div className={`${t.subtext} text-xs mb-2`}>
                    Nota: jika borang ini mempunyai struktur yang ditaip tangan, scoresheet akan
                    menggantikannya. Keadaan lama disimpan dan boleh dipulihkan melalui Revert.
                </div>
```

Match the surrounding indentation and use the theme keys that file already uses.

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Run the full suite and confirm the baseline did not grow**

Run: `php artisan test`
Expected: **20 failed / 368 passed**. That is the 342-passed baseline plus the 26 tests this
plan adds: 9 unit (Task 1) + 2 (Task 2) + 9 (Task 3) + 3 (Task 4) + 3 (Task 5).
The 20 failures must be the same pre-existing `UserFactory` telephone ones. If the failure
count is anything other than 20, stop and fix before committing.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Pilihanraya/Borang14/UploadTab.jsx
git commit -m "Borang 14: nota amaran scoresheet menggantikan struktur manual"
```

---

## Two spec tests covered by existing suites, deliberately not rewritten

- **"Manual structure beats the DPT estimate"** (spec §Bahagian 3, item 5). Manual structures
  travel the *same* `resolveReference()` branch as scoresheet structures, which
  `tests/Feature/Borang14ReferencePriorityTest.php` already pins with real roll fixtures.
  Task 2 Step 4 runs that file explicitly. Duplicating the roll fixture setup here would
  test the fixture, not the feature.
- **"Revert restores the old structure and votes"** (item 8). Task 3 pins the half that is
  new — that a `before_structure_edit` snapshot is written with the pre-edit votes intact.
  `revert()` itself is untouched existing code with its own coverage.

---

## Manual verification before merge

Local, with `php artisan serve` + a seeded geography:

1. Pick a DUN with no DPT and no scoresheet, jenis PR `PRN`, a future year. The banner must offer **Cipta Borang 14 kosong**.
2. Add two Pusat Mengundi, one with 3 saluran. Save. The grid must render 3 saluran rows for that Pusat.
3. Enter a vote in saluran 3, reload the page. **The figure must still be there** — this is the anti-orphan check by hand.
4. Reopen the panel, rename that Pusat, save. The figure must follow the new name.
5. Reopen, delete that Pusat. The confirm dialog must quote the real row count and vote total. Confirm, then check the other Pusat is untouched.
6. Publish the form, reload. The **Sunting Struktur** button must be gone.
