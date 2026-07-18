# Analisa guna Borang 14 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Benarkan senario "Perbandingan Senario AI" diambil terus dari Borang 14 sedia ada, tanpa upload scoresheet semula.

**Architecture:** Satu mapper menukar `Borang14Form` (per saluran, slot bernombor) kepada bentuk `AnalisaScenario` sedia ada (per Daerah Mengundi, kunci nama parti). `ElectionComparisonService` dan prompt AI **tidak disentuh** — mereka tidak tahu dari mana senario datang.

**Tech Stack:** Laravel 11, Inertia 2, React 18, Tailwind 3, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-17-analisa-guna-borang14-design.md`

## Global Constraints

- Branch `feature/analisa-guna-borang14` sudah checked out — jangan rancang langkah branch.
- `.gitignore` ada `*.md` — **jangan** commit fail .md. Repo **menjejaki** `public/build` — jalankan `npm run build` dan commit asetnya.
- Semua teks user-facing dalam **Bahasa Melayu**.
- **JANGAN** ubah `ElectionComparisonService`, prompt AI, atau `ComparisonResult.jsx`.
- **JANGAN** ubah laluan upload sedia ada (`storeScenario()`).
- **JANGAN** ubah skema — tiada migration diperlukan.
- `pemilih` tidak diketahui mesti `null`, **bukan 0**. Sifar menegaskan tiada sesiapa berdaftar — itu penipuan.
- Slot 90 = `(C)` ditolak, slot 91 = `(D)` tidak dimasukkan. **Slot 90/91 tidak boleh muncul sebagai parti.**
- `keluar` = `jumlah undi parti + ditolak` (ikut konvensyen `ScoresheetExtractor` supaya senario Borang 14 dan upload boleh dibanding adil). `(D)` tidak diwakili.
- Had 3 senario setiap comparison kekal.
- Ujian: `php artisan test --filter=Analisa`.

## Bentuk data sasaran (sedia ada — jangan ubah)

```php
parsed_rows = [
    ['kawasan' => 'KAMPONG TENGKEK', 'pemilih' => 2110, 'keluar' => 1655,
     'ditolak' => 19, 'undi' => ['PAKATAN HARAPAN' => 900, 'PERIKATAN NASIONAL' => 700]],
]
parsed_totals = [
    'pemilih' => 13408, 'keluar' => 9107, 'ditolak' => 87,
    'undi' => ['PAKATAN HARAPAN' => 4549, 'PERIKATAN NASIONAL' => 4471],
    'parties' => ['PAKATAN HARAPAN', 'PERIKATAN NASIONAL'],
]
```

## Angka penerimaan — Juasseh PRN 2023

```
11 DM + 1 baris UNDI POS       = 12 baris
totals.keluar   = 9020 + 87    = 9107
totals.ditolak  = 87
totals.pemilih  = 13408        (dari kepala scoresheet)
rows[*].pemilih = null         (kerusi bersumber scoresheet, tiada berdaftar)
UNDI POS: keluar = 98+73+18 = 189, ditolak = 18
slot 90/91 TIDAK muncul dalam mana-mana kunci undi
```

---

### Task 1: `Borang14ScenarioMapper`

**Files:**
- Create: `app/Services/Pilihanraya/Borang14ScenarioMapper.php`
- Test: `tests/Unit/Borang14ScenarioMapperTest.php`

**Interfaces:**
- Produces: `Borang14ScenarioMapper::map(Borang14Form $form): array` → `['rows' => array, 'totals' => array]`. Baling `RuntimeException` dengan mesej BM bila tidak boleh dipeta.
- Consumes: `App\Models\Borang14Form` (relasi `votes()`, medan `parties`, `structure`, `kawasan_type`, `kawasan_id`), `App\Support\Borang14Reference::forKadun()` / `forBandar()`.

- [ ] **Step 1: Baca kod yang menentukan bentuk sasaran**

Run: `sed -n '77,120p' app/Http/Controllers/AnalisaComparisonController.php` dan `sed -n '204,257p' app/Services/Pilihanraya/ElectionComparisonService.php`

Tujuan: sahkan sendiri kunci `parsed_rows`/`parsed_totals` yang dibaca, sebelum menulis mapper. Jangan percaya ringkasan sahaja.

- [ ] **Step 2: Tulis ujian yang gagal**

```php
<?php
// tests/Unit/Borang14ScenarioMapperTest.php
namespace Tests\Unit;

