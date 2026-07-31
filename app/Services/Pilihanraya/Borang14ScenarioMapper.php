<?php

namespace App\Services\Pilihanraya;

use App\Models\Borang14Form;
use App\Support\Borang14Reference;
use RuntimeException;

/**
 * Tukar satu Borang14Form kepada bentuk AnalisaScenario sedia ada.
 *
 * Borang 14 simpan per saluran dengan slot bernombor; Analisa mahu per Daerah
 * Mengundi dengan nama parti sebagai kunci. Mapper ini satu-satunya tempat
 * penukaran itu berlaku — ElectionComparisonService tidak tahu senario datang
 * dari mana.
 */
class Borang14ScenarioMapper
{
    private const SLOT_DITOLAK = 90;
    private const SLOT_TIDAK_DIMASUKKAN = 91;

    /**
     * $kontes melalaikan kepada pertandingan borang itu sendiri — satu senario
     * Analisa ialah keputusan SATU kerusi, jadi ia tidak boleh mencampurkan
     * dua pertandingan. Pada borang serentak, membaca $form->votes tanpa
     * penapis akan menjumlahkan undi PRU dan PRN ke dalam senario yang sama.
     *
     * @return array{rows: array<int, array>, totals: array}
     */
    public function map(Borang14Form $form, ?string $kontes = null): array
    {
        $kontes ??= $form->contestSendiri();

        $parties = $this->partyNames($form);
        $pusatToDm = $this->pusatToDm($form);
        $berdaftar = $this->berdaftarPerDm($form);

        // Kumpul: DM => ['undi' => [nama => n], 'ditolak' => n]
        $groups = [];
        foreach ($form->votesFor($kontes)->get() as $v) {
            $dm = $v->pusat === ''
                ? trim((string) $v->saluran)          // baris peringkat DUN: UNDI POS / UNDI AWAL
                : ($pusatToDm[$v->pusat] ?? null);

            if ($dm === null || $dm === '') {
                continue;                              // pusat tidak dikenali — jangan reka DM
            }

            $groups[$dm] ??= ['undi' => array_fill_keys(array_values($parties), 0), 'ditolak' => 0];

            if ($v->slot === self::SLOT_DITOLAK) {
                $groups[$dm]['ditolak'] += (int) $v->undi;
            } elseif ($v->slot === self::SLOT_TIDAK_DIMASUKKAN) {
                // (D) tiada tempat dalam model Analisa — sengaja diabaikan.
            } elseif (isset($parties[$v->slot])) {
                $groups[$dm]['undi'][$parties[$v->slot]] += (int) $v->undi;
            }
        }

        if ($groups === []) {
            throw new RuntimeException('Borang 14 ini tiada undi untuk dipetakan.');
        }

        $rows = [];
        foreach ($groups as $dm => $g) {
            $rows[] = [
                'kawasan' => $dm,
                'pemilih' => $berdaftar[$dm] ?? null,   // null, BUKAN 0 — lihat spec
                'keluar'  => array_sum($g['undi']) + $g['ditolak'],
                'ditolak' => $g['ditolak'],
                'undi'    => $g['undi'],
            ];
        }

        return ['rows' => $rows, 'totals' => $this->totals($rows, $form, $parties)];
    }

    /**
     * @return array<int, string> slot => nama parti
     *
     * Menolak sebarang calon yang belum dipetakan kepada parti (Finding 2):
     * import scoresheet mengisi parties[].nama dengan nama CALON sendiri
     * sebagai placeholder — keahlian_parti_id kekal null sehingga manusia
     * memetakannya di tab Keyin (sama seperti allPartiesMapped KeyinTab).
     * Tanpa semakan ini, "EDDIN SYAZLEE BIN SHITH" boleh terlepas ke AI
     * seolah-olah ia satu parti.
     */
    private function partyNames(Borang14Form $form): array
    {
        $partiesRaw = $form->parties ?? [];

        if ($partiesRaw === [] || collect($partiesRaw)->contains(fn ($p) => empty($p['keahlian_parti_id']))) {
            throw new RuntimeException('Sebahagian calon dalam Borang 14 ini belum dipetakan kepada parti. Petakan setiap calon di tab Keyin dahulu.');
        }

        $names = [];
        foreach ($partiesRaw as $p) {
            $nama = trim((string) ($p['nama'] ?? ''));
            if ($nama !== '' && isset($p['slot'])) {
                $names[(int) $p['slot']] = mb_strtoupper($nama);
            }
        }

        if ($names === []) {
            throw new RuntimeException('Borang 14 ini belum ada nama parti. Petakan parti di tab Keyin dahulu.');
        }

        return $names;
    }

