# Sticky Filters — ingat pilihan dropdown sepanjang sesi log masuk

Tarikh: 2026-07-22
Status: design, diluluskan

## Masalah

Pengguna memilih Negeri → Bandar → KADUN pada satu skrin, pergi ke menu lain, kembali —
dan setiap dropdown sudah kosong semula. Kerja memilih kawasan diulang berpuluh kali
sehari. Ia lebih teruk daripada yang disangka:

- **~13 skrin** menyimpan penapis dalam `useState` sahaja dan kehilangannya pada SEBARANG
  navigasi. Termasuk Dashboard, War Room, Analisa, Borang 14, Scoreboard dalaman.
- **~24 skrin** menyimpan penapis dalam URL dan menerimanya semula daripada pelayan.
  Ia bertahan apabila halaman dimuat semula, tetapi hilang apabila pengguna keluar dan
  kembali.
- **Dashboard mempunyai pepijat sebenar.** `DashboardController.php:38-43` MEMBACA
  keenam-enam parameter penapis, tetapi `Inertia::render()` (:524) tidak pernah
  memulangkannya — tiada kunci `'filters'` di mana-mana dalam fail itu (disahkan).
  Jadi `Dashboard/Index.jsx:69-76` memulakan setiap dropdown sebagai `''` walaupun URL
  masih membawa `?negeri_id=5&bandar_id=40`. Refresh biasa pun sudah kehilangannya.
- **Tiada apa-apa untuk dibina di atasnya.** `grep -rn "localStorage\|sessionStorage"
  resources/js` = 0 padanan merentasi 126 fail. Tiada jadual/lajur pilihan pengguna.

## Keputusan reka bentuk

| Soalan | Keputusan | Sebab |
|---|---|---|
| Di mana disimpan | Sesi Laravel (pemacu `database`, `config/session.php:21`) | `AuthenticatedSessionController.php:62-71` sudah memanggil `session()->invalidate()` semasa log keluar. Syarat "reset selepas log keluar" datang PERCUMA — ia sifat tempat simpanan, bukan kod tambahan yang boleh terlupa dijalankan. |
| Sejauh mana ia mengikut | Merentasi tab dan mula semula pelayar, selagi sesi hidup | Sepadan dengan "selama mana user itu login" secara literal. |
| Medan yang diingat | Dropdown + julat tarikh. BUKAN carian teks bebas | Tarikh sebahagian daripada keputusan "apa yang saya sedang lihat" yang sama seperti kerusi; memulihkan satu tanpa satu lagi memaparkan angka berskop tarikh yang tidak kelihatan. Carian teks pula bersifat sekali guna — kembali ke senarai yang masih ditapis "Ali" dibaca sebagai data hilang, bukan tetapan tersimpan. |
| Skrin yang dilindungi | Semua skrin log masuk yang ada data sebenar (~20) | Dikecualikan: Scoreboard awam (`Pages/Public/Scoreboard.jsx` — tiada log masuk, jadi tiada sesi) dan Call Center (data olok-olok berkod keras — mengingat penapis di situ menambah risiko tanpa faedah). |
| Mekanisme | Middleware menggabungkan penapis yang diingat ke dalam permintaan | Lihat "Invarian" di bawah. |

## Invarian yang memandu seluruh reka bentuk

**Dropdown yang dipulihkan tetapi keputusan yang tidak ditapis ialah UI yang menipu —
lebih buruk daripada tiada ingatan langsung.**

Pengguna melihat "KUALA PILAH" dalam dropdown dan mempercayai bahawa 13,706 di bawahnya
ialah angka Kuala Pilah. Kalau pemulihan dropdown dan penapisan pertanyaan ialah DUA
laluan berasingan, ia akan hanyut, dan kegagalannya SENYAP.

Sebab itu middleware menggabungkan nilai yang diingat ke dalam `$request` itu sendiri.
Pengawal membaca `$request->input('negeri_id')` untuk MEMBINA pertanyaan dan untuk
MEMULANGKAN penapis ke dropdown. Satu sumber, satu laluan. Ia tidak boleh hanyut kerana
tiada laluan kedua untuk dihanyutkan.

## Seni bina

### 1. Senarai putih — sempadan keselamatan

Satu array config memetakan skop kepada kunci penapis yang DIBENARKAN:

