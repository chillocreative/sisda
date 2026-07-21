# Borang 14 — Struktur Manual (borang kosong untuk PR akan datang)

Tarikh: 2026-07-21
Sambungan daripada `docs/handover-2026-07-21.md` §5.

## Masalah

Pada malam mengira PRN akan datang, pengguna perlu borang kosong untuk diisi. Hari ini:

- ✅ Berjaya jika kerusi ada data DPT — tetapi DPT sentiasa menganggap **1 saluran per Pusat
  Mengundi**, jadi pusat dengan 3 saluran tidak boleh diisi dengan tepat.
- ✅ Berjaya jika ada scoresheet tahun lepas untuk diwarisi strukturnya.
- ❌ Kerusi tanpa kedua-duanya **buntu**: banner "Data Borang 14 belum tersedia", tiada butang.
  `data()` guna `first()`, jadi tiada borang pernah dicipta.
- ❌ Tiada UI tambah/buang Pusat Mengundi, tiada cara set bilangan saluran.
- ❌ Tiada endpoint menyimpan struktur yang dibina manual, jadi borang yang diisi tangan
  tidak boleh menjadi sumber warisan untuk PR seterusnya.

## Matlamat

Pengguna boleh membina struktur (Daerah Mengundi → Pusat Mengundi → saluran) dengan tangan
bagi mana-mana kerusi, mengisi undi ke atasnya, dan struktur itu diwarisi oleh PR berikutnya.

## Keputusan reka bentuk

| Soalan | Keputusan | Sebab |
|---|---|---|
| Sumber struktur | Taip tangan sepenuhnya | Tiada kebergantungan pada padanan rentetan geografi yang rapuh; berfungsi untuk mana-mana kerusi |
| Simpanan | Lajur `structure` sedia ada | `resolveReference()`, `referenceFromStructure()`, PDF dan warisan terus berfungsi; tiada migrasi |
| Skop | Sentiasa boleh sunting (draf sahaja) | Turut membaiki masalah DPT 1-saluran/pusat |
| Undi sedia ada | Cascade + pengesahan berangka | Tiada baris yatim; tiada undi hilang senyap |
| UI | Panel penuh menggantikan grid | Ruang untuk 30+ pusat; jelas bila sedang menyunting struktur vs mengisi undi |
| Kebenaran | `admin` + `super_admin`, `published` disekat | Sepadan dengan kebenaran mengisi undi; melindungi rekod rasmi |

## Bahagian 1 — Data & backend

### Format simpanan

Struktur manual ditulis ke `borang14_forms.structure` dalam bentuk `rows` yang sama seperti
scoresheet, tetapi setiap baris hanya membawa **bentuk**, bukan angka bercetak:

```json
{
  "origin": "manual",
  "calon": [],
  "rows": [
    { "row_id": "pm_a1", "dm": "KUALA JEMAPOH", "pusat": "SEKOLAH KEBANGSAAN TENGKEK", "saluran": "1" },
    { "row_id": "pm_a1", "dm": "KUALA JEMAPOH", "pusat": "SEKOLAH KEBANGSAAN TENGKEK", "saluran": "2" },
    { "row_id": "pm_pos", "dm": "",             "pusat": "",                           "saluran": "UNDI POS" }
  ]
}
```

Tiada `a`, tiada `undi`, tiada `jumlah_undian` — kerana tiada sheet bercetak untuk dibaca.
Ketiadaan itu bermakna, bukan kekurangan: **jangan sesekali isi `0`.** Tiada migrasi diperlukan.

