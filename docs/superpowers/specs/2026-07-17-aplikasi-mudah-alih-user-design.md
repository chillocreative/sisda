# Aplikasi Mudah Alih SISDA — Peranan `user` (Android + iOS)

**Tarikh:** 2026-07-17
**Status:** Design — menunggu semakan user

## Konteks

`sisda_app/` sudah wujud dan sedang digunakan, tetapi ia bukan aplikasi sebenar — ia
**shell WebView**. Yang native hanyalah splash, login (guna telefon), register, forgot
password, dan satu home screen dengan imbasan KP (OCR ambil No. IC). Selebihnya —
Dashboard, Culaan, Pengundi, Laporan, Profil — hanya memuatkan route web di dalam
`WebViewScreen`, auto-login melalui token sekali guna (`/mobile-web-auth`).

API mudah alih hari ini ada **7 endpoint** sahaja: login, register, forgot-password,
tiga dropdown geografi, dan logout. Tiada API data langsung — tiada carian pengundi,
tiada penghantaran borang.

Akibatnya kerja lapangan bergantung sepenuhnya pada talian. Di kawasan luar bandar,
seorang pencatat yang hilang isyarat akan hilang kerjanya.

### Peranan `user` — apa yang sebenarnya dibenarkan

Disahkan daripada `ReportsController` dan `VoterDataMasker` pada 2026-07-17:

| Keupayaan | Status |
|---|---|
| Lihat Hasil Culaan / Data Pengundi | Hanya dalam **Kadun** sendiri, **atau** rekod yang dia sendiri hantar |
| Cipta Hasil Culaan | Ya |
| Edit Hasil Culaan | Hanya jika `parlimen` sama dengan Bandar dia |
| Padam rekod | **Tidak** (`abort(403)`) |
| Export | **Tidak** (`abort_if($user->isUser(), 403)`) |
| Cipta Data Pengundi | **Tiada route** — Data Pengundi datang dari DPT/upload |

### Tiga dapatan yang membentuk design ini

1. **Butang "Borang Data Pengundi" hari ini rosak.** `home_screen.dart:230` membuka
   `/reports/data-pengundi/create` — route itu **tidak wujud**. Hanya index/edit/update/
   delete yang ada. Butang itu 404 dalam WebView. Ia dibuang.

2. **`VoterDataMasker` bercanggah dengan cache luar talian.** Bagi penonton berperanan
   `user`, setiap rekod yang dihantar oleh mana-mana `user` lain dimaskkan: `no_ic`,
   `umur`, `bangsa`, `no_tel`, `alamat`, `poskod`, `negeri`, `bandar`,
   `pendapatan_isi_rumah` semuanya jadi `****`. Model ini mengandaikan data kekal di
   server dan user hanya lihat paparan. Cache luar talian membalikkan andaian itu —
   telefon terpaksa simpan No. IC, no. telefon dan alamat rumah dalam bentuk plaintext
   pada peranti yang boleh hilang atau dicuri. **Keputusan: tulis sahaja yang luar
   talian; bacaan kekal perlukan talian.**

3. **Borang Culaan ada 7 bahagian, 2,000 baris JSX, ~40 medan.** Dua bahagian
   (Isi Rumah, Bantuan) hanya muncul bila `has_sumbangan` dihidupkan. Kod web sudah
   bergelut dengan lompatan skrol akibat toggle itu (`Create.jsx:161`).

## Keputusan yang telah dipersetujui

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Bentuk aplikasi | Native sepenuhnya — **arah tuju**, dicapai berperingkat (lihat Skop V1) |
| 2 | Kerja utama | Carian **dan** culaan — kedua-dua sama penting |
| 3 | Luar talian | **Tulis sahaja** — culaan beratur, sync automatik bila ada data |
| 4 | Bacaan luar talian | Tiada cache pengundi — hormati `VoterDataMasker` |
| 5 | Susunan borang | **Checklist hub** — 7 bahagian, tap untuk isi satu-satu |
| 6 | Home screen | **Search-first** — kamera duduk dalam bar carian |
| 7 | Sync gagal | Peti masuk **"Perlu Perhatian"** — tiada apa dibuang senyap |
| 8 | Skop V1 | Culaan hujung-ke-hujung sahaja |

## Skop V1

