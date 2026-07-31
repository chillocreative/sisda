# Borang 14 Pilihanraya Serentak (PRU + PRN) — Reka Bentuk

Tarikh: 2026-07-31
Status: Diluluskan (menunggu pelan pelaksanaan)

## Masalah

Apabila Pilihan Raya Umum (PRU) dan Pilihan Raya Negeri (PRN) diadakan
SERENTAK, seorang pengundi di SATU saluran mengundi DUA kertas undi — satu
untuk kerusi Parlimen, satu untuk kerusi DUN. PACA di saluran itu menerima dua
Borang 14 dan mesti merekod kedua-duanya.

Scoresheet rujukan (`SCORESHEET DAN SENARAI DM DUN N34 GEMAS`, PRU15 vs PRN15)
menunjukkan bentuk sebenar data: SATU baris per saluran, dengan lajur calon
PRU dan calon PRN bersebelahan. Struktur Daerah Mengundi > Pusat Mengundi >
Saluran adalah SAMA bagi kedua-dua pertandingan; hanya senarai calon berbeza.

Model semasa tidak boleh mewakilinya:

- `borang14_forms` mempunyai `UNIQUE(kawasan_type, kawasan_id, jenis_pr, tahun)`
  — sesuatu borang adalah `dun` ATAU `parlimen`, tidak pernah kedua-duanya.
- `borang14_votes` mempunyai `UNIQUE(borang14_form_id, pusat, saluran, slot)`.
  Slot 1 pada saluran yang sama ialah BN dalam KEDUA-DUA pertandingan (PRN 224,
  PRU 93) — kedua-duanya berlanggar pada kekangan itu.
- Untuk merekodnya hari ini, struktur DM/Pusat/Saluran yang sama perlu dikunci
  masuk DUA KALI, dan borang Parlimen merangkumi SEMUA DUN dalam Parlimen itu
  sedangkan sesuatu pasukan hanya melaporkan salurannya sendiri.

## Keputusan

| Perkara | Keputusan |
|---|---|
| Matlamat | Sedia untuk PRU+PRN serentak — satu PACA merekod kedua-dua kertas undi di satu saluran |
| Undi Parlimen | Direkod pada borang setiap DUN; jumlah Parlimen = hasil tambah DUN-DUNnya |
| Model | Dimensi pertandingan; `borang14_votes` mendapat lajur `contest` |
| Angka per saluran | `berdaftar` dikongsi; undi ditolak + tidak dimasukkan berasingan per pertandingan |
| Papan markah Parlimen | Utamakan borang Parlimen; jika tiada, kumpulkan DUN-DUNnya |
| Senarai calon | Satu takrifan dikongsi per Parlimen+tahun, dirujuk oleh borang DUN |

## Seni Bina

**Borang Parlimen ITU SENDIRI ialah takrifan yang dikongsi.**
`UNIQUE(kawasan_type, kawasan_id, jenis_pr, tahun)` sudah menjamin TEPAT SATU
baris `parlimen/133/pru/2027` — jaminan keunikan itulah yang diperlukan oleh
sebuah takrifan bersama. Borang DUN menunjuk kepadanya melalui
`borang14_form_parlimen_id`.

Medan `structure` borang Parlimen membezakan dua senario:

```
PRU sahaja 2027                 PRU+PRN serentak 2027
─────────────────────           ───────────────────────
parlimen/133/pru/2027           parlimen/133/pru/2027
  structure: penuh                structure: KOSONG  <- takrifan sahaja
  votes: miliknya sendiri         votes: tiada
                                      ^ dirujuk oleh
                                dun/34/prn/2027
                                  structure: saluran Gemas
                                  votes: contest='dun'      (PRN)
                                         contest='parlimen' (bahagian PRU)
```

Pembeza itu terbit sendiri daripada keputusan "utamakan borang, jika tiada
kumpulkan" — bukan direka khas untuknya.

**`jenis_pr` KEKAL `'prn'`** pada borang DUN. Borang itu tetap borang DUN
tersebut; pertandingan Parlimen menumpang. Menukarnya kepada nilai baharu
(cth. `'serentak'`) akan mengubah makna kunci unik dan memaksa audit setiap
pertanyaan yang menapis padanya.