use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Services\Pilihanraya\Borang14ScenarioMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14ScenarioMapperTest extends TestCase
{
    use RefreshDatabase;

    /** Bina borang bersumber scoresheet yang meniru Juasseh PRN 2023. */
    private function juassehForm(): Borang14Form
    {
        $negeri = \App\Models\Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = \App\Models\Bandar::create(['nama' => 'P.129', 'negeri_id' => $negeri->id]);
        $kadun  = \App\Models\Kadun::create(['nama' => 'JUASSEH', 'bandar_id' => $bandar->id]);

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'scoresheet',
            'parties' => [
                ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'PERIKATAN NASIONAL'],
                ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PAKATAN HARAPAN'],
            ],
            'structure' => [
                'jumlah_pemilih' => 13408,
                'rows' => [
                    ['dm' => null, 'pusat' => '', 'saluran' => 'UNDI POS'],
                    ['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SK TENGKEK', 'saluran' => '1'],
                    ['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SK TENGKEK', 'saluran' => '2'],
                    ['dm' => 'KAMPONG TAPAK', 'pusat' => 'SK TAPAK', 'saluran' => '1'],
                ],
            ],
        ]);

        $cells = [
            // [pusat, saluran, slot, undi]
            ['',          'UNDI POS', 1, 98], ['',          'UNDI POS', 2, 73], ['',          'UNDI POS', 90, 18], ['', 'UNDI POS', 91, 14],
            ['SK TENGKEK', '1', 1, 48], ['SK TENGKEK', '1', 2, 76], ['SK TENGKEK', '1', 90, 3],
            ['SK TENGKEK', '2', 1, 102], ['SK TENGKEK', '2', 2, 108], ['SK TENGKEK', '2', 90, 1],
            ['SK TAPAK',   '1', 1, 42], ['SK TAPAK',   '1', 2, 51], ['SK TAPAK',   '1', 90, 0],
        ];
        foreach ($cells as [$pusat, $saluran, $slot, $undi]) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id, 'pusat' => $pusat,
                'saluran' => $saluran, 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        return $form->fresh();
    }

    public function test_maps_per_daerah_mengundi_not_per_saluran(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());

        $kawasan = array_column($out['rows'], 'kawasan');
        sort($kawasan);
        $this->assertSame(['KAMPONG TAPAK', 'KAMPONG TENGKEK', 'UNDI POS'], $kawasan);

        // Tengkek = dua saluran dijumlahkan: PN 48+102=150, PH 76+108=184, ditolak 3+1=4
        $tengkek = collect($out['rows'])->firstWhere('kawasan', 'KAMPONG TENGKEK');
        $this->assertSame(150, $tengkek['undi']['PERIKATAN NASIONAL']);
        $this->assertSame(184, $tengkek['undi']['PAKATAN HARAPAN']);
        $this->assertSame(4, $tengkek['ditolak']);
        $this->assertSame(150 + 184 + 4, $tengkek['keluar']);
    }

    public function test_undi_pos_is_its_own_row(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());
        $pos = collect($out['rows'])->firstWhere('kawasan', 'UNDI POS');

        $this->assertNotNull($pos);
        $this->assertSame(98, $pos['undi']['PERIKATAN NASIONAL']);
        $this->assertSame(73, $pos['undi']['PAKATAN HARAPAN']);
        $this->assertSame(18, $pos['ditolak']);
        $this->assertSame(98 + 73 + 18, $pos['keluar']);
    }

    public function test_slots_90_and_91_never_become_parties(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());

        foreach ($out['rows'] as $r) {
            $this->assertSame(['PERIKATAN NASIONAL', 'PAKATAN HARAPAN'], array_keys($r['undi']));
        }
        $this->assertSame(['PERIKATAN NASIONAL', 'PAKATAN HARAPAN'], $out['totals']['parties']);
    }

    public function test_pemilih_is_null_when_no_berdaftar_is_known(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());

        foreach ($out['rows'] as $r) {
            $this->assertNull($r['pemilih'], 'Scoresheet tiada berdaftar per DM — mesti null, bukan 0.');
        }
    }

    public function test_totals_pemilih_comes_from_scoresheet_header(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());
        $this->assertSame(13408, $out['totals']['pemilih']);
    }

    public function test_totals_sum_every_row(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());

        // PN 98+48+102+42 = 290 ; PH 73+76+108+51 = 308 ; ditolak 18+3+1+0 = 22
        $this->assertSame(290, $out['totals']['undi']['PERIKATAN NASIONAL']);
        $this->assertSame(308, $out['totals']['undi']['PAKATAN HARAPAN']);
        $this->assertSame(22, $out['totals']['ditolak']);
        $this->assertSame(290 + 308 + 22, $out['totals']['keluar']);
    }

    public function test_form_with_no_party_names_is_rejected(): void
    {
        $form = $this->juassehForm();
        $form->update(['parties' => [['slot' => 1, 'keahlian_parti_id' => null, 'nama' => null]]]);

        $this->expectException(\RuntimeException::class);
        app(Borang14ScenarioMapper::class)->map($form->fresh());
    }

    public function test_form_with_no_structure_is_rejected(): void
    {
        $form = $this->juassehForm();
        $form->update(['structure' => null]);

        $this->expectException(\RuntimeException::class);
        app(Borang14ScenarioMapper::class)->map($form->fresh());
    }
}
```

- [ ] **Step 3: Jalankan ujian, sahkan ia GAGAL**

Run: `php artisan test --filter=Borang14ScenarioMapperTest`
Expected: FAIL — `Class "App\Services\Pilihanraya\Borang14ScenarioMapper" not found`

- [ ] **Step 4: Tulis mapper**

```php
<?php
// app/Services/Pilihanraya/Borang14ScenarioMapper.php
namespace App\Services\Pilihanraya;

