# Kontrak API Mudah Alih — `/api/mobile/*`

**Tarikh:** 2026-07-17
**Status:** Rujukan — asas kepada Plan 2 (klien Flutter)
**Sumber kebenaran:** kod pada `feature/mobile-app-user`, disahkan dengan
`php artisan route:list --path=api/mobile` dan ujian feature yang lulus
(`tests/Feature/MobileVoterSearchTest.php`, `MobileCulaanReadTest.php`,
`MobileCulaanStoreTest.php`). **Bila dokumen ini bercanggah dengan
`2026-07-17-aplikasi-mudah-alih-user-design.md` atau brief Task 7, kod menang** —
API ini bertukar banyak semasa enam pusingan pembetulan keselamatan/ketepatan
selepas plan itu ditulis. Setiap percanggahan yang dijumpai disenaraikan di
bahagian akhir.

Tiada `routes/api.php` dalam projek ini. Semua endpoint di bawah didaftar dalam
`routes/web.php`, di dalam `Route::prefix('api/mobile')` (~baris 519), dan oleh
itu turut melalui middleware group `web` (sesi, cookie) — CSRF sahaja yang
dikecualikan (`withoutMiddleware([VerifyCsrfToken::class])`).

## Senarai penuh — 13 endpoint

`route:list` mengesahkan **13** endpoint, bukan 12 seperti yang dinyatakan dalam
brief Task 7 (lihat "Percanggahan" di hujung dokumen untuk butiran).

| # | Method | Path | Auth | Throttle | Controller |
|---|---|---|---|---|---|
| 1 | POST | `/api/mobile/login` | Tiada | RateLimiter dalam controller (bukan middleware) — 5 percubaan / 60 saat, dikunci pada `telephone+ip` | `MobileAuthController@login` |
| 2 | POST | `/api/mobile/register` | Tiada | Tiada | `MobileAuthController@register` |
| 3 | POST | `/api/mobile/forgot-password` | Tiada | `throttle:3,5` (3 / 5 minit) | `MobileAuthController@forgotPassword` |
| 4 | GET | `/api/mobile/negeri-list` | Tiada | Tiada | closure inline (`routes/web.php`) |
| 5 | GET | `/api/mobile/bandar-by-negeri` | Tiada | Tiada | closure inline |
| 6 | GET | `/api/mobile/kadun-by-bandar` | Tiada | Tiada | closure inline |
| 7 | POST | `/api/mobile/web-auth-token` | `auth:sanctum` | Tiada | `MobileAuthController@webAuthToken` |
| 8 | POST | `/api/mobile/logout` | `auth:sanctum` | Tiada | `MobileAuthController@logout` |
| 9 | GET | `/api/mobile/voters/search` | `auth:sanctum` | `throttle:30,1` (30 / minit) | `MobileVoterController@search` |
| 10 | GET | `/api/mobile/voters/{ic}` | `auth:sanctum` | `throttle:30,1` | `MobileVoterController@show` |
| 11 | GET | `/api/mobile/culaan/options` | `auth:sanctum` | `throttle:30,1` | `MobileCulaanController@options` |
| 12 | GET | `/api/mobile/culaan/mine` | `auth:sanctum` | `throttle:30,1` | `MobileCulaanController@mine` |
| 13 | POST | `/api/mobile/culaan` | `auth:sanctum` | `throttle:60,1` (60 / minit) | `MobileCulaanController@store` |

Nota susunan laluan (dari komen dalam `routes/web.php`): `culaan/options` dan
`culaan/mine` sengaja didaftar **sebelum** mana-mana laluan `culaan/{id}` wildcard
masa depan, supaya wildcard itu tidak "menelan" dua literal path ini — kesilapan
yang pernah berlaku pada `voters/{ic}`. Jika Plan 2 atau kerja hadapan menambah
`GET /culaan/{id}`, ia mesti didaftar **selepas** kedua-dua ini.

## Konvensyen respons

- Endpoint yang ditulis khusus untuk mudah alih (9–13 di atas, serta `login`,
  `forgot-password`, dan bahagian *checked* `register`) memulangkan bungkusan
  `{"success": bool, ...}`.