Satu baris **per saluran** (bentuk yang `referenceFromStructure()` sudah baca), manakala UI
bekerja dengan satu entri **per Pusat Mengundi** berserta kiraan saluran. Backend yang
mengembangkan `saluran_count` → baris, dan meruntuhkannya semula apabila `data()` menyuap
panel. `row_id` **disimpan** dalam setiap baris (dikongsi oleh semua saluran satu pusat) —
tanpanya, suntingan kedua tidak boleh membezakan "pusat dinamakan semula" daripada
"pusat lama dibuang, pusat baharu ditambah", dan cascade akan memadam undi yang sepatutnya
berpindah. `row_id` dijana oleh backend pada simpanan pertama; struktur warisan/scoresheet
yang tiada `row_id` diberi satu semasa dibuka untuk disunting.

### Kesan pada kod sedia ada

| Tempat | Kesan |
|---|---|
| `resolveReference()` | Berfungsi terus. Struktur borang ini sudah mengalahkan anggaran DPT, jadi saluran 2 & 3 muncul tanpa perubahan keutamaan. |
| `referenceFromStructure()` | Berfungsi terus (`berdaftar` sudah sentiasa `null`). Tambahan: pulangkan `source: 'manual'` bila `origin === 'manual'` supaya UI melaporkan asal-usul dengan jujur. |
| `crosscheckIssues()` | **Mesti dikawal.** Baris manual tiada `a`/`jumlah_undian`; dibiar begitu ia akan menuduh "(A) dijangka 0, dapat 250" pada setiap baris. Guard: pulangkan `[]` bila `origin === 'manual'`. |
| `writeForm()` (muat naik) | Tidak diubah. Scoresheet menimpa struktur manual — betul dari segi keutamaan, dan snapshot sedia ada menjadikannya boleh dipulihkan. |

### Endpoint baharu

**`POST /pilihanraya/borang14/struktur`**

Input: `kawasan_type`, `kawasan_id`, `jenis_pr`, `tahun`, `rows[]` —
setiap baris `{ row_id, dm, pusat, saluran_count }` (dan bendera `undi_awal`, `undi_pos`).

- `firstOrCreate` borang — **inilah yang memecahkan kebuntuan "belum tersedia"**.
- Pengawal eksplisit mengikut konvensyen CLAUDE.md:
  `if (!$user->isSuperAdmin() && !$user->isAdmin()) abort(403)`.
- `abort(403)` jika `status === 'published'` — struktur rekod rasmi tidak boleh berubah
  di bawah undi yang sudah diterbitkan.
- Dalam **satu `DB::transaction`**:
  1. `Borang14Snapshot::create(reason: 'before_structure_edit')` — butang Revert sedia ada
     terus berfungsi tanpa perubahan.
  2. Tulis semula kunci `borang14_votes.pusat` bagi pusat yang **dinamakan semula**.
  3. Padam baris undi bagi pusat/saluran yang **dibuang**.
  4. Simpan `structure`.
- Namakan-semula dikesan melalui `row_id` stabil yang dihantar balik oleh frontend,
  **bukan** melalui teka nama.

**`POST /pilihanraya/borang14/struktur/kesan`** (pratonton, tanpa tulis)

Pulangkan bilangan baris undi dan jumlah undi yang akan dipadam oleh cadangan struktur itu,
supaya dialog pengesahan memaparkan angka sebenar dan bukan amaran kabur.

## Bahagian 2 — Frontend

### Fail baharu `resources/js/Pages/Pilihanraya/Borang14/StrukturPanel.jsx`

~250 baris, komponen berasingan — bukan tambahan pada `KeyinTab.jsx` (sudah 491 baris dan
sedang menguruskan grid, autosave, parti dan penerbitan). Panel hanya tahu satu perkara:
senarai `{row_id, dm, pusat, saluran_count}` masuk, senarai yang sama keluar.

```
Keyin ▸ Juasseh ▸ PRN 2027      [Sunting Struktur]
─────────────────────────────────────────────
DM: KUALA JEMAPOH                    [+ Pusat]
  ▸ SK TENGKEK              saluran: [3] [🗑]
  ▸ SK JEMAPOH              saluran: [1] [🗑]
DM: JUASSEH                          [+ Pusat]
  ▸ DEWAN ORANG RAMAI       saluran: [2] [🗑]
[+ Daerah Mengundi]
☑ UNDI AWAL   ☑ UNDI POS
            [Batal]  [Simpan Struktur]
```