use App\Models\Borang14Form;
use App\Support\Borang14Reference;
use RuntimeException;

/**
 * Tukar satu Borang14Form kepada bentuk AnalisaScenario sedia ada.
 *
 * Borang 14 simpan per saluran dengan slot bernombor; Analisa mahu per Daerah
 * Mengundi dengan nama parti sebagai kunci. Mapper ini satu-satunya tempat
 * penukaran itu berlaku — ElectionComparisonService tidak tahu senario datang
 * dari mana.
 */
class Borang14ScenarioMapper
{
    private const SLOT_DITOLAK = 90;
    private const SLOT_TIDAK_DIMASUKKAN = 91;

    /** @return array{rows: array<int, array>, totals: array} */
    public function map(Borang14Form $form): array
    {
        $parties = $this->partyNames($form);
        $pusatToDm = $this->pusatToDm($form);
        $berdaftar = $this->berdaftarPerDm($form);

        // Kumpul: DM => ['undi' => [nama => n], 'ditolak' => n]
        $groups = [];
        foreach ($form->votes as $v) {
            $dm = $v->pusat === ''
                ? trim((string) $v->saluran)          // baris peringkat DUN: UNDI POS / UNDI AWAL
                : ($pusatToDm[$v->pusat] ?? null);

            if ($dm === null || $dm === '') {
                continue;                              // pusat tidak dikenali — jangan reka DM
            }

            $groups[$dm] ??= ['undi' => array_fill_keys(array_values($parties), 0), 'ditolak' => 0];

            if ($v->slot === self::SLOT_DITOLAK) {
                $groups[$dm]['ditolak'] += (int) $v->undi;
            } elseif ($v->slot === self::SLOT_TIDAK_DIMASUKKAN) {
                // (D) tiada tempat dalam model Analisa — sengaja diabaikan.
            } elseif (isset($parties[$v->slot])) {
                $groups[$dm]['undi'][$parties[$v->slot]] += (int) $v->undi;
            }
        }

        if ($groups === []) {
            throw new RuntimeException('Borang 14 ini tiada undi untuk dipetakan.');
        }

        $rows = [];
        foreach ($groups as $dm => $g) {
            $rows[] = [
                'kawasan' => $dm,
                'pemilih' => $berdaftar[$dm] ?? null,   // null, BUKAN 0 — lihat spec
                'keluar'  => array_sum($g['undi']) + $g['ditolak'],
                'ditolak' => $g['ditolak'],
                'undi'    => $g['undi'],
            ];
        }

        return ['rows' => $rows, 'totals' => $this->totals($rows, $form, $parties)];
    }