```php
'dashboard' => ['negeri_id','bandar_id','kadun_id','mpkk_id','tarikh_dari','tarikh_hingga'],
'war_room'  => ['negeri_id','parlimen_id','kadun_id','tarikh_dari','tarikh_hingga',
                'umur_dari','umur_hingga','status_pengundi'],
```

Tiada apa-apa di luar senarai itu pernah disimpan atau digabungkan. Middleware TIDAK
BOLEH menyuntik parameter yang pengawal tidak jangka.

### 2. Skop dinamakan, bukan diterbitkan daripada nama laluan

Beberapa laluan boleh berkongsi satu skop. Ini perlu kerana:
- Tab War Room berkongsi satu set penapis.
- Tab Keyin/Papar Borang 14 berkongsi kawasan + jenis PR + tahun.
- **Endpoint XHR yang dipanggil sesuatu halaman mesti memetakan ke skop YANG SAMA seperti
  halaman itu.** Inilah yang menjadikan pengambilan data biasa halaman itu merangkap
  penyimpanan — tiada panggilan "simpan" berasingan diperlukan.

### 3. Middleware `RememberFilters` (selepas `auth`, GET sahaja)

```
Permintaan membawa MANA-MANA kunci senarai putih
    -> pengguna bertindak dengan sengaja; simpan keseluruhan set ke sesi
Permintaan tidak membawa satu pun
    -> gabungkan set yang diingat ke dalam $request
```

Satu peraturan itu turut menyelesaikan "bagaimana kita tahu pengguna MEMBERSIHKAN penapis
berbanding sekadar masuk semula?":

- Membersihkan menghantar kunci HADIR-TETAPI-KOSONG -> kita simpan kosong -> lawatan
  berikutnya memulihkan TIADA APA-APA. Betul.
- Navigasi biasa langsung tidak menghantar kunci -> pulihkan.

Kesannya butang **Set Semula** sedia ada berfungsi tanpa diubah.

Tidak pernah menyentuh POST/PUT/DELETE.

### 4. Perkongsian ke frontend

`HandleInertiaRequests::share()` (`app/Http/Middleware/HandleInertiaRequests.php:47`)
menambah `rememberedFilters` bagi skop semasa.

- **~24 halaman dipacu URL: TIADA perubahan.** Ia sudah menerima prop `filters` daripada
  pengawalnya dan kini menerima nilai yang digabungkan secara automatik.
- **Halaman dipacu axios**: semai `useState` awal daripada `rememberedFilters` dan bukan
  `EMPTY_FILTERS`. `useEffect` sedia ada yang berkunci pada penapis akan mengambil data
  semula dengan sendirinya, jadi keputusan mengikut tanpa kerja tambahan.
- **`DashboardController`**: betulkan pepijat gema — pulangkan `'filters'`.

## Bahaya: penapis yang diingat mesti MENYEMPITKAN, tidak pernah MELUASKAN

`DashboardController.php:55-63` menskop pertanyaan mengikut `negeri_id`/`bandar_id`/
`kadun_id` pengguna sendiri:

```php
if (! $user->isSuperAdmin()) {
    if ($user->negeri_id) { $pengundiQuery->where('negeri', ...); }
    if ($user->bandar_id) { $pengundiQuery->where('bandar', ...); }
```

Itu sempadan KEBENARAN, bukan pilihan UI. Ia dikenakan tanpa mengira parameter penapis,
jadi penapis yang diingat menyempitkan DI DALAM sempadan itu dan tidak boleh
meluaskannya. Sifat itu MESTI dikunci dengan ujian, bukan diandaikan — termasuk kes
sesi super_admin yang meninggalkan penapis pada pelayar yang sama.

## Ujian

1. Ingat dan pulih: GET dengan parameter, kemudian GET tanpa parameter -> pengawal nampak parameter.
2. Bersih dan reset: GET dengan parameter kosong -> GET kosong berikutnya tidak ditapis.
3. Log keluar memadamnya: log masuk, set penapis, log keluar, log masuk -> lalai.
4. Dua pengguna tidak bercampur (sesi berasingan).
5. Parameter di luar senarai putih tidak pernah digabungkan.
6. **Kebenaran tidak diluaskan**: admin berskop Bandar X tidak boleh mencapai data
   Bandar Y melalui penapis yang diingat.
7. Dashboard memulangkan `'filters'` (ujian regresi bagi pepijat gema).

## Bukan dalam skop

- Carian teks bebas.
- Scoreboard awam dan Call Center.
- Sebarang jadual pilihan pengguna kekal — ini sesi sahaja, mengikut permintaan.
