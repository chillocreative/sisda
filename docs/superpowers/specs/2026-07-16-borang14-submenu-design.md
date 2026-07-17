# Submenu Borang 14 — Upload Scoresheet, Papar, Keyin

**Tarikh:** 2026-07-16
**Status:** Design — menunggu semakan user

## Konteks

Borang 14 hari ini ialah satu page tunggal yang menyimpan **satu** set keputusan per
`(kadun_id, penjuru)`. Tiada tahun, tiada jenis pilihanraya. Akibatnya: keyin PRN 2026
akan menimpa PRN 2022 untuk DUN yang sama, dan tiada cara untuk merujuk keputusan lama.
Struktur Daerah Mengundi / Pusat Mengundi pula hanya wujud untuk **satu** kerusi
(Buloh Kasap, `resources/data/borang14/kadun-41.json`) — 95 DUN dan 39 Parlimen lain
terpaksa bergantung pada agakan DPT atau langsung tidak boleh diguna.

Perubahan ini memecahkan Borang 14 kepada tiga skrin: upload scoresheet (AI populate),
papar sejarah, dan keyin manual. Hasil yang dikehendaki: mana-mana kerusi boleh direkod,
untuk mana-mana pilihanraya, dengan scoresheet SPR sebagai pintu masuk pantas.

**Keadaan data semasa (disahkan 2026-07-16):** `borang14_forms` = 0 baris,
`scoreboards` = 0 baris. Tiada migrasi data, tiada backfill, tiada perlanggaran unique.
Schema bebas dibentuk semula.

## Keputusan yang telah dipersetujui

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Skop kawasan | Parlimen **dan** DUN (polymorphic) |
| 2 | Granulariti scoresheet | Campur — handle per-saluran dan per-pusat |
| 3 | Sumber struktur | Scoresheet **sentiasa menang** (+ snapshot boleh revert) |
| 4 | `penjuru` | Atribut, dibuang dari kunci unik |
| 5 | Undi (C) & (D) | Tambah kedua-dua sebagai slot rizab |
| 6 | Aliran upload | Upload → draf → lompat ke Keyin yang terisi |
| 7 | Navigasi | Tiga tab dalam page induk (bukan menu depth-2) |
| 8 | Kawasan tiada dalam sistem | Scoresheet cipta kawasan, user sahkan |

## Rujukan: scoresheet sebenar

Disahkan terhadap fail sebenar
`Score Sheet Juasseh - PRN N9 - 2023.pdf` (Borang SPR 760 Pin. 1/99, 3 muka surat,
PRN Negeri Sembilan ke-15, N.15 Juasseh, cetak 12/08/2023). Setiap dapatan di bawah
disahkan dengan mengira semula baris sheet itu — bukan andaian.

### Susunan lajur sebenar (kiri → kanan)

```
BIL.
NO. KOD DAERAH MENGUNDI          '129 / 15 / 01'  + nama DM di atasnya
NAMA PUSAT MENGUNDI              'SEKOLAH KEBANGSAAN TENGKEK'
NO. TEMPAT MENGUNDI (SALURAN)    1, 2, 3 ...
JUMLAH KERTAS UNDI ... DALAM PETI UNDI (A)
BILANGAN UNDIAN ... SETIAP ORANG CALON (B)   <- satu lajur per CALON
JUMLAH UNDIAN OLEH PEMILIH
BILANGAN KERTAS UNDI YANG DITOLAK (C)
JUMLAH KERTAS UNDI ... TIDAK DIMASUKKAN KE DALAM PETI UNDI (D)
```

Header sheet: `JUMLAH PEMILIH : 13,408` (peringkat DUN),
`BAHAGIAN PILIHAN RAYA NEGERI : N.15 JUASSEH`.
Baris `JUMLAH` akhir: `40 | 9,122 | 4,471 | 4,549 | 9,020 | 87 | 15`
(40 = bilangan baris saluran termasuk Undi Pos, bukan angka undi).

### Empat dapatan yang membetulkan andaian awal

1. **Undi Pos ADA dalam sheet** — baris `BIL. 1`, sebelum header seksyen
   `UNDI BIASA`, dengan Pusat Mengundi dan Saluran kosong:
   `UNDI POS | A=203 | 98 | 73 | 171 | C=18 | D=14`.
   Extractor **mesti** menulisnya ke `pusat:'', saluran:'UNDI POS'`.
   Sheet ini **tiada** baris `UNDI AWAL` — jadi kehadirannya bersyarat, bukan tetap.