    /** @return array<string, string> nama Pusat Mengundi => nama Daerah Mengundi */
    private function pusatToDm(Borang14Form $form): array
    {
        $map = [];

        foreach (($form->structure['rows'] ?? []) as $r) {
            $pusat = trim((string) ($r['pusat'] ?? ''));
            $dm = trim((string) ($r['dm'] ?? ''));
            if ($pusat !== '' && $dm !== '') {
                $map[$pusat] = $dm;
            }
        }

        if ($map === []) {
            $ref = $this->reference($form);
            foreach (($ref['daerah_mengundi'] ?? []) as $dm) {
                foreach (($dm['pusat_mengundi'] ?? []) as $pm) {
                    $map[(string) $pm['nama']] = (string) $dm['nama'];
                }
            }
        }

        // Finding 4: kerusi baharu yang structure-nya diwarisi di Keyin (tiada
        // rujukan kurasi/DPT DAN tiada structure sendiri) mesti diselesaikan
        // dengan cara SAMA seperti Borang14Controller::resolveReference() —
        // structure pilihan raya LAIN yang paling baru bagi kerusi yang sama
        // (utamakan 'published'), bukan gagal dengan "Struktur saluran tiada"
        // sedangkan skrin Keyin sendiri berjaya memaparkan grid.
        if ($map === []) {
            foreach ($this->inheritedStructureRows($form) as $r) {
                $pusat = trim((string) ($r['pusat'] ?? ''));
                $dm = trim((string) ($r['dm'] ?? ''));
                if ($pusat !== '' && $dm !== '') {
                    $map[$pusat] = $dm;
                }
            }
        }

        if ($map === []) {
            throw new RuntimeException('Struktur saluran tiada untuk Borang 14 ini.');
        }

        return $map;
    }

    /**
     * @return array<int, array> baris structure daripada pilihan raya LAIN
     *                            bagi kerusi (kawasan_type, kawasan_id) yang
     *                            sama — undi TIDAK PERNAH diwarisi, hanya
     *                            pokok Pusat Mengundi/Daerah Mengundi.
     */
    private function inheritedStructureRows(Borang14Form $form): array
    {
        $sourceQuery = Borang14Form::forKawasan($form->kawasan_type, (int) $form->kawasan_id)
            ->whereNotNull('structure')
            ->where('id', '!=', $form->id);

        $source = (clone $sourceQuery)->published()
                ->orderByDesc('tahun')->orderByDesc('created_at')->first()
            ?? $sourceQuery->orderByDesc('tahun')->orderByDesc('created_at')->first();

        return $source?->structure['rows'] ?? [];
    }

    /** Rujukan Borang14Reference kerusi ini (Parlimen atau DUN), jika ada. */
    private function reference(Borang14Form $form): ?array
    {
        return $form->kawasan_type === Borang14Form::KAWASAN_PARLIMEN
            ? Borang14Reference::forBandar((int) $form->kawasan_id)
            : Borang14Reference::forKadun((int) $form->kawasan_id);
    }

