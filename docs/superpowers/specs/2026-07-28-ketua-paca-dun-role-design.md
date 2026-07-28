# Peranan `ketua_paca_dun` — Ketua PACA DUN

**Tarikh:** 2026-07-28
**Status:** Diluluskan untuk pelaksanaan

## Masalah

Setiap DUN mempunyai seorang Ketua PACA yang bertanggungjawab menyusun roster
petugas (PA/CA) bagi kerusi DUN itu sahaja. Hari ini tugas itu hanya boleh
dilakukan oleh `admin` (skop Parlimen) atau `super_admin` (skop kebangsaan).
Memberikan akses `admin` kepada seorang Ketua PACA membuka keseluruhan
subsistem Pilihanraya (War Room, Borang 14, Analisa, Scoreboard) dan semua DUN
lain dalam Parlimen yang sama — jauh melebihi keperluan.

## Penyelesaian

Peranan baharu `ketua_paca_dun` yang boleh melihat **satu menu sahaja**
(Pilihanraya > PACA) dan **satu kerusi sahaja** (DUN pada `users.kadun_id`).

### Ringkasan keputusan reka bentuk

| Soalan | Keputusan |
|---|---|
| Cara akaun diwujudkan | Pengguna daftar seperti biasa (`role=user`), Admin luluskan, kemudian Admin tukar peranan melalui Menu > User |
| Hak pada halaman PACA | Sunting penuh **kecuali** operasi memusnah (Bina Semula Roster, Pulih Snapshot) |
| Halaman pendaratan | Terus ke `/pilihanraya/paca`; Dashboard disekat |
| Pemilih kerusi | Kerusi tunggal dipilih automatik; dropdown Negeri/Parlimen/DUN disembunyikan |

## Skop

### Tiada migrasi

`users.role` ialah lajur `string` dengan `default('user')` (rujuk
`2025_11_19_132925_add_role_to_users_table.php`). Tiada enum pangkalan data,
jadi menambah nilai peranan ialah perubahan kod semata-mata.

### 1. Plumbing peranan

- `User::isKetuaPacaDun()` — mengikut corak `isAdmin()` / `isSuperUser()`.
- `UsersController::store()` dan `update()` — tambah `ketua_paca_dun` pada
  peraturan `role => required|in:...`. Sekatan Admin sedia ada kekal: Admin
  tidak boleh cipta `super_admin` dan tidak boleh cipta pengguna di luar
  `bandar_id` sendiri. Kesannya, Admin hanya boleh melantik Ketua PACA DUN
  dalam Parlimennya sendiri — tepat seperti dikehendaki.
- `kadun_id` sudah `required` pada kedua-dua kaedah, jadi `ketua_paca_dun`
  tidak mungkin wujud tanpa DUN untuk diskop. Tiada perangkap "guard
  bergantung pada medan nullable" seperti kelemahan IDOR Julai 2026.
- `Users/Index.jsx` — tambah pada dropdown cipta/sunting, dropdown penapis,
  `getRoleLabel()` dan `getRoleBadgeColor()`.
- Borang pendaftaran (`Auth/Register.jsx`, `RegisteredUserController`) TIDAK
  disentuh.

### 2. Capaian laluan

Keseluruhan kumpulan `/pilihanraya/*` berada di belakang
`middleware(['auth','admin'])`. `EnsureAdmin` akan menolak peranan baharu
sebelum mana-mana pengawal berjalan.

- Middleware baharu `EnsurePacaAccess` (alias `paca`) membenarkan
  `super_admin` | `admin` | `ketua_paca_dun`.
- 12 laluan `/paca/*` dipindahkan keluar daripada kumpulan `admin` ke kumpulan
  sendiri: `Route::middleware(['auth','paca'])->prefix('pilihanraya')->name('pilihanraya.')`.
  URL dan nama laluan kekal sama. Tiada apa-apa lain dalam Pilihanraya
  terdedah.
- Middleware ialah lapisan luar sahaja. Mengikut konvensyen repositori,
  kebenaran sebenar kekal dalam pengawal.

### 3. Skop DUN (teras keselamatan)

- `PacaBuilderService::seatsWithScoresheet()` menerima parameter
  `?int $kadunId`. Apabila diberi, hanya kerusi jenis DUN dengan
  `kawasan_id === $kadunId` dikembalikan.
