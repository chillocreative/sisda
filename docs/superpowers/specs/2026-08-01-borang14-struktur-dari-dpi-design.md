# Borang 14 — Struktur Sebenar daripada Muat Naik DPPR/DPI

Tarikh: 2026-08-01

## Masalah

`Borang14Reference::deriveFromDpt()` menganggarkan struktur Borang 14 dengan
melayan setiap **Lokaliti** sebagai satu Pusat Mengundi dengan **satu** Saluran.
Itulah sebabnya skrin Keyin memaparkan amaran kuning:

> "Pusat Mengundi & Berdaftar dianggarkan daripada data DPT yang dimuat naik
> (dikumpul ikut Lokaliti, satu Saluran setiap Pusat Mengundi) — bukan pecahan
> Saluran rasmi gazet SPR."

Anggaran itu tidak perlu. Fail DPPR/DPI **sudah mengandungi** struktur sebenar:

| Lajur DPI | Contoh |
|---|---|
| `Parlimen` | TAMPIN |
| `DUN` | GEMAS |
| `DM` | FELDA JELAI 1 & 3 |
| `Lokaliti` | `1333401001 FELDA JELAI 1` |
| `Pusat Mengundi` | SEKOLAH KEBANGSAAN JELAI 1 |
| `Saluran` | 1 |

Import membuang `Pusat Mengundi` dan `Saluran` sepenuhnya — jadual
`pangkalan_data_pengundi` tiada lajur untuk kedua-duanya.

## Bukti bahawa ia struktur sebenar, bukan anggaran

Mengumpul 29,600 baris `DPI N34 Gemas.xlsx` mengikut (DM, Pusat Mengundi, Saluran)
menghasilkan 7 Daerah Mengundi, dan kiraannya sepadan TEPAT dengan senarai DM
bergazet pada muka surat 6 scoresheet Gemas:

| DM | Daripada DPI | Scoresheet |
|---|---|---|
| FELDA JELAI 1 & 3 | SK Jelai 1 = 4 saluran, SK Jelai 3 = 5 | 4 dan 5 |
| FELDA PASIR BESAR | SK Felda Pasir Besar = 4 | 4 |
| KAMPONG LADANG | SK Kampong Ladang = 4 | 4 |

Kiraan setiap saluran (450/450/704/704) ialah jumlah pengundi berdaftar sebenar,
bukan agihan rata.

## Keputusan

| Perkara | Keputusan |
|---|---|
| Migrasi | Dua lajur nullable, TIADA index. MySQL 8 `INSTANT` — tiada bina semula jadual walau berjuta baris |
| Data sedia ada | Utamakan struktur sebenar; jika lajur kosong, kekalkan anggaran hari ini |
| Lokaliti | Buang awalan kod berangka; simpan teks sahaja. Kod masuk ke `kod_lokaliti` jika kosong |
| Amaran kuning | Hilang untuk kerusi yang mempunyai struktur sebenar; kekal untuk yang lain |

## Model Data

```sql
ALTER TABLE pangkalan_data_pengundi
  ADD pusat_mengundi VARCHAR(255) NULL,
  ADD saluran        VARCHAR(50)  NULL;
```

Nullable, tiada default, tiada index, ditambah di hujung baris — algoritma
`INSTANT` MySQL 8, jadi tiada kunci dan tiada bina semula. Ini SENGAJA berbeza
daripada menambah index pada jadual yang sama, yang akan membina semula
berjuta baris semasa `migrate --force`.

Baris sedia ada kekal NULL. Itu bermakna "tidak diketahui", BUKAN "tiada
saluran" — dan kerusi itu jatuh kembali kepada anggaran.

## Import

`VoterDatabaseImport` menggunakan peta alias tajuk. Tambah:

- `pusatmengundi` => `['pusatmengundi', 'namapusatmengundi', 'pusat', 'tempatmengundi']`
- `saluran` => `['saluran', 'nosaluran', 'channel']`

**Pembetulan Lokaliti.** Sel `Lokaliti` membawa kod DAN teks
(`1333401001 FELDA JELAI 1`). Hari ini keseluruhan rentetan disimpan, jadi
pengumpulan berlaku pada nilai berawalan kod. Buang awalan berangka: simpan
`FELDA JELAI 1` dalam `lokaliti`, dan letak `1333401001` ke dalam `kod_lokaliti`
JIKA lajur kod berasingan tiada. Jangan timpa `kod_lokaliti` yang sudah diisi.

## Pembinaan Struktur

`Borang14Reference::deriveFromDpt()` dan `deriveFromDptForBandar()`:

1. Jika baris kerusi ini mempunyai `pusat_mengundi` DAN `saluran` yang tidak
   kosong -> bina struktur SEBENAR: DM > Pusat Mengundi > Saluran, dengan
   `berdaftar` = kiraan pengundi hidup bagi setiap saluran.
   Tandakan `source => 'dpt_sebenar'`.
2. Jika tidak -> anggaran hari ini, `source => 'dpt_estimate'`, tidak berubah.

Susunan saluran mengikut nombor, bukan rentetan: `'10'` mesti datang selepas
`'9'`, bukan selepas `'1'`.

Pengundi meninggal dikecualikan seperti sedia ada (`is_deceased`).

## Antara Muka

Amaran kuning dipapar hanya apabila `source === 'dpt_estimate'`. Bagi kerusi
dengan struktur sebenar, gantikan dengan pengesahan ringkas bahawa struktur
diambil daripada DPPR/DPI yang dimuat naik. Jangan senyapkan kedua-duanya —
pengendali perlu tahu yang mana satu sedang dipaparkan.

## Had yang diketahui

Kerusi yang dimuat naik sebelum perubahan ini kekal pada anggaran sehingga
failnya dimuat naik semula. Tiada pemindahan data — maklumat itu memang tidak
pernah disimpan.

## Ujian

| Ujian | Yang dipaku |
|---|---|
| Import | `Pusat Mengundi`/`Saluran` disimpan; awalan kod Lokaliti dibuang; `kod_lokaliti` sedia ada tidak ditimpa |
| Struktur sebenar | Baris DPI Gemas -> 7 DM dengan kiraan saluran yang betul dan berdaftar sebenar |
| Fallback | Baris tanpa pusat/saluran -> anggaran hari ini, `source` masih `dpt_estimate` |
| Campuran | Kerusi dengan SESETENGAH baris berpusat -> jangan hasilkan struktur separa senyap; pilih satu mod dan nyatakan mana |
| Susunan saluran | Saluran 10 datang selepas 9 |
| Migrasi | Lajur wujud, nullable; baris sedia ada NULL |