    /** @return array<int, string> slot => nama parti */
    private function partyNames(Borang14Form $form): array
    {
        $names = [];
        foreach (($form->parties ?? []) as $p) {
            $nama = trim((string) ($p['nama'] ?? ''));
            if ($nama !== '' && isset($p['slot'])) {
                $names[(int) $p['slot']] = mb_strtoupper($nama);
            }
        }

        if ($names === []) {
            throw new RuntimeException('Borang 14 ini belum ada nama parti. Petakan parti di tab Keyin dahulu.');
        }

        return $names;
    }

    /** @return array<string, string> nama Pusat Mengundi => nama Daerah Mengundi */
    private function pusatToDm(Borang14Form $form): array
    {
        $map = [];

        foreach (($form->structure['rows'] ?? []) as $r) {
            $pusat = trim((string) ($r['pusat'] ?? ''));
            $dm = trim((string) ($r['dm'] ?? ''));
            if ($pusat !== '' && $dm !== '') {
                $map[$pusat] = $dm;
            }
        }

        if ($map === []) {
            $ref = $form->kawasan_type === Borang14Form::KAWASAN_PARLIMEN
                ? Borang14Reference::forBandar((int) $form->kawasan_id)
                : Borang14Reference::forKadun((int) $form->kawasan_id);

            foreach (($ref['daerah_mengundi'] ?? []) as $dm) {
                foreach (($dm['pusat_mengundi'] ?? []) as $pm) {
                    $map[(string) $pm['nama']] = (string) $dm['nama'];
                }
            }
        }

        if ($map === []) {
            throw new RuntimeException('Struktur saluran tiada untuk Borang 14 ini.');
        }

        return $map;
    }

    /** @return array<string, int> nama DM => jumlah berdaftar (hanya bila diketahui) */
    private function berdaftarPerDm(Borang14Form $form): array
    {
        $ref = $form->kawasan_type === Borang14Form::KAWASAN_PARLIMEN
            ? Borang14Reference::forBandar((int) $form->kawasan_id)
            : Borang14Reference::forKadun((int) $form->kawasan_id);

        $out = [];
        foreach (($ref['daerah_mengundi'] ?? []) as $dm) {
            $jumlah = 0;
            $ada = false;
            foreach (($dm['pusat_mengundi'] ?? []) as $pm) {
                foreach (($pm['saluran'] ?? []) as $s) {
                    if (isset($s['berdaftar']) && $s['berdaftar'] !== null) {
                        $jumlah += (int) $s['berdaftar'];
                        $ada = true;
                    }
                }
            }
            if ($ada) {
                $out[(string) $dm['nama']] = $jumlah;
            }
        }

        return $out;
    }