- **Bukan semua endpoint ikut konvensyen ini** — lihat "Percanggahan" di bawah.
  Tiga tempat khusus yang mengejutkan:
  - `POST /register` dan bahagian awal `POST /login` (semakan `required` atas
    `telephone`/`password` sahaja) guna `$request->validate([...])` binaan
    Laravel terus. Apabila medan **hilang sepenuhnya**, Laravel memulangkan
    bentuk lalainya sendiri — **bukan** `{success:false, errors:...}**, dan
    mesejnya **Bahasa Inggeris**, bukan Bahasa Melayu. Contoh sebenar (disahkan
    dengan menjalankan `POST /login` dengan body kosong):
    ```json
    {
      "message": "The telephone field is required. (and 1 more error)",
      "errors": {
        "telephone": ["The telephone field is required."],
        "password": ["The password field is required."]
      }
    }
    ```
    Status masih 422. Klien mesti sedia menyemak **kedua-dua** bentuk
    (`errors.<field>` wujud dalam kedua-duanya, jadi baca dari situ dan abaikan
    `message`) — tetapi jangan papar `message` terus kepada pengguna kerana ia
    boleh jadi Bahasa Inggeris.
  - `POST /web-auth-token` memulangkan `{"token": "..."}` sahaja — tiada kunci
    `success`.
  - `GET /negeri-list`, `GET /bandar-by-negeri`, `GET /kadun-by-bandar`
    memulangkan **tatasusunan JSON telanjang**, bukan dibungkus dalam objek —
    cth. `[{"id":1,"nama":"JOHOR"}]`, bukan `{"success":true,"negeri":[...]}`.
- **401 (tiada token / token tidak sah)** dan **429 (had kadar middleware)**
  tidak pernah sampai ke controller — kedua-duanya dijana oleh middleware
  Laravel sebelum controller dipanggil, jadi kedua-duanya **juga** tidak ikut
  bungkusan `{success}`. Disahkan dengan menjalankan permintaan sebenar:
  ```json
  // 401 — auth:sanctum, token hilang/tidak sah/dibatalkan
  { "message": "Unauthenticated." }
  ```
  ```json
  // 429 — throttle:X,Y dilebihi (bukan RateLimiter manual dalam login())
  { "message": "Too Many Attempts." }
  ```
  Header pada respons 429 middleware termasuk `X-RateLimit-Limit`,
  `X-RateLimit-Remaining` (0), `Retry-After` (saat), `X-RateLimit-Reset`
  (epoch). Guna `Retry-After` untuk backoff, bukan nilai tetap.
- 429 daripada `POST /login` (RateLimiter manual dalam controller, bukan
  middleware throttle) **adalah** dibungkus `{success:false, errors:...}` —
  lihat bahagian `/login` di bawah. Ini bermakna bentuk respons 429 **berbeza
  bergantung endpoint** — klien perlu semak status code dahulu, baru cuba baca
  `errors` jika ada.

---

## 1. `POST /api/mobile/login`

Tiada auth. Tiada throttle middleware — had kadar dikendali secara manual
dalam controller (`RateLimiter`, kunci `strtolower(telephone)|ip`, 5 percubaan
/ 60 saat, `RateLimiter::clear()` selepas kejayaan).

**Request body**

| Medan | Jenis | Wajib |
|---|---|---|
| `telephone` | string | Ya |
| `password` | string | Ya |

**Respons berjaya — 200**
```json
{
  "success": true,
  "token": "1|abcdef...",
  "user": {
    "id": 42,
    "name": "Ahmad bin Ali",
    "telephone": "0123456789",
    "role": "user",
    "must_change_password": false
  }
}
```
`token` ialah token plaintext Sanctum (`createToken('mobile')->plainTextToken`).
**Log masuk baharu memansuhkan SEMUA token sedia ada pengguna itu**
(`$user->tokens()->delete()` dipanggil sebelum token baharu dicipta) — jika
pengguna log masuk pada peranti/pemasangan lain, token peranti lama itu
serta-merta tidak sah dan permintaan seterusnya dari situ akan dapat 401. Ini
relevan untuk pemasangan semula app atau berkongsi akaun antara dua telefon.

**Ralat**

| Status | Sebab | Bentuk & mesej |
|---|---|---|
| 422 | `telephone`/`password` hilang sepenuhnya | Bentuk lalai Laravel, Bahasa Inggeris — lihat "Konvensyen respons" |
| 429 | > 5 percubaan gagal dalam 60 saat | `{"success":false,"errors":{"telephone":["Terlalu banyak percubaan. Sila cuba lagi dalam {n} saat."]}}` |
| 422 | Telefon/kata laluan salah | `{"success":false,"errors":{"telephone":["Nombor telefon atau kata laluan tidak sah."]}}` |
| 422 | Akaun `pending` | `{"success":false,"errors":{"telephone":["Akaun anda masih menunggu kelulusan daripada pentadbir."]}}` |
| 422 | Akaun `rejected` (bukan `pending`, bukan `approved`) | `{"success":false,"errors":{"telephone":["Akaun anda telah ditolak. Sila hubungi pentadbir."]}}` |

`super_admin` melangkau semakan status kelulusan sepenuhnya.

## 2. `POST /api/mobile/register`

Tiada auth. Tiada throttle. Guna `$request->validate([...])` binaan Laravel
terus (bukan bungkusan app), jadi **setiap** ralat 422 daripada endpoint ini
dalam bentuk lalai Laravel (Bahasa Inggeris) — lihat "Konvensyen respons".

**Request body**

| Medan | Jenis | Wajib | Nota |
|---|---|---|---|
| `name` | string, max 255 | Ya | |
| `telephone` | string, max 255 | Ya | `unique:users,telephone` |
| `email` | string, email, max 255 | Tidak | `unique:users,email` bila diisi |
| `password` | string | Ya | `confirmed` (perlukan `password_confirmation`), ikut `Rules\Password::defaults()` |
| `negeri_id` | integer | Ya | `exists:negeri,id` |
| `bandar_id` | integer | Ya | `exists:bandar,id` |
| `kadun_id` | integer | Ya | `exists:kadun,id` |

Akaun baharu sentiasa dicipta dengan `role => 'user'` dan `status => 'pending'`
— tiada cara untuk daftar sebagai role lain melalui endpoint ini.

**Respons berjaya — 200**
```json
{ "success": true }
```
Tiada `user` atau `token` dikembalikan — pengguna mesti log masuk berasingan
selepas diluluskan oleh pentadbir.

**Ralat**

| Status | Sebab | Bentuk |
|---|---|---|
| 422 | Mana-mana peraturan gagal | Bentuk lalai Laravel — `{"message": "...", "errors": {...}}`, Bahasa Inggeris |

## 3. `POST /api/mobile/forgot-password`

Tiada auth. `throttle:3,5` (3 permintaan / 5 minit, middleware — 429 dalam
bentuk `{"message":"Too Many Attempts."}`, bukan bungkusan app).

**Request body**

| Medan | Jenis | Wajib |
|---|---|---|
| `telephone` | string | Ya |

**Respons berjaya — 200**
```json
{ "success": true, "message": "Kata laluan baharu telah dihantar ke WhatsApp anda." }
```
Kata laluan baharu (rawak, 8 aksara) dihantar melalui WhatsApp (templat
`NotificationTemplate::CATEGORY_PASSWORD_RESET`, dengan fallback mesej biasa
jika templat gagal). `must_change_password` ditetapkan `true` pada akaun.

**Ralat**

| Status | Sebab | Bentuk |
|---|---|---|
| 422 | `telephone` hilang | Bentuk lalai Laravel (`$request->validate(['telephone'=>'required|string'])`) |
| 422 | Nombor tidak wujud | `{"success":false,"errors":{"telephone":["Nombor telefon tidak dijumpai dalam sistem."]}}` |
| **200** | Penghantaran WhatsApp gagal | **Bukan ralat HTTP** — masih status 200 tetapi `{"success":false,"message":"Penghantaran kata laluan melalui WhatsApp gagal. Sila hubungi pentadbir sistem."}`. Klien mesti semak `success` di badan, bukan hanya status HTTP. |
| 429 | > 3 permintaan / 5 minit | `{"message":"Too Many Attempts."}` (middleware, bukan bungkusan app) |

## 4–6. Dropdown geografi (tiada auth, tiada throttle)

Tiga closure ringkas dalam `routes/web.php`, bukan controller method. Semuanya
memulangkan **tatasusunan JSON telanjang**, disusun mengikut `nama`, dengan
lajur `id` dan `nama` sahaja.

| Endpoint | Query param | Respons |
|---|---|---|
| `GET /api/mobile/negeri-list` | — | `[{"id":1,"nama":"JOHOR"}, ...]` |
| `GET /api/mobile/bandar-by-negeri` | `negeri_id` | `[{"id":1,"nama":"SEGAMAT"}, ...]` |
| `GET /api/mobile/kadun-by-bandar` | `bandar_id` | `[{"id":1,"nama":"BULOH KASAP"}, ...]` |

Tiada pengesahan pada `negeri_id`/`bandar_id` — jika parameter hilang atau
tidak sepadan dengan mana-mana baris, hasilnya tatasusunan kosong `[]` dengan
status 200, bukan 422/404. Tidak diperlukan `auth:sanctum`; boleh dipanggil
sebelum log masuk (digunakan pada skrin daftar).

## 7. `POST /api/mobile/web-auth-token`

`auth:sanctum`. Tiada throttle.

Menjana token sekali-guna 64-aksara untuk jambatan auto-log-masuk WebView
(`/mobile-web-auth`), disimpan dalam cache selama 60 saat
(`Cache::put("mobile_web_auth:{token}", $userId, 60)`). Ini untuk skrin
WebView (Dashboard, Laporan, dll.), **bukan** untuk aliran carian/culaan
native — Plan 2 kemungkinan besar tidak perlu memanggil ini kecuali ia turut
membina pembungkus WebView.

**Respons berjaya — 200**
```json
{ "token": "AbCdEf...64 aksara..." }
```
Tiada kunci `success`. Tiada `errors` yang didokumenkan — hanya boleh gagal
melalui 401 (tiada/tidak sah token Sanctum).

## 8. `POST /api/mobile/logout`

`auth:sanctum`. Tiada throttle.

Memadam token akses semasa sahaja (`$request->user()->currentAccessToken()->delete()`)
— token lain milik pengguna yang sama (jika ada) tidak terjejas.

**Respons berjaya — 200**
```json
{ "success": true }
```

**Ralat**

| Status | Sebab |
|---|---|
| 401 | Tiada/token tidak sah |

## 9. `GET /api/mobile/voters/search`

`auth:sanctum`, `throttle:30,1` (30 permintaan / minit). Ini **direka untuk
aliran imbas-kemudian-cari, bukan type-ahead tanpa debounce** — lihat bahagian
"Had kadar" di bawah.

**Query param**

| Medan | Jenis | Wajib |
|---|---|---|
| `q` | string, min 3 aksara | Ya |

**Tingkah laku carian bergantung peranan penonton** (lihat
`VoterDataMasker::canUnmask()`):

- `nama` — sentiasa dipadan sebagai **substring** (`LIKE '%q%'`), untuk semua
  peranan.
- `no_ic` — untuk penonton yang **tidak** boleh nyahtopeng (`role = user`),
  hanya dipadan bila `q` ialah **12 digit tepat** (`preg_match('/^\d{12}$/')`).
  Carian separa IC memulangkan kosong. Untuk penonton yang boleh nyahtopeng
  (`admin`, `super_user`, `super_admin`), padanan substring biasa berfungsi.
  Ini reka bentuk anti-oracle yang disengajakan — carian separa IC pernah jadi
  cara bocorkan IC bertopeng satu digit pada satu masa (~100 permintaan).
- Skop baris (Kadun/Bandar/`submitted_by`) dikuatkuasakan oleh
  `VoterScopeService::apply()` sebelum carian dijalankan — lihat jadual peranan
  di bawah.
- Aksara metaguna LIKE (`%`, `_`, `\`) dalam `q` dilepaskan secara literal
  (`escapeLike()`) supaya `q=%%%` tidak menjadi wildcard sejagat.
- Had **30 rekod** setiap respons (`MAX_RESULTS = 30`), tiada pagination.

**Skop baris ikut peranan** (`VoterScopeService::apply`):

| Peranan | Skop |
|---|---|
| `user`, `super_user` | `kadun = kadun sendiri` ATAU `submitted_by = diri sendiri` |
| `admin` | `bandar = bandar sendiri` ATAU `submitted_by = diri sendiri` |
| `super_admin` | Tiada sekatan |

**Respons berjaya — 200**
```json
{
  "success": true,
  "voters": [
    {
      "id": 101,
      "nama": "Ahmad bin Ali",
      "no_ic": "****",
      "umur": "****",
      "no_tel": "****",
      "bangsa": "****",
      "alamat": "****",
      "poskod": "****",
      "negeri": "****",
      "bandar": "****",
      "parlimen": "SEGAMAT",
      "kadun": "BULOH KASAP",
      "...medan data_pengundi lain (tidak ditopeng)": "...",
      "submitted_by": { "id": 7, "name": "Siti binti Osman" }
    }
  ]
}
```
`submitted_by` sentiasa objek `{id, name}` sahaja (atau `null`) — **tidak
pernah** akaun penuh penyerah (bukan `email`/`telephone`/`role`/
`last_login_*`). Medan sensitif ditopeng `'****'` **hanya** jika rekod
"terkunci" (`VoterDataMasker::isLocked()` — penyerah asal berperanan `user`)
**dan** penonton tidak boleh nyahtopeng. Rekod yang diserah oleh
`admin`/`super_user`/`super_admin` tidak pernah terkunci, jadi sentiasa keluar
sebagai nilai sebenar tanpa mengira siapa yang melihat.

**Ralat**

| Status | Sebab | Mesej |
|---|---|---|
| 401 | Tiada/token tidak sah | `{"message":"Unauthenticated."}` |
| 422 | `q` hilang | `{"success":false,"errors":{"q":["Sila masukkan kata carian."]}}` |
| 422 | `q` < 3 aksara | `{"success":false,"errors":{"q":["Sila masukkan sekurang-kurangnya 3 aksara."]}}` |
| 422 | `q` bukan string (cth. `q[]=x`) | `{"success":false,"errors":{"q":["Kata carian tidak sah."]}}` |
| 429 | > 30 permintaan / minit | `{"message":"Too Many Attempts."}` |

## 10. `GET /api/mobile/voters/{ic}`

`auth:sanctum`, `throttle:30,1`. `{ic}` ialah segmen path (bukan query),
dipadan tepat pada `no_ic` (`where('no_ic', $ic)`, bukan `LIKE`) — tiada
sekatan format pada `{ic}` di peringkat laluan, tetapi padanan tepat
bermakna hanya 12-digit sepadan bermakna sesuatu.

Skop baris sama seperti carian (`VoterScopeService::apply`). Rekod di luar
skop pengguna memulangkan 404, bukan 403 — tidak mendedahkan sama ada rekod
itu wujud di suatu tempat lain dalam sistem.

**Respons berjaya — 200**
```json
{
  "success": true,
  "voter": { "id": 101, "nama": "Ahmad bin Ali", "no_ic": "****", "...": "...", "submitted_by": {"id": 7, "name": "Siti"} }
}
```
Sama seperti `search`, ditopeng ikut `VoterDataMasker` dan `submitted_by`
diringkaskan kepada `{id, name}`.

**Ralat**

| Status | Sebab | Mesej |
|---|---|---|
| 401 | Tiada/token tidak sah | `{"message":"Unauthenticated."}` |
| 404 | IC tidak dijumpai ATAU di luar skop pengguna | `{"success":false,"errors":{"no_ic":["Pengundi tidak dijumpai."]}}` |
| 429 | > 30 permintaan / minit | `{"message":"Too Many Attempts."}` |

## 11. `GET /api/mobile/culaan/options`

`auth:sanctum`, `throttle:30,1`. Punca kebenaran tunggal untuk taksonomi
borang Culaan (dropdown/checkbox). Beberapa senarai dikodkeraskan (ditranskrip
literal daripada `Create.jsx`), satu senarai (`tujuan_sumbangan`) hidup dari DB.

**Respons berjaya — 200** (bentuk penuh; `pekerjaan`, `jenis_sumbangan`,
`bantuan_lain`, `pemilik_rumah` dipendekkan di sini — lihat kod untuk senarai
lengkap):
```json
{
  "success": true,
  "options": {
    "pekerjaan": ["Kerajaan", "Swasta", "Bekerja Sendiri", "Tidak Bekerja"],
    "jenis_pekerjaan": {
      "Kerajaan": [
        {
          "category": "Jenis Perkhidmatan",
          "items": [
            "Perkhidmatan Awam Persekutuan (Kementerian / Jabatan)",
            "Perkhidmatan Awam Negeri",
            "Pihak Berkuasa Tempatan (PBT)"
          ]
        },
        { "category": "Agensi & Badan", "items": ["..."] },
        { "category": "Lain-lain", "items": ["Lain-lain"] }
      ],
      "Swasta": [ "... (struktur sama, kategori berbeza)" ],
      "Bekerja Sendiri": [ "..." ],
      "Tidak Bekerja": [ "..." ]
    },
    "jenis_sumbangan": ["Barangan Keperluan Dapur", "...", "Lain-lain"],
    "tujuan_sumbangan": ["Pendidikan", "Kesihatan", "..."],
    "bantuan_lain": ["Jabatan Kebajikan Masyarakat (JKM)", "...", "Tiada", "Lain-lain"],
    "pemilik_rumah": ["Sendiri", "Sewa", "Keluarga", "Lain-lain"]
  }
}
```

**`jenis_pekerjaan` BUKAN senarai rata.** Ia empat senarai berkumpulan
kategori, **dikunci mengikut nilai `pekerjaan`** yang dipilih terlebih dahulu.
Borang mesti bina UI berperingkat: pengguna pilih `pekerjaan` dahulu, baru
kumpulan `jenis_pekerjaan` yang berkaitan (dengan tajuk kategori
`category` masing-masing) dipaparkan. Empat kunci peringkat atas
`jenis_pekerjaan` (`Kerajaan`, `Swasta`, `Bekerja Sendiri`, `Tidak Bekerja`)
**mesti** sepadan tepat dengan nilai `pekerjaan` — ia disemak oleh
`MobileCulaanReadTest::test_pekerjaan_options_match_the_servers_validation_rule()`.

**`tujuan_sumbangan` boleh jadi tatasusunan kosong** (`TujuanSumbangan::pluck('nama')`)
jika jadual Master Data > Tujuan Sumbangan dikosongkan — bukan ralat, tetapi
klien mesti kendali senarai kosong dengan neamat (jangan tunjuk skrin rosak).
Walaupun kosong, medan `tujuan_sumbangan` pada `POST /culaan` kekal
`required|array|min:1` bila `has_sumbangan=true` — jadi jika Master Data
dikosongkan, seorang field agent yang perlu isi bahagian sumbangan akan
tersekat, dan itu isu operasi Master Data, bukan sesuatu yang klien boleh
selesaikan sendiri.

**Ralat**

| Status | Sebab |
|---|---|
| 401 | Tiada/token tidak sah |
| 429 | > 30 permintaan / minit |

## 12. `GET /api/mobile/culaan/mine`

`auth:sanctum`, `throttle:30,1`. Rekod yang **diserah oleh pemanggil sendiri**
(`submitted_by = $user->id`) — **BUKAN** ditapis melalui `VoterScopeService`.
Ini sengaja: rekod sendiri mesti sentiasa kelihatan kepada pemanggil walaupun
ia di luar Kadun/Parlimen mereka sekarang.

Disusun `created_at` menurun, dengan `id` menurun sebagai pemecah seri (baris
yang di-import/sync dalam saat yang sama tidak bergantung pada tertib DB yang
tidak menentu). Had **100 rekod**, tiada pagination.

**Respons berjaya — 200**
```json
{
  "success": true,
  "culaan": [
    {
      "id": 55,
      "idempotency_key": "b3f2...",
      "nama": "Rekod Saya",
      "no_ic": "****",
      "alamat": "****",
      "...medan hasil_culaan lain": "...",
      "submitted_by": { "id": 42, "name": "Ahmad bin Ali" }
    }
  ]
}
```
**Penting:** rekod milik seorang `user` sendiri **masih ditopeng** apabila
`user` itu melihatnya balik di sini. Penguncian bergantung pada **peranan
penyerah** (`VoterDataMasker::isLocked()` menyemak `submittedBy->role`),
bukan sama ada penonton = penyerah. Seorang `user` tidak boleh nyahtopeng
rekod sendiri walaupun dialah yang menghantarnya — hanya
`admin`/`super_user`/`super_admin` boleh (`VoterDataMasker::canUnmask()`).
Klien **tidak boleh** anggap "rekod saya" bermakna "IC saya yang sebenar
akan kembali".

**Ralat**

| Status | Sebab |
|---|---|
| 401 | Tiada/token tidak sah |
| 429 | > 30 permintaan / minit |

## 13. `POST /api/mobile/culaan`

`auth:sanctum`, `throttle:60,1` (60 / minit — lebih tinggi daripada had
bacaan 30/minit kerana ini endpoint tulis yang dijangka melepaskan baris
gilir luar talian sekali gus apabila isyarat kembali).

Ditulis di dalam `DB::transaction()` — `HasilCulaan::create()`,
`EditHistory::log()`, dan `VoterSyncService::syncFromHasilCulaan()` (yang
menyebar medan kongsi ke `data_pengundi`) semuanya atau tiada langsung.

**Penting — skop penyebaran:** setiap penyerahan hanya menyebar medan yang
**wujud pada payload yang dihantar ke `HasilCulaan::create()`**, bukan setiap
lajur dalam jadual. `VoterSyncService::extract()` menyalin satu medan kongsi
hanya jika medan itu wujud pada `getAttributes()` rekod `HasilCulaan` yang
baru dicipta. Medan yang tidak dihantar oleh klien (contohnya `mpkk`, `nota`,
`is_deceased`) kekal **tidak disentuh** pada baris `data_pengundi` sedia ada —
ia TIDAK ditulis ganti dengan `NULL`/`false`. Ini disengajakan: laluan cipta
ini memadankan laluan web (`ReportsController.php:460`), yang menghantar
rekod yang baru dicipta terus ke `VoterSyncService::syncFromHasilCulaan()`
tanpa `->fresh()`. Jangan tambah `->fresh()` di sini — itu akan menyebabkan
setiap medan kongsi (kebanyakannya `NULL` pada penyerahan minimum) ditulis
ganti ke atas rekod pengundi sedia ada, termasuk memulihkan semula
(`is_deceased` -> `false`) seorang pengundi yang telah ditanda meninggal
dunia.

**`voter_color` ialah pengecualian — ia dikira oleh SERVER, bukan medan yang
klien hantar.** `keahlian_parti`/`kecenderungan_politik` sendiri memang
menyebar mengikut peraturan di atas (disebar hanya jika dihantar). Tetapi
`voter_color` **tidak pernah** berasal dari `$validated` — ia dikira dalam
controller melalui `VoterColorService::determine($keahlian_parti,
$kecenderungan_politik)` sebelum `HasilCulaan::create()` dipanggil, meniru
`ReportsController.php:455`. Klien **tidak sepatutnya menghantar
`voter_color` langsung** — medan ini bukan sebahagian daripada
`StoreMobileCulaanRequest::rules()` dan sebarang nilai yang dihantar akan
diabaikan.

Pengiraan ini **bersyarat**, bukan tanpa syarat: controller hanya menetapkan
`voter_color` pada payload apabila sekurang-kurangnya SATU daripada
`keahlian_parti`/`kecenderungan_politik` benar-benar hadir dalam penyerahan
ini. Sebabnya: `VoterColorService::determine(null, null)` memulangkan
`'kelabu'` — satu **dakwaan pasti** ("pengundi ini kelihatan belum
menentukan pilihan"), bukan "tiada data". Jika `voter_color` dikira tanpa
syarat, ia akan sentiasa wujud pada `getAttributes()` dan sentiasa disebar
oleh `VoterSyncService::extract()` — walaupun penyerahan itu langsung tidak
menyebut politik — lalu menulis ganti `voter_color` pengundi yang sudah
diketahui (cth. `'hitam'`) dengan `'kelabu'` yang tidak berasas.

Kesan praktikal untuk klien:

- **Menghantar sekurang-kurangnya satu medan politik** -> `voter_color`
  dikira dan disimpan pada kedua-dua `hasil_culaan` dan (melalui penyebaran
  biasa) `data_pengundi`, sama seperti laluan web.
- **Meninggalkan KEDUA-DUA medan politik kosong** -> `voter_color` TIDAK
  ditetapkan pada payload langsung. Baris `hasil_culaan` baharu itu sendiri
  menyimpan `voter_color = NULL` (jujur — penyerahan ini tidak merekodkan
  sebarang isyarat politik), DAN `voter_color` sedia ada pada baris
  `data_pengundi` pengundi itu (jika ada) **kekal tidak disentuh** — bukan
  ditulis ganti dengan `'kelabu'`.

### Request body

Divalidasi oleh `App\Http\Requests\StoreMobileCulaanRequest`.

| Medan | Peraturan | Nota |
|---|---|---|
| `idempotency_key` | `required\|string\|max:64` | Lihat "Idempotency" di bawah |
| `nama` | `required\|string\|max:255` | |
| `no_ic` | `required\|string\|digits:12` | |
| `umur` | `required\|integer\|min:1\|max:150` | |
| `no_tel` | `required\|string\|max:255` | |
| `bangsa` | `required\|string\|max:255` | |
| `alamat` | `required\|string` | |
| `poskod` | `required\|string\|max:255` | |
| `negeri` | `required\|string\|max:255` | |
| `bandar` | `required\|string\|max:255` | |
| `parlimen` | `required\|string\|max:255` | Disemak lagi terhadap Bandar pemanggil — lihat bawah |
| `kadun` | `required\|string\|max:255` | |
| `mpkk` | `nullable\|string\|max:255` | |
| `daerah_mengundi` | `nullable\|string\|max:255` | |
| `lokaliti` | `nullable\|string\|max:255` | |
| `has_sumbangan` | `boolean` | Togol yang mengawal medan `nullable`/`required` di bawah |
| `locked_source_id` | `nullable\|integer` | Lihat "Masked-create" di bawah |
| `bil_isi_rumah` | required bila `has_sumbangan`: `integer\|min:1` | |
| `pendapatan_isi_rumah` | `nullable\|numeric\|min:0` | Medan sensitif |
| `pekerjaan` | required bila `has_sumbangan`: `in:Kerajaan,Swasta,Bekerja Sendiri,Tidak Bekerja` | Mesti sepadan `options.pekerjaan` |
| `jenis_pekerjaan` | required bila `has_sumbangan`: `array\|min:1` | `jenis_pekerjaan.*` = `string\|max:255`. **Tiada** peraturan `in:` — senarai `options` bukan senarai tertutup di sisi server |
| `jenis_pekerjaan_lain` | `nullable\|string\|max:255` | |
| `pemilik_rumah` | required bila `has_sumbangan`: `string\|max:255` | |
| `pemilik_rumah_lain` | `nullable\|string\|max:255` | |
| `jenis_sumbangan` | required bila `has_sumbangan`: `array\|min:1` | |
| `jenis_sumbangan_lain` | `nullable\|string\|max:255` | |
| `tujuan_sumbangan` | required bila `has_sumbangan`: `array\|min:1` | Boleh kosong dari `options` — lihat #11 |
| `tujuan_sumbangan_lain` | `nullable\|string\|max:255` | |
| `bantuan_lain` | required bila `has_sumbangan`: `array\|min:1` | |
| `bantuan_lain_lain` | `nullable\|string\|max:255` | |
| `perkeso_bantuan` | `nullable\|array` | |
| `perkeso_bantuan_lain` | `nullable\|string\|max:255` | |
| `zpp_jenis_bantuan` | `nullable\|array` | |
| `isejahtera_program` | `nullable\|string\|max:255` | |
| `bkb_program` | `nullable\|string\|max:255` | |
| `jumlah_bantuan_tunai` | `nullable\|numeric\|min:0` | |
| `jumlah_wang_tunai` | `nullable\|numeric\|min:0` | |
| `keahlian_parti` | `nullable\|string\|max:255` | |
| `kecenderungan_politik` | `nullable\|string\|max:255` | |
| `status_pengundi` | `nullable\|string\|max:255` | |
| `nota` | `nullable\|string` | |

Medan `*_lain` (`jenis_pekerjaan_lain`, `pemilik_rumah_lain`,
`jenis_sumbangan_lain`, `tujuan_sumbangan_lain`, `bantuan_lain_lain`,
`perkeso_bantuan_lain`) dilipat masuk semula ke dalam medan induk oleh
`CulaanPayloadNormalizer` sebelum simpan — lihat kod servis untuk peraturan
tepat (`pemilik_rumah`/`jenis_pekerjaan` guna padanan **tepat** `'Lain-lain'`;
`jenis_sumbangan`/`tujuan_sumbangan`/`bantuan_lain`/`perkeso_bantuan` guna
padanan **kabur** — mana-mana opsyen yang **mengandungi** teks "lain",
case-insensitive. Ketidakkonsistenan ini disengajakan/warisan — ia
mencerminkan tingkah laku sedia ada `ReportsController::hasilCulaanStore`).

**Semakan Parlimen** (dalam controller, bukan `rules()`): jika pemanggil
`isUser()` atau `isAdmin()`, `parlimen` yang dihantar **mesti** sama dengan
`$user->bandar->nama`. `super_user`/`super_admin` tidak disekat.

### Respons berjaya — 201
```json
{ "success": true, "culaan": { "id": 501, "no_ic": "800101015555" } }
```
**Hanya `id` dan `no_ic`** dikembalikan, bukan rekod penuh — respons dijalankan
melalui `VoterDataMasker::maskedIdAndIc()`. `no_ic` boleh jadi `'****'` untuk
pemanggil yang tidak boleh nyahtopeng — lihat "Respons ditopeng" di bawah.
Klien tidak boleh anggap 201 sentiasa membawa IC sebenar.

### Idempotency (wajib)

`idempotency_key` (string, maks 64 aksara, klien-jana) mesti unik setiap
**draf**, **bukan** setiap percubaan/permintaan HTTP. Klien mesti jana satu
kunci sekali sahaja apabila draf dicipta secara tempatan, dan guna kunci yang
**sama itu untuk setiap percubaan semula** (retry) draf tersebut. Kunci
segar setiap percubaan akan meniadakan tujuan mekanisme ini — ia akan
mencipta rekod pendua, iaitu bahaya yang sepatutnya dielakkan.

Pemadanan kunci yang sudah wujud **disemak-skop kepada pemanggil**
(`submitted_by = $user->id`) — pengguna lain tidak boleh main balik
(replay) kunci anda dan dapat balik rekod anda. Padanan kunci berjaya:

- **Mengembalikan rekod ASAL** dengan 201, **tidak menulis apa-apa** —
  walaupun payload semasa tidak lagi sah (medan wajib hilang), semakan
  Parlimen akan gagal sekarang, atau `locked_source_id` sumbernya telah
  dipadam sejak itu. Semakan main-balik berjalan **sebelum** semakan lain
  semuanya, dalam `StoreMobileCulaanRequest::prepareForValidation()`.
- Jika perlumbaan check-then-act menyebabkan dua permintaan serentak dengan
  kunci sama lepas semakan skop serentak, indeks unik DB pada
  `idempotency_key` akan gagalkan satu — controller menangkap
  `QueryException` itu dan cuba cari semula rekod bawah skop pemanggil
  (untuk kes ini masih 201 dengan rekod asal). Jika kunci itu didakwa oleh
  **pengguna lain** (bukan perlumbaan diri sendiri), controller memulangkan:
  ```json
  { "success": false, "errors": { "idempotency_key": ["Kunci idempotency ini telah digunakan oleh pengguna lain."] } }
  ```
  dengan status **409**.

**Jurang diketahui (belum dibetulkan):** main balik kunci yang sama dengan
**payload BERBEZA** senyap-senyap memulangkan rekod lama — tiada fingerprint
payload disemak. Ini bukan pepijat yang perlu dibetulkan oleh dokumen ini,
tetapi Plan 2 mesti tahu: jika klien secara tidak sengaja guna semula kunci
untuk draf yang berbeza (cth. bug dalam logik jana-UUID), server tidak akan
mengesannya — ia akan senyap memulangkan rekod draf pertama.

### Masked-create

Field agent yang mencari pengundi sedia ada (`GET /voters/search` atau
`/voters/{ic}`) mungkin nampak medan sensitif sebagai `'****'` kerana peranan
mereka tidak boleh nyahtopeng. Bila mereka pra-isi borang Culaan daripada
rekod itu, klien menghantar balik `'****'` untuk setiap medan sensitif yang
tidak pernah ditunjukkan kepada mereka, ditambah `locked_source_id` menunjuk
kepada ID rekod `data_pengundi` sumber. Server tukar `'****'` kepada nilai
sebenar sebelum simpan (`StoreMobileCulaanRequest::prepareForValidation()`,
dijalankan **sebelum** `rules()`).

Senarai medan sensitif — **`VoterDataMasker::SENSITIVE_FIELDS`**, disahkan
daripada kod, bukan andaian:
```
no_ic, umur, bangsa, no_tel, alamat, poskod, negeri, bandar, pendapatan_isi_rumah
```
(`nama` dan `nota` sengaja **tidak** ditopeng — nota/dokumen mesti kelihatan
merentas sejarah bantuan.)

Peraturan penting:

- **`'****'` TANPA `locked_source_id` ialah 422**, bukan laluan pintas
  pengesahan. Medan seperti `no_ic` (`digits:12`) atau `umur` (`integer`)
  akan gagal validasi biasa terhadap literal `'****'` — mask bukan bypass.
- Carian `locked_source_id` **diskop** melalui `VoterScopeService`, sama
  seperti `voters/{ic}`. `locked_source_id` yang **hilang** (dipadam/tidak
  wujud) DAN yang **di luar skop** pemanggil memulangkan **409 yang sama
  persis**:
  ```json
  { "success": false, "errors": { "locked_source_id": ["Rekod sumber tidak lagi wujud. Sila cari semula pengundi ini."] } }
  ```
  Ini disengajakan — membezakan kedua-duanya akan menjadikan medan ini oracle
  kewujudan ID rentas seluruh jadual `data_pengundi`.
- Tiada peraturan `exists:data_pengundi,id` pada `locked_source_id` — semakan
  tunggal ialah lookup diskop dalam controller/request; menambah `exists:`
  akan sendiri jadi oracle kewujudan tanpa skop.

### Respons ditopeng

`{culaan.no_ic}` dalam 201 dijalankan melalui `VoterDataMasker` juga — pemanggil
yang menghantar `'****'` kerana tidak boleh nyahtopeng rekod terkunci **akan
menerima `'****'` balik**, walaupun server menyimpan nilai sebenar dalam DB.
Ini turut terpakai kepada pemanggil `admin`/`super_user`/`super_admin` **jika**
rekod yang dicipta terkunci disebabkan penyerah asal (medan
`submitted_by` yang baru dicipta ini) — tetapi oleh kerana rekod baru
disimpan dengan `submitted_by = pemanggil sendiri`, ia hanya terkunci jika
peranan pemanggil sendiri ialah `user`. Ringkasnya: **seorang `user` tidak
pernah nampak IC sebenar dalam respons 201 rekod sendiri**, walaupun mereka
yang menaipnya.

---

## Pemetaan status → baldi kegagalan klien

| Status | Maksud | Baldi | Tindakan klien |
|---|---|---|---|
| 200/201 | Berjaya | — | Padam draf tempatan |
| 401 | Token dibatalkan | Auth | Kekal `queued`, minta login semula |
| 403 | Di luar Parlimen | Kekal | → Perlu Perhatian |
| 409 | Pendua / rekod sumber hilang | Kekal | → Perlu Perhatian |
| 422 | Validation | Kekal | → Perlu Perhatian |
| 429 | Rate limit | Sementara | Backoff, cuba semula |
| 5xx | Ralat server | Sementara | Backoff, cuba semula |
| timeout / tiada rangkaian | — | Sementara | Backoff, cuba semula |

**429 ialah sementara, bukan kekal** — ia satu-satunya 4xx yang mesti dicuba
semula. Salah kelaskan 429 sebagai kekal akan mengunci penghantaran yang sah
selama-lamanya dalam peti masuk "Perlu Perhatian", walhal ia cuma perlu
menunggu (guna header `Retry-After` bila ada) dan cuba lagi.

---

## Had kadar — apa yang akan tersadung

`voters/search` dan `voters/{ic}` dihadkan **30 permintaan / minit**. Komen
dalam `routes/web.php` menyatakan ini "cukup murah hati untuk carian medan
biasa (agen mengimbas/menaip berulang kali) sambil menyekat serangan oracle
IC (bruteforce) dan wildcard-enumeration". **Had ini direka untuk aliran
imbas-kemudian-cari (OCR baca IC penuh 12-digit → satu carian tepat), bukan
untuk type-ahead tanpa debounce.** Klien yang menghantar satu permintaan
carian bagi **setiap ketukan kekunci** semasa penggunaan biasa akan tersadung
429 dalam masa singkat — carian nama enam-tujuh huruf pun sudah cukup
ketukan. Plan 2 **mesti** debounce carian (cth. 300–500ms selepas taip
berhenti) atau tunggu ketukan Enter/tekan-cari secara eksplisit.

`culaan/options` dan `culaan/mine` turut 30/minit — GET baca sahaja, bukan
laluan burst luar talian.

`POST /culaan` dihadkan **60 permintaan / minit**, lebih tinggi daripada had
bacaan sebab ia dijangka melepaskan baris gilir draf luar talian (~20+) sekali
gus apabila isyarat kembali selepas zon mati. Klien masih perlu backoff
selepas 429 (jangan burst semula serta-merta), tetapi had ini sengaja
dilonggarkan untuk kes guna sebenar itu.

`login` tiada throttle middleware — had kadarnya (5 percubaan / 60 saat,
dikunci `telephone+ip`) dikendali oleh `RateLimiter` manual dalam controller,
jadi bentuk 429-nya **berbeza** daripada 429 middleware yang lain (lihat
"Konvensyen respons").

`forgot-password` dihad `3` permintaan / **5 minit** (bukan seminit).

---

## Percanggahan antara brief/plan dan kod sebenar

Disenaraikan di sini secara eksplisit kerana brief Task 7 menyatakan "the plan
is now an unreliable description of what was built."

1. **Bilangan endpoint.** Brief Task 7 menyatakan "12 endpoint". `route:list`
   dan dokumen reka bentuk (`2026-07-17-aplikasi-mudah-alih-user-design.md`)
   kedua-duanya menunjukkan **13**. (7 sedia ada + 6 baharu mengikut dokumen
   reka bentuk — tetapi lihat titik #2 di bawah untuk mengapa "6 baharu" itu
   sendiri tidak tepat.)

2. **`POST /api/mobile/token/refresh` tidak wujud.** Dokumen reka bentuk
   menyenaraikan endpoint ini sebagai salah satu daripada "enam baharu",
   dengan tujuan "elak logout paksa di lapangan". Ia **tidak** didaftar dalam
   `routes/web.php` dan tiada method sepadan dalam mana-mana controller.
   Sanctum token dalam sistem ini tiada tempoh luput automatik
   (`config/sanctum.php: 'expiration' => null`), jadi 401 hanya berlaku bila
   token dipadam secara eksplisit (logout, atau log masuk baharu yang
   memansuhkan token lama — lihat bahagian `/login`). **Plan 2 tidak boleh
   reka bentuk mekanisme refresh proaktif** kerana tiada endpoint untuknya;
   satu-satunya pemulihan daripada 401 ialah log masuk semula penuh, seperti
   yang jadual "baldi kegagalan" sudah nyatakan.

3. **Bungkusan respons `{success: bool}` tidak sejagat.** DoD dalam brief
   Task 7 (dan design doc) menyatakan "Every endpoint returns the
   `{"success": bool, ...}` envelope, including validation failures." Ini
   **tidak benar** untuk: `POST /register` (semua ralat), separuh
   `POST /login` (ralat medan-hilang sahaja), `POST /web-auth-token`
   (respons berjaya), tiga closure geografi (respons berjaya — tatasusunan
   telanjang), dan **semua** 401/429 middleware (lihat "Konvensyen respons"
   di atas untuk contoh sebenar). Ini bukan pepijat yang dibetulkan oleh
   tugasan ini — ia dilaporkan di sini supaya Plan 2 mengendalikannya, bukan
   mengandaikannya konsisten.

4. **Bahasa mesej ralat tidak konsisten.** CLAUDE.md menegaskan "All
   user-facing text is Bahasa Melayu." `POST /register` dan sebahagian
   `POST /login` (laluan `$request->validate()` binaan Laravel) memulangkan
   mesej **Bahasa Inggeris** lalai Laravel (cth. *"The telephone field is
   required."*) kerana tiada `messages()` tersuai disediakan dan
   `config/app.php` `locale => en`. Ini dilaporkan sebagai isu, **tidak
   dibetulkan** — di luar skop tugasan dokumentasi ini.

Tiada percanggahan lain yang dijumpai antara tingkah laku sebenar dan enam
perkara yang disenaraikan dalam brief di bawah "Behaviours Plan 2 will get
wrong" (idempotency, masked-create, respons ditopeng, carian ikut peranan,
struktur `jenis_pekerjaan`, had kadar) — semuanya disahkan sepadan dengan kod
dan ujian.

## Perkara yang tidak dapat disahkan

- Tingkah laku sebenar `WhatsappService::sendCategoryDefault()` /
  `WhatsappService::send()` (kejayaan/kegagalan penghantaran sebenar) tidak
  disemak — hanya laluan kod `forgot-password` yang menyemak nilai pulangan
  boolean-nya disahkan.
- Tiada ujian feature merangkumi `POST /login`, `POST /register`, atau
  `POST /forgot-password` dalam `tests/Feature/Mobile*` — bentuk respons untuk
  tiga endpoint ini disahkan dengan membaca kod controller terus dan
  menjalankan permintaan ad hoc semasa menulis dokumen ini (dipadam selepas
  disahkan), bukan daripada ujian sedia ada yang lulus.
