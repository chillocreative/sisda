# Scoreboard per Kerusi — Reka Bentuk

Tarikh: 2026-07-31
Status: Diluluskan (menunggu pelan pelaksanaan)

## Masalah

Scoreboard dibina untuk satu orang mengendalikan satu papan. Hasrat sekarang:
**setiap pengguna Parlimen/DUN di seluruh Malaysia boleh membina papan markah
mereka sendiri.** Struktur semasa menghalangnya:

1. **Admin sahaja.** `/pilihanraya/scoreboard` berada dalam kumpulan
   `['auth','admin']` (`routes/web.php:430`); `EnsureAdmin` hanya membenarkan
   `super_admin` + `admin`. Peranan `user`, `super_user`, `ketua_paca_dun`
   langsung tidak boleh membukanya.
2. **Tiada pemilikan.** `saveSettings()` menerima sebarang `kadun_id` tanpa
   semakan (`ScoreboardController.php:161-186`). Mana-mana admin boleh menulis
   ganti papan mana-mana DUN di seluruh negara — kelas IDOR yang sama seperti
   empat pepijat yang dihotfix pada Julai 2026.
3. **Identiti papan tidak stabil.** Papan dikunci pada `borang14_form_id` yang
   diselesaikan sebagai "Borang 14 DUN ini dengan `updated_at` terkini"
   (`:82-85`, diulang di `:179-182`). Menyunting senario Borang 14 lain
   menukar papan secara senyap — tajuk, logo, calon berubah tanpa sesiapa
   menyunting papan.
4. **DUN sahaja.** `Borang14Form` menyokong `kawasan_type = 'parlimen'`, tetapi
   scoreboard mengekod `'dun'` secara tetap.
5. **URL awam ialah id pangkalan data** (`/scoreboard/171`) — tidak sesuai
   dikongsi dan boleh dicongak satu per satu.
6. **`PH_PARTIES` dikekod tetap** (`:20`); `minima` bermaksud "undi minimum PH
   untuk menang". Setiap papan di Malaysia dibingkai sebagai PH lawan yang lain.
7. **Pemilih kawasan bertaraf nasional tanpa nilai lalai** — pengguna yang
   memiliki satu kerusi tetap berdepan tiga dropdown kosong.

Perkara 1–3 ialah penghalang sebenar; selebihnya berpunca daripadanya.

## Keputusan

| Perkara | Keputusan |
|---|---|
| Pemilikan | Satu papan bagi satu kerusi — `UNIQUE(kawasan_type, kawasan_id)` |
| Hak sunting | `super_admin` semua · `admin` Parlimen sendiri + DUN di bawahnya · `super_user`/`user` DUN sendiri · `ketua_paca_dun` tiada |
| Sumber undi | Pemilik memilih Borang 14 secara eksplisit |
| Kebolehlihatan | Draf sehingga disiarkan; papan draf 404 kepada awam |
| URL awam | Kod kerusi — `/scoreboard/n27`, `/scoreboard/p129` |
| Pihak diserlahkan | Pemilik menanda slot sendiri — tiada padanan nama parti |
| Papan Parlimen | Membaca Borang 14 Parlimen sendiri, laluan kod yang sama |
| Migrasi | Papan terkini bagi setiap kerusi dikekalkan |

## Seni Bina

Tiga unit, setiap satu satu tujuan:

- **`App\Support\SeatScope`** — satu-satunya tempat peraturan kebenaran kerusi
  ditulis. Tiada kaitan dengan scoreboard secara khusus supaya Keanggotaan,
  Borang 14 dan PACA boleh menerimanya kemudian. *Skop kerja ini tidak
  termasuk mengubah ketiga-tiga itu.*
- **`App\Services\Pilihanraya\ScoreboardPayload`** — `boardPayload()` semasa
  diangkat keluar. Logik baca tulen, tiada kesedaran tentang `Request`.
  Dipanggil oleh tiga hujung.