    private function totals(array $rows, Borang14Form $form, array $parties): array
    {
        $undi = array_fill_keys(array_values($parties), 0);
        $ditolak = 0;
        $pemilih = 0;
        $semuaPemilihDiketahui = true;

        foreach ($rows as $r) {
            foreach ($r['undi'] as $nama => $n) {
                $undi[$nama] += $n;
            }
            $ditolak += $r['ditolak'];
            if ($r['pemilih'] === null) {
                $semuaPemilihDiketahui = false;
            } else {
                $pemilih += $r['pemilih'];
            }
        }

        // Bila berdaftar per DM tiada, guna JUMLAH PEMILIH dari kepala scoresheet.
        // Angka itu benar; jangan reka apa-apa selain itu.
        $totalPemilih = $semuaPemilihDiketahui
            ? $pemilih
            : (isset($form->structure['jumlah_pemilih']) ? (int) $form->structure['jumlah_pemilih'] : null);

        return [
            'pemilih' => $totalPemilih,
            'keluar'  => array_sum($undi) + $ditolak,
            'ditolak' => $ditolak,
            'undi'    => $undi,
            'parties' => array_values($parties),
        ];
    }
}
```

- [ ] **Step 5: Jalankan ujian, sahkan LULUS**

Run: `php artisan test --filter=Borang14ScenarioMapperTest`
Expected: PASS — 8 tests

- [ ] **Step 6: Commit**

```bash
git add app/Services/Pilihanraya/Borang14ScenarioMapper.php tests/Unit/Borang14ScenarioMapperTest.php
git commit -m "Analisa: mapper Borang 14 -> bentuk senario"
```

---

### Task 2: Endpoint senarai + cipta senario dari Borang 14

**Files:**
- Modify: `app/Http/Controllers/AnalisaComparisonController.php`
- Modify: `routes/web.php` (kumpulan `pilihanraya.analisa.comparisons.*`, sekitar baris 425-433)
- Test: `tests/Feature/AnalisaBorang14ScenarioTest.php`

**Interfaces:**
- Consumes: `Borang14ScenarioMapper::map(Borang14Form): array` dari Task 1.
- Produces:
  - `GET pilihanraya.analisa.comparisons.borang14` → `{forms: [{id, label, tahun, jenis_pr, status, penjuru, sedia}]}`
  - `POST pilihanraya.analisa.comparisons.scenarios.borang14` body `{form_id}` → respons sama dengan `storeScenario()` sedia ada (`{comparison: ...}`)

- [ ] **Step 1: Baca `storeScenario()` dan `comparisonPayload()` sepenuhnya**

Run: `sed -n '77,125p;183,214p' app/Http/Controllers/AnalisaComparisonController.php`

Tujuan: laluan baharu mesti mengikut kelakuan sedia ada — had 3 senario, `position` berikutnya, `status => 'draft'`, dan respons yang sama.

- [ ] **Step 2: Tulis ujian yang gagal**

```php
<?php
// tests/Feature/AnalisaBorang14ScenarioTest.php
namespace Tests\Feature;

use App\Models\AnalisaComparison;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaBorang14ScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        // UserFactory tidak set telephone (NOT NULL) — pepijat sedia ada, luar skop.
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123456789']);
    }

    private function seedSeat(string $dunNama = 'JUASSEH'): array
    {
        $negeri = \App\Models\Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = \App\Models\Bandar::create(['nama' => 'P.129', 'negeri_id' => $negeri->id]);
        $kadun  = \App\Models\Kadun::create(['nama' => $dunNama, 'bandar_id' => $bandar->id]);

        return [$negeri, $bandar, $kadun];
    }

    private function form(\App\Models\Kadun $kadun): Borang14Form
    {
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'scoresheet',
            'parties' => [
                ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'PERIKATAN NASIONAL'],
                ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PAKATAN HARAPAN'],
            ],
            'structure' => [
                'jumlah_pemilih' => 13408,
                'rows' => [['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SK TENGKEK', 'saluran' => '1']],
            ],
        ]);
        foreach ([[1, 48], [2, 76], [90, 3]] as [$slot, $undi]) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK',
                'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        return $form->fresh();
    }

    private function comparison(\App\Models\Bandar $bandar, \App\Models\Kadun $kadun): AnalisaComparison
    {
        return AnalisaComparison::create([
            'title' => 'Ujian', 'level' => 'dun',
            'bandar_id' => $bandar->id, 'kadun_id' => $kadun->id,
            'negeri' => 'NEGERI SEMBILAN', 'parlimen' => 'P.129', 'dun' => $kadun->nama,
        ]);
    }

    public function test_lists_only_forms_for_the_comparison_seat(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $this->form($kadun);

        // borang kerusi lain — tidak boleh muncul
        $lain = \App\Models\Kadun::create(['nama' => 'LAIN', 'bandar_id' => $bandar->id]);
        $this->form($lain);

        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.analisa.comparisons.borang14', $c));

        $res->assertOk()->assertJsonCount(1, 'forms');
        $this->assertSame(2023, $res->json('forms.0.tahun'));
    }

    public function test_creates_scenario_from_a_borang14_form(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $form = $this->form($kadun);
        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), [
                'form_id' => $form->id,
            ]);

        $res->assertOk();
        $this->assertSame(1, $c->scenarios()->count());

        $s = $c->scenarios()->first();
        $this->assertSame('PRN 2023', $s->label);
        $this->assertSame('2023-01-01', $s->election_date->format('Y-m-d'));
        $this->assertSame(127, $s->parsed_totals['keluar']);          // 48 + 76 + 3
        $this->assertSame(3, $s->parsed_totals['ditolak']);
        $this->assertSame(48, $s->parsed_totals['undi']['PERIKATAN NASIONAL']);
        $this->assertStringContainsString('Borang 14', $s->source_filename);
    }

    public function test_rejects_a_form_belonging_to_another_seat(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $lain = \App\Models\Kadun::create(['nama' => 'LAIN', 'bandar_id' => $bandar->id]);
        $formLain = $this->form($lain);
        $c = $this->comparison($bandar, $kadun);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), [
                'form_id' => $formLain->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $c->scenarios()->count());
    }

    public function test_enforces_the_three_scenario_limit(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $c = $this->comparison($bandar, $kadun);

        foreach ([2020, 2021, 2022] as $i => $tahun) {
            $f = $this->form($kadun);
            $f->update(['tahun' => $tahun]);
            $c->scenarios()->create([
                'position' => $i + 1, 'label' => "PRN {$tahun}",
                'election_date' => "{$tahun}-01-01",
                'parsed_rows' => [], 'parsed_totals' => [], 'row_count' => 0,
            ]);
        }

        $form = $this->form($kadun);
        $form->update(['tahun' => 2023]);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), [
                'form_id' => $form->id,
            ])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 3: Jalankan ujian, sahkan ia GAGAL**