2. **Formula silang-semak** (disahkan pada setiap baris):
   ```
   JUMLAH UNDIAN OLEH PEMILIH = Σ undi calon
   (A)                        = Σ undi calon + (C) + (D)
   ```
   Contoh: Bil 10 saluran 2 → `65 + 103 + 1 + 1 = 170 = A`.
   Undi Pos → `98 + 73 + 18 + 14 = 203 = A`.
   Jumlah besar → `4,471 + 4,549 + 87 + 15 = 9,122 = A`.

3. **`berdaftar` TIADA dalam scoresheet.** Lajur (A) ialah kertas undi dalam peti
   (iaitu undi keluar), **bukan** pengundi berdaftar. Satu-satunya angka pemilih
   ialah `JUMLAH PEMILIH: 13,408` di peringkat DUN. Implikasi: lajur
   **`% Turnout`** dan **`Tak Keluar`** tidak boleh dikira daripada scoresheet
   sahaja — ia perlukan `berdaftar` per saluran daripada `Borang14Reference`.

4. **Parti dikenali melalui logo + nama calon**, bukan teks nama parti. Lajur calon
   berkepala nama orang (`EDDIN SYAZLEE BIN SHITH`, `PUAN SRI BIBI SHARLIZA`) dengan
   logo parti sebagai **imej**. Logo Perikatan Nasional mengandungi teks; logo
   PKR (dacing/mata) tidak. Jadi pengecaman parti **tidak boleh dipercayai
   sepenuhnya** — extractor pulangkan nama calon + tekaan parti, user **mesti**
   sahkan pemetaan ke `keahlian_parti` di tab Keyin.

Nota lain: sheet ada **watermark `DRAFT` / `JPRP`** pepenjuru yang mesti diabaikan;
sel `BIL.` + nama DM kadang bertindih (`12 KAMPONG GENTAM`) pada muka surat 2-3.

## Data model

### `borang14_forms` — bentuk semula

```
id
kawasan_type     varchar(10)           'parlimen' | 'dun'
kawasan_id       unsignedBigInteger    -> bandar.id | kadun.id
jenis_pr         varchar(4)            'pru' | 'prn' | 'prk'
tahun            unsignedSmallInteger
penjuru          unsignedTinyInteger   atribut sahaja
parties          json                  [{slot, keahlian_parti_id, nama}]
structure        json      nullable    DM/Pusat/Saluran dari scoresheet
status           varchar(10)           'draft' | 'published'   default 'draft'
source           varchar(12)           'manual' | 'scoresheet' default 'manual'
source_filename  varchar(255) nullable
needs_review     boolean               default false
published_at     timestamp nullable
timestamps
UNIQUE (kawasan_type, kawasan_id, jenis_pr, tahun)
INDEX  (kawasan_type, kawasan_id)
INDEX  (status, tahun)
```

`kawasan_type`/`kawasan_id` tidak boleh guna FK constraint kerana ia menunjuk ke dua
jadual. Integriti dijaga di peringkat validation (`exists:bandar,id` atau `exists:kadun,id`
bergantung pada `kawasan_type`).

**Julat `tahun`:** validation `integer|between:1959,2100` (1959 = pilihanraya umum
pertama Malaysia). UI guna `<select>` yang dijana dari `1959..tahun_semasa+1` supaya
tiada typo — bukan input bebas.

**Padanan `jenis_pr` ↔ `kawasan_type`:** tiada kekangan keras. PRU lazimnya kerusi
Parlimen dan PRN kerusi DUN, tetapi PRK boleh jadi mana-mana, dan PRU/PRN kerap
diadakan serentak. Sekatan keras akan menolak data sah. Sebaliknya, UI tunjuk nota
lembut apabila gabungan luar biasa dipilih (contoh: PRU + DUN).

### `borang14_votes` — struktur kekal, slot diperluas

Tiada perubahan skema. Slot rizab:

```
slot 1..6   undi parti
slot 90     (C) undi ditolak
slot 91     (D) undi tidak dimasukkan ke peti
```