- `PacaController::index()` menghantar `$user->kadun_id` bagi peranan baharu.
- `PacaController::assertBolehAkses()` mendapat cabang ketiga: bagi
  `ketua_paca_dun`, `kawasan_type` mesti `KAWASAN_DUN` **dan** `kawasan_id`
  mesti sama dengan `$user->kadun_id`; jika tidak `abort(403)`. Semakan ini
  berjalan SEBELUM sebarang carian `Borang14Form`, supaya `kawasan_id` yang
  diteka tidak membocorkan kewujudan borang melalui 404 vs 403.
- 11 gerbang `if (! isSuperAdmin && ! isAdmin) abort(403)` yang berulang
  digantikan dengan satu kaedah persendirian `assertPeranan()`.
- `binaSemula()`, `pulih()` dan `sejarah()` mengekalkan semakan ketat
  admin-sahaja. `sejarah()` disekat kerana ia hanyalah UI untuk `pulih()`.

### 4. Pendaratan dan navigasi

- `DashboardController::index()` — `redirect()->route('pilihanraya.paca')`
  secara eksplisit bagi peranan ini. Ini juga menutup pepijat terpendam:
  kaedah itu kini jatuh melalui ke papan pemuka Super Admin peringkat
  kebangsaan bagi mana-mana peranan yang tidak dikenali.
- `AuthenticatedSessionController` tidak disentuh — ia mengalih ke
  `dashboard`, yang kemudian mengalih ke PACA. Satu tempat sahaja.
- `AuthenticatedLayout.jsx` — cabang awal khusus: navigasi hanya
  `[{ Pilihanraya → submenu: [PACA] }]`. Pautan Dashboard yang kini tanpa
  syarat perlu digerbang.

### 5. UI halaman PACA

Pengawal menghantar dua prop baharu:

- `kerusiTerkunci` — kerusi tunggal yang dipilih automatik, atau `null`.
- `bolehUrusStruktur` — `false` bagi peranan ini.

Perubahan `Paca.jsx`:

- Apabila `kerusiTerkunci` ditetapkan, langkau `SeatPicker` dan papar nama
  kerusi sebagai tajuk tetap.
- Apabila `!bolehUrusStruktur`, sembunyikan butang **Sejarah** dan **Bina
  Semula Roster**.
- Simpan / tambah saluran / tambah slot / buang slot / PDF / WhatsApp / Salin
  Pautan Awam semuanya kekal.

### 6. Ujian

`tests/Feature/KetuaPacaDunTest.php`:

1. Halaman PACA DUN sendiri dimuatkan dan mengembalikan tepat satu kerusi.
2. `data` bagi DUN lain → 403.
3. `simpan` bagi DUN lain → 403.
4. `bina-semula` pada DUN sendiri → 403.
5. `pulih` pada DUN sendiri → 403.
6. `simpan` pada DUN sendiri → 200.
7. `/dashboard` mengalih ke `pilihanraya.paca`.
8. Peranan `user` biasa masih 403 pada PACA.
9. Admin dan Super Admin tidak mengalami regresi.

## Di luar skop

- **API mudah alih** (`/api/mobile/*`) — bergerbang pada peranan `user`,
  jadi `ketua_paca_dun` lengai di sana. Tiada perubahan.
- **Halaman awam token PACA** (`PacaPublicController`) — sudah tidak
  memerlukan pengesahan. Tiada perubahan.
- **`VoterDataMasker::canSeeSensitive()`** — mengembalikan `false` bagi
  peranan yang tidak dikenali, jadi data sensitif kekal bertopeng secara
  lalai. Tiada perubahan diperlukan.
- **`SuspiciousActivityDetector`** — hanya memerhati `user` dan `super_user`.
  Ketua PACA DUN tidak menyunting data pengundi, jadi tiada perubahan.

## Risiko

| Risiko | Mitigasi |
|---|---|
| Memindahkan laluan PACA keluar daripada kumpulan `admin` boleh menjatuhkan middleware secara senyap | Nama dan URL laluan kekal; ujian mengesahkan `user` biasa masih 403 dan Admin masih lulus |
| `kawasan_id` diteka membocorkan kewujudan borang | `assertBolehAkses()` dipanggil sebelum sebarang carian `Borang14Form` (corak sedia ada dikekalkan) |
| Peranan tidak dikenali jatuh ke papan pemuka Super Admin | Ditutup secara eksplisit dalam `DashboardController::index()` |