Run: `php artisan test --filter=AnalisaBorang14ScenarioTest`
Expected: FAIL — route tidak wujud

- [ ] **Step 4: Tambah kaedah controller**

```php
// app/Http/Controllers/AnalisaComparisonController.php — TAMBAH
// (jangan ubah storeScenario() sedia ada)

use App\Models\Borang14Form;
use App\Services\Pilihanraya\Borang14ScenarioMapper;

/** Borang 14 yang layak untuk kerusi comparison ini. */
public function borang14Tersedia(AnalisaComparison $comparison)
{
    $forms = $this->formsForComparison($comparison)
        ->orderByDesc('tahun')->orderBy('jenis_pr')
        ->get()
        ->map(fn (Borang14Form $f) => [
            'id' => $f->id,
            'label' => mb_strtoupper($f->jenis_pr) . ' ' . $f->tahun,
            'tahun' => $f->tahun,
            'jenis_pr' => $f->jenis_pr,
            'status' => $f->status,
            'penjuru' => $f->penjuru,
            // Borang tanpa nama parti tidak boleh dipeta — tandakan supaya user tahu.
            'sedia' => collect($f->parties ?? [])->contains(fn ($p) => trim((string) ($p['nama'] ?? '')) !== ''),
        ]);

    return response()->json(['forms' => $forms]);
}

public function storeScenarioFromBorang14(Request $request, AnalisaComparison $comparison, Borang14ScenarioMapper $mapper)
{
    $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);

    if ($comparison->scenarios()->count() >= 3) {
        return response()->json(['message' => 'Maksimum 3 senario setiap perbandingan.'], 422);
    }

    // Kerusi mesti padan — jangan bergantung pada tapisan frontend sahaja.
    $form = $this->formsForComparison($comparison)->where('borang14_forms.id', $data['form_id'])->first();
    if (! $form) {
        return response()->json(['message' => 'Borang 14 ini bukan untuk kawasan perbandingan ini.'], 422);
    }

    try {
        $mapped = $mapper->map($form);
    } catch (\RuntimeException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }

    $position = (int) $comparison->scenarios()->max('position') + 1;

    $comparison->scenarios()->create([
        'position' => $position,
        'label' => mb_strtoupper($form->jenis_pr) . ' ' . $form->tahun,
        // Borang 14 simpan tahun sahaja. 1 Jan menjaga isihan; UI papar label, bukan tarikh ini.
        'election_date' => $form->tahun . '-01-01',
        'source_filename' => 'Borang 14 — ' . mb_strtoupper($form->jenis_pr) . ' ' . $form->tahun,
        'parsed_rows' => $mapped['rows'],
        'parsed_totals' => $mapped['totals'],
        'row_count' => count($mapped['rows']),
    ]);

    $comparison->update(['status' => 'draft']);

    return response()->json(['comparison' => $this->comparisonPayload($comparison->fresh('scenarios'))]);
}

/** Query borang yang sejajar dengan kerusi comparison. */
private function formsForComparison(AnalisaComparison $comparison)
{
    return $comparison->level === 'parlimen'
        ? Borang14Form::forKawasan(Borang14Form::KAWASAN_PARLIMEN, (int) $comparison->bandar_id)
        : Borang14Form::forKawasan(Borang14Form::KAWASAN_DUN, (int) $comparison->kadun_id);
}
```

