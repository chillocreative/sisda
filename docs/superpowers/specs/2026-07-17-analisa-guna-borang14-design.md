# Perbandingan Senario AI: ambil data dari Borang 14

**Tarikh:** 2026-07-17
**Status:** Design — diluluskan

## Konteks

"Perbandingan Senario AI" (Analisa) memaksa user **upload scoresheet semula** untuk
setiap senario — walaupun scoresheet yang sama sudah pun dibaca, disemak, dan
disimpan dalam Borang 14. Itu kerja berganda, kos AI berganda (setiap upload =
satu panggilan Claude, sehingga ~200s), dan dua salinan angka yang sama yang boleh
terpesong antara satu sama lain.

Selepas ini: pilih borang yang sudah ada. Upload kekal untuk kerusi yang belum
ada Borang 14.

## Keputusan yang dipersetujui

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Ganti atau tambah | **Tambah** — pilih dari Borang 14 ATAU upload |
| 2 | `pemilih` bila tiada | Guna yang ada; `null` bila tiada; **jangan reka** |
| 3 | `election_date` | 1 Jan tahun itu (dalaman); papar "PRN 2023" |
| 4 | `(D) tidak dimasukkan` | Tidak diwakili — Analisa tiada medannya |

## Prinsip reka bentuk

`ElectionComparisonService`, prompt AI, dan `ComparisonResult.jsx`
**tidak disentuh langsung.** Satu mapper menukar `Borang14Form` kepada bentuk
`AnalisaScenario` yang sedia ada. Analisa tidak tahu — dan tidak perlu tahu —
dari mana senario itu datang.

Ini yang menjadikan perubahan ini kecil dan berisiko rendah.

## Kontrak data sasaran (sedia ada — jangan ubah)

Disahkan dari `AnalisaComparisonController::storeScenario()` dan
`ElectionComparisonService::scenarioSummary()`:

```php
parsed_rows = [
    ['kawasan' => 'KAMPONG TENGKEK',   // nama Daerah Mengundi
     'pemilih' => 2110,                 // int ATAU null
     'keluar'  => 1655,                 // int
     'ditolak' => 19,                   // int
     'undi'    => ['PAKATAN HARAPAN' => 900, 'PERIKATAN NASIONAL' => 700]],
]

parsed_totals = [
    'pemilih' => 13408, 'keluar' => 9107, 'ditolak' => 87,
    'undi'    => ['PAKATAN HARAPAN' => 4549, 'PERIKATAN NASIONAL' => 4471],
    'parties' => ['PAKATAN HARAPAN', 'PERIKATAN NASIONAL'],
]
```

Nota penting: `parsed_rows` ialah **per Daerah Mengundi**, bukan per saluran.
Kunci `undi` ialah **nama parti**, bukan nombor slot. Baris dengan `kawasan`
kosong atau `array_sum(undi) === 0` digugurkan oleh Analisa.

## Sumber data Borang 14

```
borang14_votes: (borang14_form_id, pusat, saluran, slot, undi)
  slot 1..6  -> undi parti
  slot 90    -> (C) undi ditolak
  slot 91    -> (D) tidak dimasukkan ke peti

borang14_forms.parties: [{slot, keahlian_parti_id, nama}]
```

## Mapper: `App\Services\Pilihanraya\Borang14ScenarioMapper`

Satu kaedah awam: `map(Borang14Form $form): array` → `['rows' => [...], 'totals' => [...]]`.

### Langkah

1. **Pusat Mengundi → Daerah Mengundi.** `borang14_votes.pusat` ialah nama Pusat
   Mengundi. Peta ia ke DM melalui struktur yang sama yang dipakai skrin Keyin —
   keutamaan: `form.structure` (dari scoresheet), else `Borang14Reference::forKadun()`
   / `forBandar()`. Jika struktur tiada langsung → mapper gagal dengan mesej BM
   yang jelas; jangan agak.

2. **Slot → nama parti.** Dari `form.parties[].nama`. Slot tanpa nama parti
   digugurkan (bukan dinamakan "Parti 3").

3. **Agregat per DM.** Jumlahkan semua saluran semua Pusat dalam DM itu.

4. **Baris peringkat DUN** (`pusat = ''`, `saluran = 'UNDI POS'` / `'UNDI AWAL'`)
   tiada DM. Ia menjadi **barisnya sendiri** dengan `kawasan = 'UNDI POS'`.
   Ini jujur: undi pos memang bukan sebahagian mana-mana DM.

5. **`keluar`** = `jumlah undi parti + ditolak`. Ini mengikut konvensyen
   `ScoresheetExtractor` sedia ada supaya senario dari Borang 14 dan dari upload
   boleh dibanding secara adil. Lihat "Batasan diketahui" di bawah.

6. **`ditolak`** = slot 90.

