# Borang 14 — Skop Pertandingan (Parlimen / DUN / Kedua-duanya)

Tarikh: 2026-08-01
Status: Direka bentuk secara autonomi atas arahan pengguna ("teruskan buat, saya hendak
tidur"). SEMUA andaian direkodkan di bawah supaya boleh disemak semula.

## Masalah

Scoresheet Gemas (`SCORESHEET DAN SENARAI DM DUN N34 GEMAS`) mengandungi DUA
pertandingan bersebelahan:

| Lajur | Warna | Pertandingan | Calon |
|---|---|---|---|
| 1-3 | Biru | **PRU15 (Parlimen)** | MOHD ISAM (BN), MEJAR (B) ABDUL HALIM (PN), FAIZ FADZIL (PH) |
| 4-5 | Merah | **PRN15 (DUN)** | HAJI RIDZUAN AHMAD (PN), ABD RAZAK BIN AB SAID (BN) |

`ScoresheetExtractor` mendatarkan kedua-duanya menjadi SATU senarai 5 calon. Skrin
Keyin lalu memaparkan "5 Penjuru" — satu pertandingan lima penjuru, yang tidak wujud.
Sebenarnya ia tiga penjuru di Parlimen dan dua penjuru di DUN.

Akibatnya: undi PRU dan PRN dijumlahkan sebagai satu perlumbaan, papan markah
memaparkan lima calon bersaing antara satu sama lain, dan tiada satu pun angka itu
bermakna.

Ciri pilihanraya serentak yang baru dihantar menyediakan MODEL untuk merekod dua
pertandingan (lajur `contest`, borang takrifan Parlimen, `Borang14RollUp`). Yang tiada
ialah cara pengguna MEMBERITAHU sistem calon mana milik pertandingan mana.

## Prinsip

**Jangan sekali-kali percaya ekstraksi.** Prompt `ScoresheetExtractor` sudah pun
meminta AI mengambil pertandingan DUN sahaja apabila ada dua, dan ia tetap
mengembalikan kelima-lima calon. Pembetulan oleh manusia bukan tampalan — ia
seni bina yang betul. Sistem membentangkan apa yang difahaminya; pengguna
mengesahkan sebelum apa-apa diterbitkan.

## Keputusan

| Perkara | Keputusan |
|---|---|
| Skop borang | Pilihan eksplisit: **Parlimen sahaja** / **DUN sahaja** / **Kedua-duanya (serentak)** |
| Lalai | Skop tunggal yang sepadan dengan `kawasan_type` borang — tingkah laku hari ini, tiada perubahan bagi borang sedia ada |
| Penetapan calon | Setiap slot parti ditanda milik Parlimen atau DUN, hanya apabila skop = kedua-duanya |
| Pemisahan | Semasa simpan: slot Parlimen dinomborkan semula 1..n pada borang takrifan Parlimen; slot DUN dinomborkan semula 1..m pada borang DUN |
| Undi sedia ada | **Tolak** perubahan skop/penetapan apabila undi sudah wujud. Jangan petakan semula secara senyap |

## Mengapa menolak dan bukan memetakan semula

Memetakan semula undi bermakna menulis semula nombor slot pada baris undi sebenar.
Jika logiknya salah, undi calon A menjadi undi calon B — kegagalan paling teruk yang
mungkin berlaku dalam sistem ini, dan senyap sepenuhnya.

Penetapan pertandingan ialah kerja SETUP, dilakukan sekali sebelum kemasukan undi
bermula. Menolak perubahan selepas undi wujud tidak menghalang apa-apa aliran kerja
sebenar, dan menghapuskan seluruh kelas kerosakan senyap. Mesej ralat memberitahu
pengendali untuk mengosongkan undi dahulu jika mereka benar-benar perlu menukar
penetapan.

## Model Data

**Tiada migrasi.** Semua yang diperlukan sudah wujud:

- `borang14_forms.borang14_form_parlimen_id` — pautan ke borang takrifan Parlimen
- `borang14_forms.parties` (json) — senarai calon setiap borang
- `borang14_forms.penjuru` — bilangan calon setiap pertandingan
- `borang14_votes.contest` — pertandingan mana setiap undi milik

Penetapan pertandingan ialah keadaan SEMENTARA dalam UI. Selepas simpan ia dinyatakan
sepenuhnya oleh CARA senarai calon dipisahkan antara dua borang. Tiada lajur baharu
diperlukan, dan tiada keadaan pendua yang boleh menyimpang.

## Aliran

```
Muat naik scoresheet Gemas
  -> extractor memulangkan 5 calon (mendatar, salah)
  -> skrin Keyin memaparkan 5 baris Parti

Pengguna memilih Skop: "Kedua-duanya (serentak)"
  -> setiap baris Parti mendapat pemilih pertandingan
  -> pengguna menanda 1,2,3 = Parlimen ; 4,5 = DUN

Simpan
  -> borang takrifan Parlimen dicipta/dikemas kini (P___/pru/2022)
       parties = [MOHD ISAM, MEJAR, FAIZ]  penjuru = 3
  -> borang DUN dikemas kini
       parties = [HAJI RIDZUAN, ABD RAZAK]  penjuru = 2
       borang14_form_parlimen_id -> borang takrifan
  -> skrin Keyin kini memaparkan dua jalur, 3 lajur dan 2 lajur
```

## Pengesahan

- Setiap pertandingan mesti mempunyai sekurang-kurangnya 2 calon. Pertandingan satu
  calon bukan pertandingan; tolak dengan mesej yang menamakan pertandingan itu.
- Setiap pertandingan mesti mempunyai paling banyak 6 calon (had `penjuru` sedia ada).
- Skop "kedua-duanya" pada borang `kawasan_type = parlimen` ditolak — pautan hanya
  wujud dari borang DUN ke borang Parlimen, tidak sebaliknya.
- Skop "Parlimen sahaja" pada borang DUN bermakna borang DUN itu merekod pertandingan
  PARLIMEN sahaja. Ini SAH: sesetengah pasukan hanya melaporkan bahagian PRU mereka.
  Ia memautkan borang takrifan dan meninggalkan `parties` DUN kosong.

## Had yang diketahui

Jumlah calun merentas kedua-dua pertandingan mesti muat dalam senarai rata yang
diekstrak, dan `penjuru` dihadkan `in:2,3,4,5,6`. Scoresheet dengan lebih daripada
6 calon bergabung (cth. 4 PRU + 3 PRN) tidak boleh diwakili hari ini. Gemas ialah
3+2=5, jadi ini tidak menghalang kes sebenar yang ada. Direkodkan, tidak diselesaikan.

## Ujian

| Ujian | Yang dipaku |
|---|---|
| Pemisahan | 5 slot rata -> Parlimen 1..3 + DUN 1..2, pada borang yang betul, pautan ditetapkan |
| Lalai | Skop tunggal berkelakuan TEPAT seperti hari ini; borang sedia ada tidak tersentuh |
| Tolak-bila-ada-undi | Menukar penetapan dengan undi sedia ada -> 422, tiada tulisan |
| Minimum dua calon | Satu calon dalam sesuatu pertandingan -> 422 menamakan pertandingan itu |
| Parlimen-sahaja pada borang DUN | Pautan ditetapkan, parties DUN kosong, tiada ralat |
| Skop kedua-dua pada borang Parlimen | 422 |