- [ ] **Step 5: Daftar routes**

```php
// routes/web.php — dalam kumpulan analisa.comparisons sedia ada (sekitar baris 425-433)
Route::get ('/analisa/comparisons/{comparison}/borang14', [AnalisaComparisonController::class, 'borang14Tersedia'])
    ->name('analisa.comparisons.borang14');
Route::post('/analisa/comparisons/{comparison}/scenarios/borang14', [AnalisaComparisonController::class, 'storeScenarioFromBorang14'])
    ->name('analisa.comparisons.scenarios.borang14')->middleware('throttle:20,1');
```

- [ ] **Step 6: Jalankan ujian, sahkan LULUS**

Run: `php artisan test --filter=AnalisaBorang14ScenarioTest`
Expected: PASS — 4 tests

- [ ] **Step 7: Sahkan laluan upload tidak terjejas**

Run: `php artisan test --filter=Analisa`
Expected: PASS semua

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/AnalisaComparisonController.php routes/web.php tests/Feature/AnalisaBorang14ScenarioTest.php
git commit -m "Analisa: endpoint senarai + cipta senario dari Borang 14"
```

---

### Task 3: Pemilih sumber dalam `AddScenarioForm`

**Files:**
- Modify: `resources/js/Pages/Pilihanraya/analisa/ComparisonBuilder.jsx` (`AddScenarioForm`, sekitar baris 14-99)

**Interfaces:**
- Consumes: `GET pilihanraya.analisa.comparisons.borang14` → `{forms: [{id, label, tahun, jenis_pr, status, penjuru, sedia}]}`; `POST pilihanraya.analisa.comparisons.scenarios.borang14` body `{form_id}` → `{comparison}` (Task 2).

- [ ] **Step 1: Baca `AddScenarioForm` sepenuhnya**

Run: `sed -n '1,145p' resources/js/Pages/Pilihanraya/analisa/ComparisonBuilder.jsx`

Tujuan: fahami cara ia POST dan cara `res.data.comparison` menggantikan state, supaya laluan baharu mengikut corak yang sama.

- [ ] **Step 2: Muat senarai borang bila comparison dibuka**

```jsx
const [borangList, setBorangList] = useState([]);
const [sumber, setSumber] = useState('upload');   // 'borang14' | 'upload'
const [formId, setFormId] = useState('');

useEffect(() => {
    let batal = false;
    axios.get(route('pilihanraya.analisa.comparisons.borang14', comparisonId))
        .then((res) => {
            if (batal) return;
            const senarai = res.data.forms ?? [];
            setBorangList(senarai);
            // Default ke Borang 14 hanya bila ada yang boleh dipakai.
            setSumber(senarai.some((f) => f.sedia) ? 'borang14' : 'upload');
        })
        .catch(() => { if (! batal) setSumber('upload'); });
    return () => { batal = true; };
}, [comparisonId]);
```

- [ ] **Step 3: Render pemilih sumber**

```jsx
<div className="flex gap-4 text-sm">
    <label className="flex items-center gap-2">
        <input type="radio" checked={sumber === 'borang14'} onChange={() => setSumber('borang14')}
               disabled={! borangList.some((f) => f.sedia)} />
        Pilih dari Borang 14
    </label>
    <label className="flex items-center gap-2">
        <input type="radio" checked={sumber === 'upload'} onChange={() => setSumber('upload')} />
        Upload scoresheet
    </label>
</div>

