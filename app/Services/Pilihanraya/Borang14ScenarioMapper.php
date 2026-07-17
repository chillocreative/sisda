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

    /** @return array{rows: array<int, array>, totals: array} */
    public function map(Borang14Form $form): array
    {
        $parties = $this->partyNames($form);
        $pusatToDm = $this->pusatToDm($form);
        $berdaftar = $this->berdaftarPerDm($form);

        // Kumpul: DM => ['undi' => [nama => n], 'ditolak' => n]
        $groups = [];
        foreach ($form->votes as $v) {
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

    /** @return array<int, string> slot => nama parti */
    private function partyNames(Borang14Form $form): array
    {
        $names = [];
        foreach (($form->parties ?? []) as $p) {
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
            $ref = $form->kawasan_type === Borang14Form::KAWASAN_PARLIMEN
                ? Borang14Reference::forBandar((int) $form->kawasan_id)
                : Borang14Reference::forKadun((int) $form->kawasan_id);

            foreach (($ref['daerah_mengundi'] ?? []) as $dm) {
                foreach (($dm['pusat_mengundi'] ?? []) as $pm) {
                    $map[(string) $pm['nama']] = (string) $dm['nama'];
                }
            }
        }

        if ($map === []) {
            throw new RuntimeException('Struktur saluran tiada untuk Borang 14 ini.');
        }

        return $map;
    }

    /** @return array<string, int> nama DM => jumlah berdaftar (hanya bila diketahui) */
    private function berdaftarPerDm(Borang14Form $form): array
    {
        $ref = $form->kawasan_type === Borang14Form::KAWASAN_PARLIMEN
            ? Borang14Reference::forBandar((int) $form->kawasan_id)
            : Borang14Reference::forKadun((int) $form->kawasan_id);

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

    private function totals(array $rows, Borang14Form $form, array $parties): array
    {
        $undi = array_fill_keys(array_values($parties), 0);
        $ditolak = 0;
        $pemilih = 0;
        $semuaPemilihDiketahui = true;

        foreach ($rows as $r) {
            foreach ($r['undi'] as $nama => $n) {
                $undi[$nama] += $n;
            }
            $ditolak += $r['ditolak'];
            if ($r['pemilih'] === null) {
                $semuaPemilihDiketahui = false;
            } else {
                $pemilih += $r['pemilih'];
            }
        }

        // Bila berdaftar per DM tiada, guna JUMLAH PEMILIH dari kepala scoresheet.
        // Angka itu benar; jangan reka apa-apa selain itu.
        $totalPemilih = $semuaPemilihDiketahui
            ? $pemilih
            : (isset($form->structure['jumlah_pemilih']) ? (int) $form->structure['jumlah_pemilih'] : null);

        return [
            'pemilih' => $totalPemilih,
            'keluar'  => array_sum($undi) + $ditolak,
            'ditolak' => $ditolak,
            'undi'    => $undi,
            'parties' => array_values($parties),
        ];
    }
}