    /**
     * Rujukan boleh dipakai sebagai FAKTA (bukan anggaran) untuk pemilih
     * berdaftar — Finding 3: anggaran DPT (`source === 'dpt_estimate'`)
     * ialah penghampiran (docblock Borang14Reference sendiri kata pemanggil
     * mesti disclaim ia), jadi mapper ini tidak menghantarnya kepada AI
     * sebagai fakta — ia jatuh balik kepada kepala scoresheet, atau null.
     */
    private function factualReference(Borang14Form $form): ?array
    {
        $ref = $this->reference($form);

        return ($ref && ($ref['source'] ?? null) !== 'dpt_estimate') ? $ref : null;
    }

    /** @return array<string, int> nama DM => jumlah berdaftar (hanya bila diketahui, dan bukan anggaran DPT) */
    private function berdaftarPerDm(Borang14Form $form): array
    {
        $ref = $this->factualReference($form);

        $out = [];
        foreach (($ref['daerah_mengundi'] ?? []) as $dm) {
            $jumlah = 0;
            $ada = false;
            foreach (($dm['pusat_mengundi'] ?? []) as $pm) {
                foreach (($pm['saluran'] ?? []) as $s) {
                    if (isset($s['berdaftar']) && $s['berdaftar'] !== null) {
                        $jumlah += (int) $s['berdaftar'];
                        $ada = true;
                    }
                }
            }
            if ($ada) {
                $out[(string) $dm['nama']] = $jumlah;
            }
        }

        return $out;
    }

    /**
     * Jumlah pemilih berdaftar KESELURUHAN kerusi — Finding 1 (CRITICAL).
     *
     * DIHITUNG SECARA BEBAS daripada Borang14Reference, BUKAN dengan
     * menjumlahkan pemilih setiap baris hasil map(): baris UNDI POS/UNDI AWAL
     * sengaja tiada berdaftar sendiri (ia bukan satu Daerah Mengundi), jadi
     * pendekatan lama "jumlah semua baris, TAPI hanya jika SETIAP baris
     * diketahui" sentiasa gagal (UNDI POS mendiskualifikasi jumlah itu untuk
     * hampir setiap borang), dan borang yang dikeyin tangan (saveParties()/
     * putVote() tidak pernah menulis `structure`) jatuh terus kepada null —
     * yang kemudiannya disalah anggap 0 oleh ElectionComparisonService.
     *
     * Keutamaan: rujukan SPR/DPT SEBENAR (bukan anggaran, lihat
     * factualReference()) -> kepala scoresheet (`structure.jumlah_pemilih`,
     * ditulis oleh upload) -> null. TIDAK PERNAH reka angka.
     */
    private function totalPemilih(Borang14Form $form): ?int
    {
        $ref = $this->factualReference($form);
        if ($ref) {
            $jumlah = 0;
            $ada = false;
            foreach (($ref['daerah_mengundi'] ?? []) as $dm) {
                foreach (($dm['pusat_mengundi'] ?? []) as $pm) {
                    foreach (($pm['saluran'] ?? []) as $s) {
                        if (isset($s['berdaftar']) && $s['berdaftar'] !== null) {
                            $jumlah += (int) $s['berdaftar'];
                            $ada = true;
                        }
                    }
                }
            }
            foreach (['undi_awal', 'undi_pos'] as $key) {
                if (isset($ref[$key]['berdaftar']) && $ref[$key]['berdaftar'] !== null) {
                    $jumlah += (int) $ref[$key]['berdaftar'];
                    $ada = true;
                }
            }
            if ($ada) {
                return $jumlah;
            }
        }

        return isset($form->structure['jumlah_pemilih']) ? (int) $form->structure['jumlah_pemilih'] : null;
    }

    private function totals(array $rows, Borang14Form $form, array $parties): array
    {
        $undi = array_fill_keys(array_values($parties), 0);
        $ditolak = 0;

        foreach ($rows as $r) {
            foreach ($r['undi'] as $nama => $n) {
                $undi[$nama] += $n;
            }
            $ditolak += $r['ditolak'];
        }

        return [
            'pemilih' => $this->totalPemilih($form),
            'keluar'  => array_sum($undi) + $ditolak,
            'ditolak' => $ditolak,
            'undi'    => $undi,
            'parties' => array_values($parties),
        ];
    }
}
