# Peranan Baharu — Pengarah DUN

Tarikh: 2026-08-01

## Keperluan

Peranan baharu `pengarah_dun`, dipapar sebagai **Pengarah DUN**.

> "Dia hanya boleh melihat & edit untuk menu: Pilihanraya di bawah Parlimen
> yang user didaftarkan sahaja."

Jadi: **menu Pilihanraya sahaja**, diskop kepada **Parlimen** pengguna itu
(`users.bandar_id`) dan setiap DUN di bawahnya. Walaupun namanya "DUN", skopnya
ialah Parlimen — itulah yang dinyatakan, dan itulah yang dilaksanakan.

## Bahaya utama

`DashboardController::index()` bercabang bagi `ketua_paca_dun`, kemudian
`admin|super_user|user`, dan **jatuh ke papan pemuka Super Admin KEBANGSAAN**
bagi apa-apa yang tidak sepadan. Peranan baharu tanpa cabangnya sendiri akan
melihat data seluruh negara. CLAUDE.md memberi amaran tentang ini secara
khusus. Cabang eksplisit WAJIB, dan diuji.

## Keputusan

| Perkara | Keputusan |
|---|---|
| Nilai peranan | `pengarah_dun` (lajur `role` ialah string biasa — tiada migrasi) |
| Skop kerusi | Sama seperti `admin`: `bandar_id` sendiri + setiap DUN di bawahnya |
| Menu | Pilihanraya SAHAJA, disenaraikan secara eksplisit |
| Laluan | Kumpulan `pilihanraya` dibuka kepadanya; TIADA laluan lain |
| Papan pemuka | Cabang eksplisit, berskop Parlimen — jangan sekali-kali jatuh ke kebangsaan |
| Status | Guna aliran `pending`/`approved` sedia ada, tiada status baharu |

## Pelaksanaan

1. `User::isPengarahDun()`.
2. `SeatScope` — layan serupa `admin` (Parlimen sendiri + DUN di bawahnya).
   Ini SATU-SATUNYA tempat peraturan kerusi ditulis, jadi papan markah, Borang 14
   dan kebenaran kerusi lain mengikutinya secara automatik.
3. Middleware `EnsureAdmin` menjaga kumpulan `pilihanraya` utama. Ia hanya
   membenarkan super_admin/admin. Tambah `pengarah_dun` DI SINI SAHAJA — jangan
   longgarkan mana-mana gerbang lain, kerana middleware yang sama menjaga
   Keanggotaan, Data Induk, Laporan dan Tetapan.
   **Semak dahulu**: jika `admin` digunakan oleh kumpulan selain `pilihanraya`,
   melonggarkannya akan memberi peranan ini akses kepada menu lain. Jika ya,
   gunakan middleware berasingan bagi kumpulan `pilihanraya` (ikut duluan
   `EnsurePacaAccess`) dan JANGAN sentuh `EnsureAdmin`.
4. `DashboardController::index()` — cabang eksplisit, berskop kepada
   `bandar_id`. Diuji: seorang Pengarah DUN TIDAK boleh melihat angka
   kebangsaan atau angka Parlimen lain.
5. `UsersController` — tambah kepada kedua-dua senarai `in:` (cipta + kemas kini).
6. UI Users — label "Pengarah DUN", warna lencana, pilihan dropdown. Medan
   kawasan: Parlimen WAJIB (tanpa `bandar_id`, SeatScope menafikan segalanya —
   itu pengajaran IDOR Julai 2026, jangan dilonggarkan).
7. Navigasi — blok Pilihanraya ketiga, itemnya disenaraikan secara EKSPLISIT
   mengikut corak `ketua_paca_dun`, supaya menambah item Pilihanraya baharu pada
   masa depan tidak membocorkannya ke sini.

## Ujian

| Ujian | Yang dipaku |
|---|---|
| Papan pemuka | Pengarah DUN mendapat papan pemuka berskop Parlimennya, BUKAN kebangsaan |
| Menu | Hanya Pilihanraya; Keanggotaan/Data Induk/Laporan/Tetapan ditolak |
| Kerusi | Boleh sentuh Parlimen sendiri + DUN di bawahnya; ditolak bagi yang lain |
| bandar_id NULL | Dinafikan di mana-mana |
| Belum lulus | Dinafikan |
| Peranan lain | Tidak berubah langsung |
