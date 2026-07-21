# Status EKYC per anggota — reka bentuk

Tarikh: 2026-07-21

## Masalah

Fail keanggotaan yang dimuat naik (contoh: `KEANGGOTAAN N9 - JUASSEH.xlsx`) membawa
lajur **`STATUS EKYC`** dengan nilai `Completed` / `Pending` bagi setiap anggota.
SISDA mengabaikan lajur ini sepenuhnya.

Pada hari ini "EKYC" dalam SISDA ialah sifat **batch**, bukan sifat anggota:

```
EKYC = status_anggota = 'aktif'  OR  batch_id ∈ (batch yang ditanda is_ekyc)
```

Kerana batch Juasseh tidak ditanda dan tiada anggota berstatus `aktif`, kad
"Jumlah Ahli 917 / Aktif/ EKYC — 0" memaparkan **0**, sedangkan fail sebenarnya
mengandungi 331 `Completed` dan 591 `Pending` (922 baris; 917 selepas dedup IC).

## Keputusan reka bentuk

1. **Nilai per-anggota mengatasi; peraturan lama jadi sandaran.** Jika fail
   memberitahu status anggota itu, itulah yang dikira. Jika tidak (batch lama,
   import PDF, fail tanpa lajur EKYC), peraturan sedia ada dikekalkan supaya
   tiada angka sedia ada berubah.
2. **Muat naik semula** batch Juasseh melalui UI selepas perubahan importer —
   tiada arahan artisan sekali guna.
3. **Lajur EKYC sahaja.** Lajur lain dalam fail (E-MEL, POSKOD, TARIKH LAHIR,
   STATUS KEANGGOTAAN, PARLIMEN, DUN) di luar skop perubahan ini.

## Skema

Migrasi baharu menambah satu lajur pada `keanggotaan`:

| Lajur | Jenis | Nilai |
|---|---|---|
| `status_ekyc` | `VARCHAR(20) NULL` | `'completed'`, `'pending'`, atau `NULL` |

`NULL` bermakna **fail tidak menyatakan** — bukan "pending". Ini mematuhi
peraturan projek: *Unknown is not zero*. Lajur tidak mempunyai nilai lalai
selain `NULL`, dan migrasi hanya menambah (tiada drop/recreate) supaya selamat
untuk `migrate --force` di produksi.

## Importer

`App\Imports\KeanggotaanImport`:

- Alias pengepala baharu `EKYC_KEYS = ['statusekyc', 'ekyc', 'ekycstatus']`,
  dibandingkan selepas huruf kecil + buang aksara bukan alfanumerik (sama seperti
  alias sedia ada), ditambah ke `EMPTY_MAP` dan `detectHeader()`.
- `normaliseEkyc(?string): ?string`
  - `completed`, `complete`, `selesai`, `lengkap`, `ya`, `yes`, `1` → `'completed'`
  - `pending`, `belum`, `tidak`, `no`, `0` → `'pending'`
  - kosong / nilai tidak dikenali → `null`
- `extract()` mengembalikan kunci `ekyc`; `flushMembers()` menulisnya ke
  `status_ekyc`. Gabungan pelbagai helaian (`mergeMembers`) mengekalkan tingkah
  laku sedia ada: nilai pertama menang, helaian kemudian mengisi yang `null`.

## Peraturan pengiraan — satu tempat sahaja

Peraturan EKYC kini ditulis semula **empat kali**:
`DashboardController:376`, `KeanggotaanController:750`, ungkapan SQL mentah
`KeanggotaanController:809`, dan gelung carta `KeanggotaanController:921`.
Perubahan ini memusatkannya ke model `Keanggotaan`:

```php
// Scope Eloquent
Keanggotaan::query()->ekycVerified($ekycBatchIds)

// Ungkapan mentah untuk SUM(CASE WHEN ... THEN 1 ELSE 0 END)
[$expr, $bind] = Keanggotaan::ekycSql($ekycBatchIds);
```

Kedua-duanya menghasilkan logik yang sama:

```
status_ekyc = 'completed'
  OR (status_ekyc IS NULL
      AND (status_anggota = 'aktif' OR batch_id IN (<batch EKYC>)))
```

SQL yang dijana ialah SQL mudah (tiada `REGEXP`/`TIMESTAMPDIFF`), jadi ia
berjalan pada SQLite (CI) dan MySQL (produksi). Empat pemanggil ditukar untuk
menggunakan pembantu ini; tiada logik EKYC lain kekal di dalam controller.

## UI

`Pages/Keanggotaan/Senarai.jsx` — lajur EKYC:

| `status_ekyc` | Paparan |
|---|---|
| `completed` | tanda hijau (`CheckCircle2`) |
| `pending` | sengkang kelabu (`—`) |
| `null` | sandaran kepada tanda batch seperti sekarang |

Baris butiran anggota memaparkan `EKYC: Ya / Tidak / —` mengikut peraturan sama.
Label kad (`Aktif/ EKYC`) di Dashboard dan Analisa kekal tidak berubah — hanya
angkanya betul. Controller mesti memilih `status_ekyc` dalam senarai anggota.

## Ujian

1. `KeanggotaanImport` mengekstrak `ekyc` daripada susun atur pengepala sebenar
   fail Juasseh (`NAMA … STATUS KEANGGOTAAN, STATUS EKYC, PARLIMEN, DUN`), dan
   memulangkan `null` bila lajur tiada.
2. `normaliseEkyc` memetakan varian `Completed`/`Pending`/kosong dengan betul.
3. Ujian ciri: anggota `status_ekyc = 'pending'` dalam batch yang **ditanda
   EKYC** *tidak* dikira — nilai per-anggota mengatasi tanda batch. Ini regresi
   yang akan memalsukan 917.
4. Ujian ciri: anggota `status_ekyc = NULL` dalam batch ditanda EKYC **dikira**
   (peraturan sandaran kekal).

Garis dasar suite: 20 gagal / 127 lulus (kegagalan `UserFactory` sedia ada).
Hanya bimbang jika jumlah itu bertambah.

## Hasil dijangka

Selepas batch Juasseh dimuat naik semula: **Jumlah Ahli 917 · Aktif/ EKYC — 331**.