7. **`pemilih`** (keputusan #2):
   - Dari `Borang14Reference` — jumlah `berdaftar` semua saluran dalam DM itu.
   - Jika `berdaftar` tiada (kerusi bersumber scoresheet) → **`null`**, bukan 0.
   - `totals.pemilih`: jumlah DM jika semua diketahui; jika tidak, guna
     `form.structure['jumlah_pemilih']` (angka JUMLAH PEMILIH dari kepala
     scoresheet — ia benar) ; jika itu pun tiada → `null`.

### Contoh (Juasseh PRN 2023, bersumber scoresheet)

```
parsed_rows[0] = ['kawasan' => 'UNDI POS', 'pemilih' => null,
                  'keluar' => 189, 'ditolak' => 18,
                  'undi' => ['PN' => 98, 'PKR' => 73]]
parsed_rows[1] = ['kawasan' => 'KAMPONG TENGKEK', 'pemilih' => null, ...]

parsed_totals  = ['pemilih' => 13408,   // dari kepala scoresheet — benar
                  'keluar'  => 9107,    // 9020 + 87
                  'ditolak' => 87, ...]
```

`% keluar` peringkat DUN boleh dikira (67.9%). Per DM papar `—`. Tiada rekaan.

## Endpoint

```
GET  /analisa/comparisons/{comparison}/borang14-tersedia   (BAHARU)
     -> borang yang layak untuk kerusi comparison itu:
        [{id, label, tahun, jenis_pr, status, penjuru, row_count_anggaran}]

POST /analisa/comparisons/{comparison}/scenarios/borang14   (BAHARU, throttle:20,1)
     body: {form_id}
     -> sama respons dengan storeScenario() sedia ada
```

**Padanan kerusi:** `borang14_forms.kawasan_type`/`kawasan_id` sejajar 1:1 dengan
`analisa_comparisons.level`/(`bandar_id`|`kadun_id`). Borang dari kerusi lain
**mesti ditolak** — bukan ditapis senyap di frontend sahaja.

**Had 3 senario** dan `status => 'draft'` (paksa analisis semula) kekal seperti
laluan upload.

`source_filename` diisi `"Borang 14 — PRN 2023"` supaya asal usul senario
kelihatan pada `ScenarioChip` sedia ada tanpa perubahan UI.

## Frontend

`resources/js/Pages/Pilihanraya/analisa/ComparisonBuilder.jsx` → `AddScenarioForm`
dapat pemilih sumber:

```
Senario 1
  (•) Pilih dari Borang 14      ( ) Upload scoresheet
      [ PRN 2023 — Juasseh ▾ ]
```

- Default ke **Borang 14** apabila kerusi itu ada borang; default ke **Upload**
  apabila tiada.
- Bila tiada borang: nota BM "Tiada Borang 14 untuk kerusi ini" + kekal boleh upload.
- Medan **Tarikh Pilihanraya** disembunyikan pada laluan Borang 14 — tahun datang
  dari borang. Ia kekal pada laluan upload.
- Label auto-isi "PRN 2023" (boleh diedit).
- Guna token `usePilihanrayaTheme()`. Tiada gaya baharu.

## Batasan diketahui (dinyatakan, bukan disembunyikan)

- **(D) tidak dimasukkan ke peti tidak diwakili.** Analisa tiada medannya.
  Di Juasseh ia 15 daripada 9,122 (0.16%). `keluar` mengecualikannya, konsisten
  dengan senario dari upload.
- **`pemilih` per DM tiada** untuk kerusi bersumber scoresheet. `% keluar` per DM
  akan papar `—`. Peringkat DUN tetap tepat.
- **Nama parti mesti dipetakan dahulu.** Borang dengan `parties[].nama` kosong
  menghasilkan senario tanpa kunci parti. Endpoint senarai hendaklah menandakan
  borang sebegini supaya user tahu ia perlu disiapkan di Keyin dahulu.

## Pengendalian ralat

| Keadaan | Kelakuan |
|---|---|
| `form_id` bukan milik kerusi comparison | 422, mesej BM |
| Borang tiada struktur | 422 "Struktur saluran tiada" |
| Borang tiada nama parti | 422, cadang siapkan di Keyin |
| Sudah 3 senario | 422 (kelakuan sedia ada) |
| Borang tiada undi langsung | 422 — jangan cipta senario kosong |

## Verification

- **Unit** `Borang14ScenarioMapperTest`: fixture Juasseh → sahkan jumlah
  `9020 + 87 = 9107` keluar, `ditolak = 87`, `pemilih` totals = 13408, `pemilih`
  per DM = null, baris `UNDI POS` wujud berasingan, slot 90/91 **tidak** muncul
  sebagai parti, 11 DM + 1 Undi Pos = 12 baris.
- **Unit**: borang dengan `Borang14Reference` (Buloh Kasap) → `pemilih` per DM ialah
  nombor sebenar, bukan null.
- **Feature**: POST dengan `form_id` kerusi lain → 422, tiada senario dicipta.
- **Feature**: senario dari Borang 14 + senario dari upload dalam comparison yang
  sama → `analyze()` berjaya (bentuknya serasi).
- `php artisan test --filter=Analisa`
- Manual: Buloh Kasap → pilih PRN 2026 → Jana Analisis AI → laporan terhasil.

## Skop dikecualikan

- Menukar `ElectionComparisonService` atau prompt AI
- Auto-cipta comparison dari Borang 14
- Menyelaraskan `(D)` ke dalam model Analisa
- Membuang laluan upload