**Alternatif yang ditolak:** jadual `pertandingan` berasingan (menambah konsep
kedua di sebelah "borang" yang setiap pengguna sedia ada perlu belajar), dan
pemfaktoran semula penuh pertandingan keluar daripada `borang14_forms`
(migrasi terbesar terhadap data undi produksi, untuk faedah yang kebanyakannya
estetik).

## Model Data

### `borang14_votes`

| Perubahan | Butiran |
|---|---|
| `contest` | **baharu** — `dun` \| `parlimen`. Pertandingan mana baris ini milik |
| `borang14_votes_cell_unique` | menjadi `(borang14_form_id, contest, pusat, saluran, slot)` |

Isian belakang: `contest = kawasan_type borang itu sendiri`. Borang DUN sedia
ada menjadi `'dun'`, borang Parlimen sedia ada menjadi `'parlimen'`. Setiap
baris sedia ada mengekalkan maknanya; tiada penulisan semula data.

Membentuk semula index itu memerlukan turutan ralat 1553 daripada
`2026_07_16_100001`: FK pada `borang14_form_id` bersandar padanya, jadi
**gugur FK -> gugur unique -> tambah unique yang diperluas -> pasang semula FK**.

### `borang14_forms`

| Perubahan | Butiran |
|---|---|
| `borang14_form_parlimen_id` | **baharu**, nullable, FK rujuk-diri, `nullOnDelete` |

`jenis_pr` dan kunci unik TIDAK berubah.

### Yang tidak perlu berubah

- **`berdaftar`** sudah berada dalam `structure` borang DUN, yang dikongsi oleh
  kedua-dua pertandingan secara semula jadi. Keputusan "berdaftar dikongsi"
  tidak menelan sebarang kos.
- **Undi ditolak (slot 90) dan tidak dimasukkan (slot 91)** hanyalah slot, jadi
  lajur `contest` memisahkannya per pertandingan tanpa pengendalian khas.

### Bahaya: had `penjuru` per pertandingan

`penjuru` borang DUN mengehadkan bilangan slot partinya. Pertandingan Parlimen
mempunyai kiraannya sendiri (3 di Gemas berbanding 2 bagi DUN). Had mesti
dibaca daripada pertandingan yang sel itu milik — `penjuru` borang Parlimen
yang dipaut, BUKAN borang DUN. Terlepas pandang di sini akan menolak atau
mengosongkan sel secara senyap. Wajib dipaku dengan ujian.

## Skrin Kemasukan

Susun atur mengikut scoresheet rujukan: satu baris per saluran, pertandingan
bersebelahan dalam dua kumpulan berjalur.

```
                      +---- PRN . N34 GEMAS ----+ +---- PRU . P133 ------------+
Pusat        Saluran  |  PN    BN   Tolak  T.Msk| |  BN    PN    PH  Tolak T.Msk|  Berdaftar
SK Gemas        3     |  63   224     5      1  | |  93    27   204    8     0  |    420
```

- Tajuk jalur menamakan pertandingan DAN kerusinya, supaya PACA pada pukul 11
  malam tidak tersilap kertas undi.
- `Berdaftar` berada DI LUAR kedua-dua jalur kerana ia dikongsi — kedudukannya
  membawa makna itu.

**Autosimpan mesti berkunci pada pertandingan.** Autosimpan sedia ada
menghantar sel sebagai `(pusat, saluran, slot)`; ia kini menghantar
`(contest, pusat, saluran, slot)`. Tanpa `contest`, satu ketukan kekunci PRU
akan MENULIS GANTI sel PRN pada kedudukan yang sama — kelas pepijat
tulis-ganti senyap yang sama seperti key-drift yang pernah berlaku.

**Mod serentak dimatikan secara lalai.** Dalam tetapan borang, togol
"Pilihanraya serentak (PRU + PRN)" mendedahkan pemilih Parlimen; memilih satu
akan memaut `borang14_form_parlimen_id` dan mencipta borang takrifan jika
belum wujud. Borang sedia ada tidak disentuh dan dipapar tepat seperti hari
ini — satu jalur, tiada perubahan visual.