`UNIQUE (borang14_form_id, pusat, saluran, slot)` sedia ada terus berfungsi. Satu-satunya
perubahan: validation `slot` dari `integer|between:1,6` → `in:1,2,3,4,5,6,90,91`.
Autosave on-blur tidak perlu diubah langsung.

### `borang14_snapshots` — baharu

Jaring keselamatan untuk keputusan #3. Sebelum scoresheet menimpa form sedia ada,
simpan keadaan lama. Overwrite kekal senyap (tiada prompt), tetapi boleh di-revert.

```
id
borang14_form_id  FK -> borang14_forms  cascadeOnDelete
structure         json nullable
votes             json
parties           json nullable
reason            varchar(40)      'before_scoresheet_overwrite'
created_by        FK -> users      nullOnDelete
created_at        timestamp
INDEX (borang14_form_id, created_at)
```

### `scoreboards` — selaraskan kunci

Kini `UNIQUE(kadun_id, penjuru)` dan membaca angka Borang 14 melalui kunci lama itu.
Kunci itu tidak lagi wujud. Jadual kosong, jadi tukar terus:

```
- kadun_id, penjuru
+ borang14_form_id  FK -> borang14_forms  cascadeOnDelete
  UNIQUE (borang14_form_id)
```

`ScoreboardController` dikemas kini untuk resolve melalui `borang14_form_id`.

## Keutamaan struktur

