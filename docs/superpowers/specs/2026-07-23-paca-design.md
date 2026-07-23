# PACA — Senarai Petugas PACABA

Tarikh: 2026-07-23
Status: design, diluluskan

## Masalah

Penyelaras pilihan raya perlu menyusun petugas PACABA (Polling Agent / Counting
Agent / Barung Agent) bagi setiap Saluran di setiap Pusat Mengundi, mengikut
kerusi. Hari ini ia diuruskan dalam fail Excel per kerusi (satu helaian per
Pusat) — tiada satu tempat, tiada borang awam untuk petugas mendaftar sendiri,
tiada sejarah.

Sampel sebenar: `SENARAI PETUGAS PACABA N03` (Pinang Tunggal), satu helaian per
Pusat Mengundi, jadual per Saluran, lajur **Nama · No K/P · No Tel · Parti ·
Jawatan · Masa Bertugas**, jawatan **PA1/PA2/PA3/CA**.

## Sumber data — hanya daripada scoresheet

Struktur Pusat Mengundi → Saluran DISEMAI daripada `Borang14Form.structure`
(hasil ekstraksi scoresheet), melalui `Borang14StrukturService::collapse()` yang
sedia ada. Kerusi tanpa scoresheet tidak muncul dalam pemilih PACA langsung.
Ini bermakna:
- Tiada geografi baharu, tiada padanan rentetan baharu.
- `collapse($form->structure)` memulangkan `pusat[]` dengan
  `{row_id, dm, pusat, saluran_count, saluran_labels}` — tepat apa yang PACA
  perlukan.

## Keputusan reka bentuk (manusia)

| Soalan | Keputusan |
|---|---|
| Slot masa | 4 slot lalai per saluran (PA1/PA2/PA3 + CA), masa boleh diedit, minimum 2 jam dikuatkuasakan, CA slot terakhir berakhir "selesai". Lalai mula 08:00 blok 2 jam. |
| Borang awam | Pautan awam per Pusat Mengundi (token tidak boleh diteka). Petugas awam buka pautan → pilih Saluran + slot kosong → isi butiran. |
| Parti | Teks bebas dengan cadangan daripada `KeahlianParti` (Data Induk). |
| Kebenaran | Borang admin: admin + super_admin (skop Bandar), semakan dalam pengawal. Borang awam: tiada login, dikunci pada SATU Pusat melalui token. |
| Sejarah | Setiap "Simpan" admin menyimpan snapshot JSON penuh (cermin `Borang14Snapshot`), boleh dilihat dan dipulihkan. |

## Hierarki & entiti

```
Kerusi (DUN/Parlimen)              paca_forms
└─ Pusat Mengundi                  paca_pusat   (+ ketua_nama, ketua_tel, public_token)
   └─ Saluran                      paca_saluran
      └─ Slot                      paca_slot    (jawatan, masa, + petugas)
```

- **paca_forms**: `kawasan_type, kawasan_id, jenis_pr, tahun, borang14_form_id
  (sumber struktur, nullable), created_by`. Unik pada
  `(kawasan_type, kawasan_id, jenis_pr, tahun)`.
- **paca_pusat**: `paca_form_id, dm, pusat, ketua_nama, ketua_tel, public_token
  (unik), urutan`.
- **paca_saluran**: `paca_pusat_id, label, urutan`.
- **paca_slot**: `paca_saluran_id, jawatan (PA1..PAn|CA), masa_mula, masa_tamat
  (null = "selesai"), urutan`, + petugas: `petugas_nama, petugas_kp,
  petugas_tel, petugas_parti` (semua nullable sehingga diisi).
- **paca_snapshots**: `paca_form_id, data (JSON penuh), reason, created_by,
  created_at`. Cermin Borang14Snapshot.

Tiada FK ke jadual geografi — `paca_forms` mencerminkan konvensyen
`borang14_forms` (kawasan sebagai type+id).

## Logik slot masa

`PacaSlotPlanner` (perkhidmatan tulen, boleh diuji):
- Menyemai N slot bagi satu saluran. Lalai N = 4: PA1, PA2, PA3, CA.
- Masa lalai: mula 08:00, setiap blok minimum 2 jam. PA1 08:00–10:00, PA2
  10:00–12:00, PA3 12:00–14:00, CA 14:00–selesai (masa_tamat null).
- **Jawatan diterbitkan daripada urutan**: slot terakhir = CA, selebihnya PA1..n.
  Menambah slot menomborkan semula (PA sedia ada kekal PA, CA sentiasa terakhir).
- **Minimum 2 jam dikuatkuasakan** semasa simpan: `masa_tamat - masa_mula >= 120
  minit` bagi slot bukan-CA yang mempunyai kedua-dua masa. Slot CA
  (`masa_tamat` null) terlepas semakan tamat.
- Masa disimpan sebagai rentetan `HH:MM` (24 jam); UI memapar 12 jam (am/pm).

Menambah PA (item 4) = tambah slot pada saluran. Menambah Saluran = tambah
saluran pada Pusat. Kedua-dua disokong; primary ialah "Tambah PA".

## Borang awam — privasi

Pautan awam mendedahkan SATU Pusat. Pelawat awam nampak:
- Nama Pusat, senarai Saluran, dan slot mana **kosong vs terisi** (label sahaja,
  cth "PA2 — terisi").
- Butiran peribadi orang lain (No K/P, No Tel) TIDAK PERNAH didedahkan pada
  paparan awam. No K/P disahkan sebagai IC Malaysia sah (`MalaysianIc`) tetapi
  tidak dipaparkan semula pada pandangan awam.
- Menghantar ke slot yang SUDAH terisi ditolak (422) — elak menulis ganti secara
  senyap. Penyelaras boleh mengosongkan slot dari borang admin.

Token: rentetan rawak 32-aksara, `paca_pusat.public_token`, dicari terus (bukan
melalui id berjujukan).

## UI/UX

- **Pilihanraya → PACA** (menu baharu, admin/super_admin): pemilih kerusi (hanya
  kerusi berscoresheet) → grid Pusat → Saluran → slot. Isi/edit petugas, Ketua
  PACABA per Pusat, Tambah PA / Tambah Saluran, **Simpan**, lihat **Sejarah**,
  salin **Pautan Awam** per Pusat.
- **Awam** (`/paca/{token}`): satu Pusat, senarai Saluran + slot; borang isi
  butiran pada slot kosong. Sahkan IC + telefon. Terima kasih selepas hantar.

## Ujian

1. `PacaSlotPlanner`: 4 slot lalai betul; CA sentiasa terakhir; menambah slot
   menomborkan semula; masa lalai 2 jam.
2. Semaian daripada `collapse()`: pusat/saluran betul; saluran_count → bilangan
   saluran; kerusi tanpa scoresheet ditolak.
3. Simpan admin: minimum 2 jam ditolak (422); snapshot ditulis; skop kerusi
   (admin bandar lain 403).
4. Awam: token sah memaparkan Pusat betul; hantar ke slot kosong berjaya; hantar
   ke slot terisi ditolak; IC tak sah ditolak; No K/P orang lain tidak bocor
   pada paparan awam.
5. Sejarah: senarai snapshot; pulihkan menggantikan keadaan semasa.

## Bukan dalam skop

- Statistik agregat (helaian STATISTIK dalam Excel) — fasa kemudian.
- Import terus daripada fail Excel PACA sedia ada.
- Notifikasi/WhatsApp kepada petugas.
- Tarikh latihan / kehadiran latihan (medan Excel `TARIKH LATIHAN`) — boleh
  ditambah kemudian; tidak menghalang aliran teras.