## Kumpulan (Roll-up)

**`Borang14RollUp`** — satu perkhidmatan menjawab "apakah keputusan Parlimen?":

1. Jika borang Parlimen mempunyai `structure` sendiri -> baca terus
   (PRU sahaja, laluan hari ini).
2. Jika tidak -> jumlahkan undi `contest = 'parlimen'` merentas setiap borang
   DUN yang dipaut kepadanya.

Kerana senarai calon ditakrifkan SEKALI pada borang Parlimen, slot 1 bermakna
orang yang sama di setiap DUN, jadi hasil tambah sentiasa serupa-dengan-serupa.

**Ia melaporkan liputan bersama angka** — "3 daripada 5 DUN melapor" — kerana
kumpulan separa pada malam keputusan TIDAK boleh kelihatan seperti keputusan
muktamad.

## Kesan Hiliran

**Papan markah** mendapatnya secara percuma:
`ScoreboardPayload::forSeat('parlimen', 133)` memanggil roll-up dan bukan
membaca borang. Papan DUN tidak berubah — ia membaca `contest = 'dun'`.

**RISIKO REGRESI UTAMA — audit setiap pembaca undi.** Pembaca sedia ada
menyoal `borang14_votes` TANPA penapis pertandingan. Selepas migrasi, bacaan
tanpa penapis pada borang serentak akan menjumlahkan PRU dan PRN bersama dan
menghasilkan kira-kira dua kali ganda. Setiap satu mesti diberi pertandingan
yang eksplisit:

- `app/Http/Controllers/Borang14Controller.php`
- `app/Models/Borang14Form.php` (hubungan `votes()`)
- `app/Services/Pilihanraya/ScoreboardPayload.php`
- `app/Services/Pilihanraya/Borang14ScenarioMapper.php` (membaca slot 90/91)
- `app/Support/Borang14Reference.php`

Ini kod yang SUDAH dihantar ke produksi, bukan kod baharu — di situlah risiko
sebenar perubahan ini.

## Pengendalian Ralat

- Borang DUN dipaut kepada borang Parlimen yang kemudiannya dipadam ->
  `nullOnDelete` menjadikan pautan null; borang kembali kepada mod satu
  pertandingan dan undi `contest='parlimen'` menjadi yatim. Kumpulan mesti
  mengabaikan borang tanpa pautan, bukan mengira undi yatim itu.
- Roll-up dengan sifar DUN melapor -> pulangkan `null` liputan, BUKAN sifar
  undi. Tidak diketahui bukan sifar.
- `penjuru` Parlimen berubah selepas undi dikunci masuk -> sel melebihi had
  baharu mesti dilaporkan, bukan dipadam senyap.

## Ujian

| Ujian | Yang dipaku |
|---|---|
| Migrasi | Kunci sel diperluas; isian belakang `contest` mengikut `kawasan_type`; turutan 1553; `down()` enggan |
| Kemasukan dua pertandingan | Sel PRU dan PRN pada saluran+slot yang SAMA hidup berdampingan; autosimpan tidak menulis ganti silang |
| Had penjuru | Sel Parlimen dihadkan oleh `penjuru` borang Parlimen, bukan borang DUN |
| Roll-up | Jumlah merentas DUN; liputan separa dilaporkan; sifar DUN -> null bukan sifar |
| Papan Parlimen | Borang dengan struktur -> baca terus; tanpa struktur -> kumpul |
| Regresi hiliran | Setiap pembaca dalam senarai audit memulangkan angka SATU pertandingan sahaja |

Garis dasar projek: 20 kegagalan sedia ada (Breeze `Auth`/`ProfileTest`). Set
ujian penuh perlu `php -d memory_limit=1G vendor/bin/phpunit`.

## Di Luar Skop

- Mengimport scoresheet PDF sedia ada (Gemas PRU15/PRN15) sebagai data. Reka
  bentuk ini menyediakan bentuknya; import ialah kerja berasingan.
- Lebih daripada dua pertandingan serentak.
- Kumpulan peringkat Negeri.