- **`ScoreboardController`** (pemilik: index, data, settings, publish) dan
  **`PublicScoreboardController`** (papan tersiar sahaja). Laluan awam tiada
  auth, tiada tulis, dan peraturan cache tersendiri — memisahkannya bermakna
  "adakah papan draf terbocor?" boleh dijawab dengan membaca satu fail kecil.

Kekal mengikut konvensyen CLAUDE.md: laluan digelang oleh `auth` sahaja,
setiap kaedah membuat semakannya sendiri. `SeatScope` ialah pembantu yang
dipanggil pengawal, bukan middleware.

## Model Data

Bentuk semula `scoreboards` **di tempatnya** — ikut corak
`2026_07_16_100001_reshape_borang14_forms.php`, jangan `Schema::drop` dan bina
semula. Turutan MySQL: gugur FK → gugur index → gugur lajur (ralat 1553).

| Lajur | Perubahan | Sebab |
|---|---|---|
| `kawasan_type` | **baharu** — `dun` \| `parlimen` | Papan milik kerusi, bukan borang |
| `kawasan_id` | **baharu** | + `UNIQUE(kawasan_type, kawasan_id)` |
| `borang14_form_id` | **kekal**, kini nullable + tiada unique | Sumber undi *pilihan*. Nullable supaya papan boleh disiapkan sebelum Borang 14 wujud |
| `status` | **baharu** — `draf` \| `tersiar`, lalai `draf` | Gerbang penyiaran |
| `kod` | **baharu**, nullable, **UNIQUE** | Pemegang awam (`N27`, `P129`) |
| `pihak_kami` | **baharu**, json — `[1,3]` | Slot yang ditanda pemilik sebagai pihaknya |
| `title`, `minima`, `logo_path`, `candidates` | tidak berubah | |

**Mengapa `kod` ialah lajur, bukan carian.** `kadun.kod_dun` dan
`bandar.kod_parlimen` kedua-duanya nullable dan tiada index unique, jadi
`/scoreboard/n27` boleh merujuk dua kerusi. Daripada menambah index unique pada
jadual data induk yang hidup, kod **disalin ke papan ketika penyiaran** dan
dijadikan unique di situ. Penyiaran ditolak jika kerusi tiada kod, atau kod itu
sudah dipegang papan lain. Data induk tidak disentuh; ruang URL awam dijamin
bebas perlanggaran.

**Mengapa `borang14_form_id` kekal nullable.** Sesebuah kerusi mungkin mahu
papannya disiapkan — calon, gambar, logo — beberapa hari sebelum Borang 14
dibuka. Nullable menjadikan "belum pilih sumber" boleh diwakili. Mengikut
peraturan projek, nilai tiada kekal `null` dan dipaparkan `—`, bukan `0`.

### Migrasi

- Bagi setiap kerusi, papan dengan `updated_at` terkini dikekalkan;
  `borang14_form_id` sedia ada menjadi sumber pilihannya, jadi tiada perubahan
  yang kelihatan.
- Papan yang kalah dipadam **selepas** `logo_path` dan gambar calonnya
  dibandingkan dengan papan yang menang — fail yang masih dirujuk tidak
  dinyahpaut.
- `pihak_kami` diisi daripada `PH_PARTIES` yang dikekod tetap hari ini: slot
  yang nama partinya berada dalam senarai itu ditanda, supaya papan sedia ada
  mengekalkan serlahan semasa dan tidak reset kepada kosong.
- `down()` **enggan berjalan** dan melontar pengecualian, bukan kehilangan
  data — sama seperti `2026_07_16_100001`.
- Tulisan berbilang baris dibalut dalam transaksi.
- CI berjalan atas SQLite, produksi MySQL: sebarang `ALTER ... MODIFY` mentah
  memerlukan cabang pemacu.

## Kebenaran