{sumber === 'borang14' ? (
    borangList.length === 0 ? (
        <p className={t.label}>Tiada Borang 14 untuk kawasan ini. Upload scoresheet di Borang 14 dahulu, atau guna pilihan upload.</p>
    ) : (
        <select className={t.input} value={formId} onChange={(e) => setFormId(e.target.value)}>
            <option value="">Pilih Borang 14</option>
            {borangList.map((f) => (
                <option key={f.id} value={f.id} disabled={! f.sedia}>
                    {f.label}{f.status === 'draft' ? ' (draf)' : ''}{f.sedia ? '' : ' — parti belum dipetakan'}
                </option>
            ))}
        </select>
    )
) : (
    /* dropzone + medan Tarikh Pilihanraya sedia ada, tidak berubah */
)}
```

Medan **Tarikh Pilihanraya** hanya dirender pada laluan `upload` — tahun datang dari borang pada laluan Borang 14.

- [ ] **Step 4: Hantar mengikut sumber**

```jsx
const hantar = async () => {
    setSibuk(true);
    try {
        const res = sumber === 'borang14'
            ? await axios.post(route('pilihanraya.analisa.comparisons.scenarios.borang14', comparisonId),
                               { form_id: Number(formId) })
            : await axios.post(route('pilihanraya.analisa.comparisons.scenarios.store', comparisonId),
                               formData, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 60000 });
        onUpdated(res.data.comparison);
        setFormId('');
    } catch (e) {
        setRalat(e.response?.data?.message ?? 'Gagal menambah senario.');
    } finally {
        setSibuk(false);
    }
};
```

Butang dilumpuhkan bila `sumber === 'borang14' && ! formId`.

- [ ] **Step 5: Build**

Run: `npm run build`
Expected: `✓ built` tanpa ralat

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Pilihanraya/analisa/ComparisonBuilder.jsx public/build
git commit -m "Analisa: pemilih sumber senario (Borang 14 atau upload)"
```

---

### Task 4: Ujian penerimaan — dua sumber dalam satu perbandingan

**Files:**
- Test: `tests/Feature/AnalisaBorang14ScenarioTest.php` (tambah)

- [ ] **Step 1: Tulis ujian**

```php
public function test_borang14_and_upload_scenarios_coexist(): void
{
    [, $bandar, $kadun] = $this->seedSeat();
    $form = $this->form($kadun);
    $c = $this->comparison($bandar, $kadun);

    // senario dari Borang 14
    $this->actingAs($this->user())
        ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), ['form_id' => $form->id])
        ->assertOk();

    // senario "upload" dibina terus dengan bentuk yang sama
    $c->scenarios()->create([
        'position' => 2, 'label' => 'PRN 2018', 'election_date' => '2018-05-09',
        'source_filename' => 'upload.xlsx',
        'parsed_rows' => [['kawasan' => 'KAMPONG TENGKEK', 'pemilih' => 2000,
                           'keluar' => 1500, 'ditolak' => 10,
                           'undi' => ['PERIKATAN NASIONAL' => 700, 'PAKATAN HARAPAN' => 790]]],
        'parsed_totals' => ['pemilih' => 2000, 'keluar' => 1500, 'ditolak' => 10,
                            'undi' => ['PERIKATAN NASIONAL' => 700, 'PAKATAN HARAPAN' => 790],
                            'parties' => ['PERIKATAN NASIONAL', 'PAKATAN HARAPAN']],
        'row_count' => 1,
    ]);

    // Kedua-duanya mesti berbentuk serasi untuk ElectionComparisonService.
    $this->assertSame(2, $c->scenarios()->count());
    foreach ($c->fresh('scenarios')->scenarios as $s) {
        $this->assertArrayHasKey('undi', $s->parsed_totals);
        $this->assertArrayHasKey('parties', $s->parsed_totals);
        foreach ($s->parsed_rows as $r) {
            $this->assertArrayHasKey('kawasan', $r);
            $this->assertArrayHasKey('undi', $r);
        }
    }
}
```

- [ ] **Step 2: Jalankan suite penuh**

Run: `php artisan test --filter=Analisa`
Expected: PASS semua

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/AnalisaBorang14ScenarioTest.php
git commit -m "Analisa: ujian penerimaan dua sumber senario"
```