Ia bukan satu keutamaan, tetapi **dua** — kerana scoresheet tidak mengandungi
`berdaftar` (dapatan #3):

```
Nama DM / Pusat Mengundi / Saluran / undi / (C) / (D):
  1. form.structure                  dari scoresheet — sentiasa menang
  2. Borang14Reference               JSON rasmi, else agakan DPT
  3. null                            banner "data belum tersedia"

berdaftar (dan lajur terbitannya — % Turnout, Tak Keluar):
  1. Borang14Reference SAHAJA        scoresheet tiada angka ini
  2. null                            papar '—', bukan 0
```

Inilah sebabnya "scoresheet sentiasa menang" tidak boleh mutlak. Ia menang untuk
segala yang **ada** dalam sheet; ia senyap untuk `berdaftar` kerana sheet memang
tidak menyimpannya.

`forBandar()` (BAHARU — untuk Parlimen) boleh dibina dengan mudah kerana
`daerah_mengundi.bandar_id` memang sudah menunjuk ke Parlimen — DM dikumpul terus
tanpa melalui DUN.

## Cipta kawasan dari scoresheet (keputusan #8)

**Masalah yang disahkan:** jadual `kadun` hanya ada Johor (56 DUN) dan Pulau Pinang
(40 DUN). 14 negeri lain **kosong**. Scoresheet rujukan (N.15 Juasseh, Negeri
Sembilan) tiada kawasan untuk dipilih — upload akan buntu.

**Penyelesaian:** header sheet mengenal dirinya sendiri, dan kod DM mengekod hierarki
penuh:

```
'129 / 15 / 01'
  129  -> kod Parlimen  (bandar)
  15   -> kod DUN       (kadun)  — padan 'N.15 JUASSEH' pada header
  01   -> kod DM
```

Aliran apabila kawasan tiada:

```
1. Padan negeri dari header       'NEGERI SEMBILAN' -> negeri.id  (16 negeri sedia ada)
2. Padan/cipta bandar  kod 129    -> jika tiada, cipta dengan nama dari sheet
3. Padan/cipta kadun   kod N.15   -> jika tiada, cipta di bawah bandar itu
4. Papar pengesahan kepada user SEBELUM tulis
```

Kekangan: pencipta hanya dibenarkan apabila **negeri** dapat dipadankan (ke-16 negeri
sudah wujud). Jika negeri pun tidak dikenali → tolak upload dengan mesej jelas, jangan
cipta negeri baru. Ini menghalang data geografi tercemar oleh bacaan AI yang tersasar.

Rekod yang dicipta ditanda `source: 'scoresheet'` supaya boleh diaudit kemudian.
Nama Parlimen kerap tiada pada sheet (hanya kod `129`) — jika begitu, cipta dengan
nama placeholder `P.129` dan biarkan user betulkan; jangan teka nama.

## Extraction

`ScoresheetExtractor::extract()` dipakai oleh Analisa dan **tidak diusik**. Tambah
kaedah baharu `extractDetailed()` dengan prompt kedua yang mengekalkan kolum 3 & 4
(Nama Pusat Mengundi, Nombor Tempat Mengundi) — kolum yang prompt sedia ada baca lalu
sengaja buang (`"do not return per-saluran detail"`).

Bentuk output (padan dengan lajur sebenar Borang 760 — perhatikan **tiada**
`berdaftar`, dan calon adalah orang, bukan parti):

```json
{
  "negeri": "NEGERI SEMBILAN",
  "kawasan_kod": "N.15",
  "kawasan_nama": "JUASSEH",
  "parlimen_kod": "129",
  "jumlah_pemilih": 13408,
  "calon": [
    { "nama": "EDDIN SYAZLEE BIN SHITH", "parti_tekaan": "PN",  "yakin": true  },
    { "nama": "PUAN SRI BIBI SHARLIZA",  "parti_tekaan": null,  "yakin": false }
  ],
  "rows": [
    { "dm_kod": null, "dm": null, "pusat": "", "saluran": "UNDI POS",
      "a": 203, "undi": [98, 73], "jumlah_undian": 171,
      "ditolak": 18, "tidak_dimasukkan": 14 },

    { "dm_kod": "129/15/01", "dm": "KAMPONG TENGKEK",
      "pusat": "SEKOLAH KEBANGSAAN TENGKEK", "saluran": "1",
      "a": 127, "undi": [48, 76], "jumlah_undian": 124,
      "ditolak": 3, "tidak_dimasukkan": 0 }
  ],
  "jumlah": { "a": 9122, "undi": [4471, 4549], "jumlah_undian": 9020,
              "ditolak": 87, "tidak_dimasukkan": 15 }
}
```

`undi` ialah **array mengikut kedudukan**, selaras dengan `calon` — bukan objek
berkunci nama parti. Ini mengekalkan aturan kiri-ke-kanan yang menjadi punca bug
salah jajar lajur dalam commit `25366893`, dan tidak memaksa AI meneka parti
sebelum user sahkan.

**Undi Pos / Undi Awal:** tangkap sebagai baris biasa dengan `pusat: ''` dan
`saluran: 'UNDI POS'` / `'UNDI AWAL'` — padan terus dengan kelakuan sedia ada.
Kehadirannya **bersyarat**: sheet Juasseh ada Undi Pos tetapi tiada Undi Awal.
Jangan cipta baris kosong untuk yang tiada. (Buloh Kasap `kadun_id=41` menggabung
kedua-duanya jadi `'UNDI AWAL & POS'` — kekalkan pengecualian sedia ada itu.)

**Kes campur (keputusan #2):** jika `saluran` null untuk baris *Undi Biasa* → satu
baris agregat per Pusat pada `saluran: "1"`, set `form.needs_review = true`.
Baris Undi Pos/Awal memang tiada saluran secara semula jadi — itu **bukan**
`needs_review`.

**Silang-semak (formula disahkan pada sheet sebenar):**

```
per baris:  a == Σ undi + ditolak + tidak_dimasukkan
per baris:  jumlah_undian == Σ undi
jumlah:     Σ semua baris == blok jumlah
```

Gagal mana-mana → banner amaran pada Keyin dengan baris yang tidak seimbang
disenaraikan. Rekod tetap disimpan; **bukan gagal senyap**.

**`berdaftar` dan `% Turnout`:** scoresheet tiada angka ini (lihat dapatan #3).
Nilai `berdaftar` per saluran kekal datang daripada `Borang14Reference` sahaja.
Jika reference tiada untuk kawasan itu → sel `Berdaftar`, `% Turnout`, `Tak Keluar`
papar `—` dan bukan sifar. Sifar akan menipu; `—` jujur.

**Pemetaan parti:** `calon[].parti_tekaan` hanya cadangan. Tab Keyin papar setiap
lajur calon dengan dropdown `keahlian_parti` yang perlu disahkan user sebelum
publish. Form dengan mana-mana `yakin: false` ditanda `needs_review = true`.

**Watermark:** prompt mesti arahkan AI abaikan teks pepenjuru `DRAFT` / `JPRP`
dan mana-mana teks footer (`[keAdilan] - JABATAN PILIHANRAYA PUSAT ...`).

Guna semula tanpa perubahan: `ClaudeService::chat()` (sokong content block),
`documentModel()`, `extractJson()`, logging kos automatik ke `ai_usage_logs`.

## Tiga tab

Page induk `/pilihanraya/borang-14` — tiga tab, nav global tidak disentuh.

### Tab 1 — Upload Scoresheet

Dropdown: Negeri → Jenis PR → Kawasan (Parlimen/DUN) → Tahun. Dropzone menerima
`xlsx,xls,csv,txt,pdf,jpg,jpeg,png,webp` max 20MB — ikut pattern
`AnalisaComparisonController::storeScenario()` (baris 79-84), termasuk fail **tidak
disimpan ke disk**, hanya dihantar ke extractor lalu dibuang.

Aliran: extract → snapshot (jika form wujud) → tulis `structure` + `votes` + `parties`
→ `source: 'scoresheet'`, `status: 'draft'` → redirect ke tab Keyin yang terisi.

### Tab 2 — Papar Borang 14

Penapis bertingkat: **Negeri** (wajib) → **Parlimen** (pilihan) → **DUN** (pilihan).

Semantik penapis, kerana satu rekod boleh jadi Parlimen **atau** DUN:

| Penapis dipilih | Rekod yang keluar |
|---|---|
| Negeri sahaja | Semua rekod dalam negeri itu — Parlimen dan DUN |
| Negeri + Parlimen | Rekod Parlimen itu sendiri, **dan** rekod semua DUN di bawahnya |
| Negeri + Parlimen + DUN | Rekod DUN itu sahaja |

Jadual: Tahun · Jenis PR · Kawasan · Penjuru · Status · Sumber · Tindakan
(Papar / PDF / Revert). Lajur "Kawasan" tunjuk badge jenis (`PARLIMEN` / `DUN`)
supaya dua aras itu tidak dikelirukan.

Menyenaraikan draf **dan** published dengan badge status, supaya draf tidak hilang
tanpa jejak. Isih: `tahun desc, jenis_pr, kawasan_type`.

### Tab 3 — Keyin Borang 14

Dropdown: Negeri → Jenis PR → Kawasan → Tahun. DM/Pusat Mengundi keluar dari
keutamaan struktur di atas. Jadual undi sama seperti sekarang, tambah lajur
**Ditolak** (slot 90) dan **Tidak Dimasukkan** (slot 91).

Autosave on-blur kekal (`POST /borang-14/vote`). Butang **Save** →
`status: 'published'`, `published_at: now()` → rekod muncul di tab Papar.

## Routes

```
GET  /borang-14              index — tab shell            (sedia ada, dikemas kini)
GET  /borang-14/senarai      senarai untuk tab Papar      (BAHARU)
POST /borang-14/upload       extract + cipta draf         (BAHARU, throttle:10,1)
POST /borang-14/publish      butang Save                  (BAHARU, throttle:30,1)
POST /borang-14/revert       pulih dari snapshot          (BAHARU, throttle:10,1)
GET  /borang-14/data         + param jenis_pr, tahun, kawasan_type/id
POST /borang-14/vote         + slot 90/91                 (throttle:120,1 kekal)
POST /borang-14/parties      + kawasan polymorphic
GET  /borang-14/pdf          + kawasan polymorphic
```

## Pembersihan yang disertakan

Dua sahaja — kedua-duanya menghalang kerja ini secara langsung:

1. **Pecahkan `resources/js/Pages/Pilihanraya/Borang14.jsx`** (621 baris, satu fail).
   Borang ini kini dipakai dua laluan masuk. Asingkan `VoteTable`,
   `UndiAwalPosTable`, `GrandSummary` → `components/Borang14Form.jsx`.
   Buang salinan pendua `EditableCell` (baris 32-65) — guna
   `components/EditableCell.jsx` yang sedia dikongsi dengan Simulasi.

2. **Indikator status simpan.** Autosave kini `.catch(() => {})` — error ditelan
   senyap. Dengan adanya butang Save, user boleh sangka data tersimpan padahal gagal.
   Tambah state `saving | saved | error` per sel.

Tiada refactor lain. `ScoresheetExtractor` prompt sedia ada, `ClaudeService`,
`Borang14Reference::forKadun()` semuanya kekal.

## Arah UI

Guna skill `frontend-design` + `ui-ux-suite`. Arah gaya: **Dense Functional** —
ini alat kemasukan data pilihanraya, ketumpatan tinggi dan ketepatan mengalahkan
hiasan. Kekalkan token `PilihanrayaShell` sedia ada (`t.card`, `t.tableHead`,
`t.input`) supaya tiga tab ini konsisten dengan War Room / Analisa / Scoreboard.

Selepas page siap, jalankan `/a11y` dan `/colors` dari ui-ux-suite untuk audit
kontras — penting kerana jadual undi padat dan dibaca pantas.

## Pengendalian ralat

| Keadaan | Kelakuan |
|---|---|
| AI gagal / tiada API key | 422 + mesej BM, cadang Tetapan → Claude (pattern sedia ada) |
| Saluran tiada dalam scoresheet | Agregat ke Saluran 1 + `needs_review` + banner |
| Silang-semak tak seimbang | Banner senaraikan baris terlibat; rekod tetap disimpan |
| Autosave gagal | Indikator merah per sel (bukan senyap) |
| Struktur tiada langsung | Banner "data belum tersedia" (kelakuan sedia ada) |
| `berdaftar` tiada (scoresheet sahaja) | `% Turnout` / `Tak Keluar` papar `—`, bukan 0 |
| Parti tak dapat dikenal dari logo | `parti_tekaan: null` + `needs_review`; user petakan |
| Kawasan tiada dalam sistem | Papar pengesahan cipta; user sahkan sebelum tulis |
| Negeri tak dikenali | Tolak upload; **jangan** cipta negeri baru |
| Upload > 20MB / jenis salah | Validation Laravel, mesej BM |

## Verification

**Ujian penerimaan utama — scoresheet Juasseh sebenar.** Fail rujukan
`Score Sheet Juasseh - PRN N9 - 2023.pdf` menjadi ujian sebenar, kerana ia
menguji setiap keputusan serentak: Undi Pos, (C), (D), 39 saluran merentas 11 DM,
kawasan yang tiada dalam sistem, dan parti yang perlu dipetakan.

Angka yang **mesti** dihasilkan (disahkan manual daripada sheet):

```
jumlah_pemilih          13,408
baris saluran           40         (39 Undi Biasa + 1 Undi Pos)
daerah mengundi         11         (kod 129/15/01 .. 129/15/11)
jumlah (A)              9,122
undi calon              4,471 / 4,549
jumlah undian pemilih   9,020
ditolak (C)             87
tidak dimasukkan (D)    15
silang-semak            4,471 + 4,549 + 87 + 15 == 9,122   ✓
Undi Pos                A=203  98 / 73  jumlah=171  C=18  D=14
```

- **Unit test** `ScoresheetExtractor::extractDetailed()` dengan fixture Juasseh:
  sahkan jumlah di atas, sahkan `saluran: 'UNDI POS'` wujud dengan `pusat: ''`,
  sahkan **tiada** baris `UNDI AWAL` direka, sahkan `berdaftar` **tidak** dipulangkan.
- **Unit test** kedua dengan fixture per-pusat sahaja (tiada lajur saluran) → mesti
  set `needs_review = true` dan agregat ke `saluran: '1'`.
- **Feature test** `tests/Feature/Borang14SubmenuTest.php`: upload → draf
  `source: 'scoresheet'`; kawasan Juasseh dicipta di bawah Negeri Sembilan selepas
  pengesahan; publish → `status: 'published'` + muncul dalam `/borang-14/senarai`;
  unique key menolak duplikat `(kawasan, jenis_pr, tahun)`; revert memulihkan snapshot.
- **Feature test** negeri tidak dikenali → upload ditolak, **tiada** negeri dicipta.
- **Manual end-to-end:** upload PDF Juasseh → sahkan cipta kawasan → semak 40 baris
  dalam tab Keyin → petakan dua calon ke parti → Save → muncul di Papar → PDF export.
  Sahkan `% Turnout` papar `—` (bukan 0) kerana Juasseh tiada reference `berdaftar`.
- `php artisan test --filter=Borang14`

## Skop yang dikecualikan

- Menu bersarang depth-2 dalam `AuthenticatedLayout` (guna tab)
- Import pukal banyak kerusi sekali gus
- Edit sejarah / audit trail selain `borang14_snapshots`
- Penyelarasan `JohorElectionData` hardcoded 2022 dengan Borang 14