```php
SeatScope::allows(User $u, string $type, int $id): bool
SeatScope::assert(User $u, string $type, int $id): void   // abort(403)
SeatScope::seats(User $u): array                          // [['dun', 171, 'PILAH'], …]
```

| Peranan | Kerusi `parlimen` | Kerusi `dun` |
|---|---|---|
| `super_admin` | semua | semua |
| `admin` | `bandar_id` sendiri sahaja | setiap DUN yang `bandar_id` = miliknya |
| `super_user` / `user` | tiada | `kadun_id` sendiri sahaja |
| `ketua_paca_dun` | tiada | tiada |

Tiga mod kegagalan yang mesti ditutup — sebab kelas ini wujud dan bukan empat
semakan sebaris:

1. **Kerusi null = tolak, jangan sekali-kali benarkan.** `admin` dengan
   `bandar_id = null` mesti tidak padan dengan apa-apa. Ditulis sebaris,
   `where('bandar_id', $user->bandar_id)` terhadap null boleh menjadi padan-semua
   secara senyap dalam sesetengah bentuk query — keluarga pepijat yang sama
   seperti hotfix Julai.
2. **Pengguna belum diluluskan tidak dapat apa-apa** tanpa mengira peranan.
   `status !== 'approved'` memintas sebelum jadual peranan dirujuk.
3. **`seats()` dan `allows()` tidak boleh bercanggah.** Kedua-duanya diterbitkan
   daripada satu peraturan persendirian supaya kerusi yang tidak muncul dalam
   pemilih tidak boleh ditulis dengan membina permintaan sendiri.

### Laluan

Scoreboard mesti keluar daripada kumpulan `['auth','admin']` — `EnsureAdmin`
akan menyekat pengguna yang hendak dibenarkan. Ia mendapat kumpulannya sendiri,
mengikut duluan PACA:

```php
Route::middleware(['auth'])->prefix('pilihanraya')->name('pilihanraya.')->group(function () {
    Route::get('/scoreboard', …)->name('scoreboard');
    Route::get('/scoreboard/data', …)->name('scoreboard.data');
    Route::post('/scoreboard/settings', …)->name('scoreboard.settings');
    Route::post('/scoreboard/publish', …)->name('scoreboard.publish');
});
```

Awalan dan awalan nama sama, jadi `pilihanraya.scoreboard` kekal berfungsi dalam
navigasi dan JSX — tiada perubahan URL, tiada panggilan `route()` yang pecah.

Laluan awam kekal tanpa auth dan bertukar kepada carian kod:
`/scoreboard/{kod}` dan `/scoreboard/{kod}/data`, hanya menyelesaikan papan
`status = 'tersiar'`. Papan draf dan kod tidak dikenali 404 secara sama — tiada
petunjuk kerusi mana yang wujud. Laluan angka `/scoreboard/{kadun}` dikekalkan
sebagai lencongan kekal (301) ke URL kod supaya pautan yang sudah tersebar
tidak pecah.

Kedua-dua laluan berkongsi satu segmen, jadi **turutan pendaftaran penting**:
laluan angka didaftarkan dahulu dengan `->whereNumber('kadun')`, laluan kod
selepasnya dengan `->where('kod', '[A-Za-z]\d+')`. Kod kerusi sentiasa bermula
dengan huruf (`N27`, `P129`) manakala id lama sentiasa angka penuh, jadi
kedua-duanya tidak boleh bertindih.

**Normalisasi kod.** Kod disimpan huruf besar (`N27`) dan dicari tanpa mengira
saiz huruf, supaya `/scoreboard/n27` dan `/scoreboard/N27` menuju papan yang
sama. Index unique dikenakan pada bentuk huruf besar yang disimpan.

### Navigasi