### Perubahan pada `KeyinTab.jsx` (kecil)

- Butang `Sunting Struktur` di sebelah pemilih kawasan — dipapar bila `geographyComplete`,
  disembunyikan bila `status === 'published'` atau pengguna bukan admin.
- Banner buntu (baris 332) mendapat butang **`Cipta Borang 14 kosong`** yang membuka panel
  yang sama dalam keadaan kosong. Teks banner ditukar daripada penyataan mati kepada
  ajakan bertindak.
- Bila panel dibuka, grid diganti. `Simpan` → `POST struktur` → ambil semula `data()` →
  grid dilukis semula.

### Model saluran

Setiap Pusat Mengundi membawa satu **kiraan integer** (1–20), bukan senarai bebas.
Saluran dijana `"1".."N"` — sepadan dengan `normalizeSaluran()`. Menurunkan 3 → 2 dikira
sebagai membuang saluran 3 dan mencetuskan dialog pengesahan yang sama seperti membuang pusat.

### UNDI AWAL / UNDI POS

Dua kotak semak, disimpan sebagai baris `pusat: ''` seperti yang dihasilkan scoresheet —
bentuk yang `referenceFromStructure()` sudah tahu baca.

### Dialog pengesahan

Guna semula `ConfirmDialog.jsx`, memaparkan angka daripada endpoint pratonton:

> Membuang SK TENGKEK akan memadam 9 baris undi (jumlah 1,204 undi). Teruskan?

## Bahagian 3 — Ujian

`tests/Feature/Borang14StrukturManualTest.php`, ditulis TDD:

1. Kerusi tanpa DPT & tanpa scoresheet: simpan struktur → `data()` pulangkan `hasData: true`
   dengan pusat yang betul (kebuntuan pecah).
2. **Ujian anti-yatim** — simpan struktur, tulis undi melalui `saveVote()`, ambil semula
   `data()`: setiap sel yang ditulis muncul kembali. Ini yang akan menangkap semula pepijat §2b.
3. Namakan semula pusat → undi mengikut ke kunci baharu, jumlah kekal sama.
4. Buang pusat berundi → undi pusat itu sahaja hilang; pusat lain tidak tersentuh.
5. Struktur manual mengalahkan anggaran DPT — pusat 3-saluran kekal 3 saluran, bukan jatuh ke 1.
6. `crosscheck_issues` kosong bagi borang manual — bukan penuh amaran palsu.
7. Borang `published` → 403; peranan `user` → 403.
8. Snapshot dicipta → Revert memulihkan struktur dan undi lama.

Garis dasar ujian semasa: **20 gagal / 342 lulus**. 20 kegagalan itu sedia ada
(`UserFactory` tidak set lajur NOT NULL `telephone`). Hanya risau kalau bilangan itu naik.

## Bukan dalam skop (YAGNI)

- Benih struktur daripada Lokaliti/DaerahMengundi, salin-daripada-kerusi-lain, import CSV.
- Menamakan calon/parti — sudah dikendalikan `saveParties()`.
- `berdaftar` per saluran — kekal `null`. Tiada sumber jujur untuk PR akan datang,
  dan `null` mesti kekal `null`.
- Pengawal peranan bagi kaedah Borang 14 **lama** (handover §6) — kerja berasingan.

## Risiko yang diterima

Muat naik scoresheet kemudian akan menimpa struktur manual, kerana `writeForm()` menulis
`structure` tanpa syarat. Itu betul dari segi keutamaan — scoresheet rasmi patut menang —
dan `writeForm()` sudah membuat snapshot dahulu, jadi ia boleh dipulihkan. Tindakan:
tambah nota amaran pada dialog muat naik, bukan menyekat muat naik.