Arah tuju ialah native sepenuhnya (Keputusan #1), tetapi V1 **tidak** cuba sampai ke
sana sekali gus. V1 menghantar satu gelung lengkap — cari pengundi → rekod Culaan →
sampai ke server, ada isyarat atau tidak — supaya bahagian paling berisiko (sync luar
talian) diuji di lapangan awal. Skrin lain bertukar native selepas gelung ini terbukti.

**Native:**
splash · login · register · forgot password · home (search-first) · carian pengundi +
imbas KP · butiran pengundi (baca sahaja) · borang Culaan (checklist hub) · peti masuk
Perlu Perhatian.

**Kekal WebView:**
Dashboard · Laporan penuh · senarai Data Pengundi · Profil.
Jambatan `/mobile-web-auth` sedia ada terus berfungsi.

**Dibuang:**
Butang "Borang Data Pengundi" pada home screen (404 — lihat Dapatan #1).

## Seni bina

```
lib/
  models/          Jenis Dart tulen (Voter, CulaanDraft, SyncResult). Tiada I/O.
  data/local/      Drift/SQLite. Simpan DRAF SAHAJA — tiada data pengundi.
  data/remote/     Klien MobileApi bertaip. Satu method per endpoint.
  sync/            Enjin baris gilir. Logik tulen atas dua antara muka di atas.
  features/<nama>/ UI per feature. Bercakap dengan controller sahaja.
```

**Dua peraturan sempadan:**

- `sync/` **tidak** import Flutter — supaya boleh diuji unit tanpa peranti.
- UI **tidak** import `data/` terus — sentiasa melalui controller.

`data/local/` yang hanya menyimpan draf ialah **garis privasi**. Tiada PII pengundi
disimpan pada peranti. Ini bukan pilihan gaya — ia yang menjadikan Dapatan #2 selamat.

### Perubahan server

API mudah alih berkembang dari 7 ke 13 endpoint. Enam yang **baharu**:

| Endpoint | Nota |
|---|---|
| `GET /api/mobile/voters/search` | Ikut skop peranan; lalui `VoterDataMasker` |
| `GET /api/mobile/voters/{ic}` | Sama |
| `POST /api/mobile/culaan` | Cipta; terima kunci idempotency |
| `GET /api/mobile/culaan/options` | Taksonomi borang (jenis pekerjaan, bantuan, dll.) |
| `GET /api/mobile/culaan/mine` | Sejarah penghantaran sendiri |
| `POST /api/mobile/token/refresh` | Elak logout paksa di lapangan |

Logik skop peranan **diekstrak ke satu service**, bukan disalin dari
`ReportsController`. Peraturan itu halus (Kadun ATAU `submitted_by`; Parlimen untuk
edit) dan salinan akan menyimpang.

Dua kekangan repo yang dihormati:

- **Bungkus transaksi.** Laluan cipta Culaan menulis banyak baris melalui
  `VoterSyncService`. CLAUDE.md menandakan lapisan HTTP ini tiada transaksi. Endpoint
  baharu dibungkus dengan betul.
- **Masking tidak boleh bocor.** Setiap respons melalui `VoterDataMasker`. API mudah
  alih tidak boleh jadi lubang yang memulangkan data yang web sengaja sembunyikan.

## Aliran data — draf ialah unit kerja

Satu Culaan hidup dalam SQLite tempatan dari ketukan kekunci pertama:

```
draft → queued → syncing → synced (padam tempatan)
                        ↘ failed  (masuk Perlu Perhatian)
```

Checklist hub **ialah** keadaan `draft` yang dipaparkan — "5 daripada 7 bahagian siap"
hanyalah query atas baris tempatan. Tiada apa disimpan dalam memori sahaja; aplikasi
mati atau bateri habis tidak menghilangkan apa-apa.

### Idempotency — wajib

Telefon POST, server tulis rekod, respons hilang kerana isyarat mati. Telefon nampak
timeout dan cuba semula — dua rekod Culaan untuk satu pengundi. Kerana `VoterSyncService`
menyebarkan baris merentas dua jadual, pendua bukan pepijat kosmetik; ia merosakkan
kiraan hiliran.

Setiap draf membawa **UUID jana-klien sejak ia dicipta**, dihantar sebagai kunci
idempotency. Server simpan, unique-indexed. Kunci yang sudah dilihat memulangkan
**hasil asal**, bukan tulis semula. Ini yang menjadikan cuba-semula automatik selamat.

### Pengelasan kegagalan

| Jenis | Contoh | Tindakan |
|---|---|---|
| **Sementara** | Tiada isyarat, timeout, 5xx | Kekal `queued`, backoff eksponen (had ~5 minit). User **tidak** diberitahu — ini kehidupan biasa di lapangan. |
| **Auth** | 401, token tamat | Kekal `queued`, minta login semula. **Draf kekal hidup melepasi logout.** |
| **Kekal** | 403 Parlimen, 422 validation, 409 pendua | Berhenti serta-merta → `failed` → Perlu Perhatian. |

Cuba semula kegagalan kekal selama-lamanya ialah cara aplikasi luar talian membakar
bateri dan menyembunyikan data rosak daripada user. Sebab itu tiga baldi ini berasingan.

**Pencetus sync:** talian kembali · aplikasi ke depan · pull-to-refresh · satu percubaan
terus selepas user tekan Hantar (supaya kes biasa terasa serta-merta).

### Peti masuk "Perlu Perhatian"

Sebab dipapar dalam BM biasa, bukan kod HTTP:

- *"Rekod ini di luar Parlimen anda"* (403)
- *"IC ini telah direkod oleh Ahmad pada 3:15 petang"* (409)
- *"Ruangan Bil. Isi Rumah diperlukan"* (422)

Setiap baris tawarkan **betulkan-dan-hantar-semula** atau **buang-dengan-pengesahan**.
Lencana kiraan pada home screen supaya tidak boleh diabaikan.

### Aliran masked-create kekal utuh

User prefill daripada pengundi **semasa ada talian**. Draf simpan `locked_source_id`
plus placeholder `****` — **bukan** nilai sebenar. Server tukar kepada nilai benar
semasa sync, sama seperti web. Jika rekod sumber berubah atau hilang, ia kegagalan
kekal → Perlu Perhatian.

Telefon tidak pernah memegang data yang user tidak dibenarkan lihat, walaupun dalam draf.

## UI/UX

### Home — search-first

Bar carian di atas, **kamera sebagai ikon di dalam bar** — mengimbas ialah "cari guna
gambar", yang memang itulah fungsinya (OCR keluarkan IC → carian). Satu tindakan utama
untuk kedua-dua kerja. Di bawahnya, rekod terkini merangkap baris gilir sync, jadi
keadaan luar talian tidak pernah tersembunyi.

Jalur amber: **"3 culaan menunggu talian"** dengan ikon `↻`.

### Borang Culaan — checklist hub

Hub menyenaraikan 7 bahagian dengan tanda siap; tap mana-mana untuk isi pada skrin
sendiri.

```
Ahmad bin Ali
5 daripada 7 bahagian siap
  Maklumat Peribadi     ✓
  Maklumat Alamat       ✓
  Kawasan Mengundi      ✓
  Maklumat Politik      ✓
  Status Pengundi       ✓
  ☑ Ada Sumbangan
  Isi Rumah        Belum diisi ›
  Bantuan          Belum diisi ›
              [ Hantar ]
```

Kelebihan berbanding skrol panjang atau wizard: skrin pendek, toggle `has_sumbangan`
hanya menambah/membuang dua baris (tiada lompatan skrol — masalah `Create.jsx:161`
lenyap), pakar boleh terus ke bahagian yang dia perlu, dan yang belum siap kelihatan
sebelum hantar.

Semua teks BM, sepadan dengan copy sedia ada.

## Ujian

`sisda_app/` hari ini ada **sifar ujian**. Itu berubah bermula dengan bahagian paling
berisiko.

| Lapisan | Ujian |
|---|---|
| **Enjin sync** | Unit, dengan API palsu + DB dalam-memori. Setiap cabang: tiga baldi kegagalan, backoff, dan khususnya **POST-pendua-selepas-respons-hilang**. Tiada peranti perlu. |
| **Borang** | Widget test atas hub — terutama cabang `has_sumbangan` tambah/buang dua bahagian. |
| **Server** | Feature test endpoint baharu: skop peranan, masking tidak bocor, replay idempotency. |
| **Emulator** | Toggle mod pesawat untuk hujung-ke-hujung sebenar. Batu tanda terakhir, selepas ujian unit hijau. |

Baseline PHP kekal **20 gagal / 127 lulus**. Hanya risau jika bilangan itu bertambah.

## Yang sengaja ditinggalkan (YAGNI)

- Cache pengundi luar talian — bercanggah dengan masking (Dapatan #2)
- Push notification — tiada kes guna yang disahkan lagi
- Penggantian penuh WebView — tunggu maklum balas lapangan V1 dahulu
- Sync dua-hala / penyelesaian konflik — user tidak boleh padam; konflik hanya pendua