`AuthenticatedLayout.jsx:196` menggelang seluruh menu Pilihanraya kepada
super_admin/admin. `user`/`super_user` memerlukan blok ketiga yang mengandungi
Scoreboard sahaja — dibina cara bertahan yang sama seperti blok
`ketua_paca_dun` di `:222`, menyenaraikan itemnya secara eksplisit supaya item
Pilihanraya baharu pada masa depan tidak boleh terbocor masuk.

### Suntingan serentak

`admin` yang menyunting papan DUN dalam Parlimennya bermakna dua orang boleh
menyunting satu papan. Ini tulisan-terakhir-menang tanpa kunci. Kunci **tidak**
dibina; panel tetapan memaparkan "Dikemaskini oleh Ahmad, 2 minit lalu" supaya
perlanggaran kelihatan.

## Aliran Antara Muka

**Halaman pemilik.** `SeatScope::seats()` memacu segalanya. Satu kerusi → terus
dimuatkan, tiada pemilih. Beberapa kerusi → pemilih menyenaraikan kerusi
tersebut sahaja. `super_admin` mengekalkan lata penuh Negeri → Parlimen → DUN.
Panel tetapan menambah:

- **Sumber Undi** — dropdown Borang 14 kerusi itu (`jenis_pr`, `tahun`, `penjuru`)
- **Pihak Kami** — kotak semak setiap slot
- **Penyiaran** — togol Draf/Tersiar dengan URL awam dan butang salin

**Halaman awam.** Diselesaikan melalui `kod`, memaparkan satu papan, tiada
pemilih. `/scoreboard` kosong memaparkan senarai ringkas papan **tersiar**
dikumpulkan mengikut Negeri, supaya penanda halaman sedia ada tidak mati. Ini
hanya mendedahkan apa yang pemilik pilih untuk siarkan.

**Bahasa.** Semua teks pengguna dalam Bahasa Melayu, sepadan dengan salinan
sekeliling. Tiada lapisan i18n.

## Pengendalian Ralat

- Kerusi tanpa Borang 14 dipilih → papan memapar `—`, bukan `0`.
- Kerusi tanpa `kod_dun`/`kod_parlimen` → penyiaran ditolak dengan mesej yang
  menyatakan lajur mana perlu diisi dalam Data Induk.
- Kod sudah dipegang papan lain → penyiaran ditolak, menamakan kerusi pemegang.
- Sumber Borang 14 yang dipilih kemudian dipadam → `borang14_form_id` menjadi
  `null` melalui `nullOnDelete`; papan kembali ke keadaan "belum pilih sumber".

## Ujian

| Ujian | Yang dipaku |
|---|---|
| `SeatScopeTest` (unit) | matriks peranan × jenis kerusi; `bandar_id`/`kadun_id` null menolak; belum lulus menolak; `seats()`/`allows()` sepadan |
| `ScoreboardAccessTest` | setiap peranan, kerusi sendiri lawan kerusi asing, merentas empat hujung |
| `ScoreboardPublicTest` | draf 404; tersiar dipapar; kod tidak dikenali 404; URL angka melencong |
| `ScoreboardMigrationTest` | kerusi berbilang papan runtuh kepada terkini; gambar terselamat; `down()` enggan |
| `ScoreboardPayloadTest` | tiada sumber dipilih memapar `—` bukan `0`; kiraan `pihak_kami` betul |

Garis dasar projek: 20 kegagalan sedia ada (ujian Breeze `Auth`/`Profile`
kerana `UserFactory` tidak mengisi lajur `telephone` NOT NULL). Hanya risau
jika kiraan itu bertambah. Set ujian penuh perlu
`php -d memory_limit=1G vendor/bin/phpunit` — lalai 128M kehabisan memori dalam
`Cpdf.php`.

## Di Luar Skop

- Memindahkan Keanggotaan/Borang 14/PACA kepada `SeatScope`.
- Kunci suntingan serentak.
- Direktori awam yang boleh dicari melangkaui senarai ringkas mengikut Negeri.
- Jadual induk parti → gabungan (ditolak; pemilik menanda slot sendiri).
