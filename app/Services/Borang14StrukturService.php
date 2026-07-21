<?php

namespace App\Services;

/**
 * Logik BENTUK bagi struktur Borang 14 yang dibina dengan tangan.
 *
 * UI berfikir "satu entri per Pusat Mengundi + kiraan saluran"; storan
 * berfikir "satu baris per saluran" (bentuk yang dihasilkan scoresheet dan
 * yang sudah dibaca oleh referenceFromStructure()). Kelas ini satu-satunya
 * tempat kedua-dua pandangan itu bertemu.
 *
 * Tiada DB, tiada HTTP, tiada kebergantungan — supaya keputusan "undi mana
 * berpindah, undi mana dipadam" boleh diuji secara langsung.
 */
class Borang14StrukturService
{
    /**
     * Entri per-Pusat → struktur penuh (satu baris per saluran).
     *
     * Baris yang dihasilkan membawa BENTUK sahaja: row_id, dm, pusat, saluran.
     * Tiada 'a', tiada 'undi', tiada 'jumlah_undian' — tiada sheet bercetak
     * wujud untuk dibaca, dan menulis 0 di situ akan mencipta angka palsu yang
     * kemudian dituduh oleh crosscheck.
     *
     * @param  array<int,array{row_id:string,dm:string,pusat:string,saluran_count:int}>  $pusatList
     * @return array{origin:string,calon:array,rows:array<int,array<string,string>>}
     */
    public function expand(array $pusatList, bool $undiAwal, bool $undiPos): array
    {
        $rows = [];

        foreach ($pusatList as $p) {
            $count = max(1, (int) ($p['saluran_count'] ?? 1));
            for ($i = 1; $i <= $count; $i++) {
                $rows[] = [
                    'row_id'  => (string) $p['row_id'],
                    'dm'      => (string) ($p['dm'] ?? ''),
                    'pusat'   => (string) $p['pusat'],
                    'saluran' => (string) $i,
                ];
            }
        }

        // Baris pusat-kosong, bentuk yang sama seperti keluaran scoresheet —
        // itulah yang referenceFromStructure() sudah tahu baca.
        if ($undiAwal) {
            $rows[] = ['row_id' => 'pm_awal', 'dm' => '', 'pusat' => '', 'saluran' => 'UNDI AWAL'];
        }
        if ($undiPos) {
            $rows[] = ['row_id' => 'pm_pos', 'dm' => '', 'pusat' => '', 'saluran' => 'UNDI POS'];
        }

        return ['origin' => 'manual', 'calon' => [], 'rows' => $rows];
    }

    /**
     * Struktur tersimpan → entri per-Pusat untuk panel penyuntingan.
     *
     * Berfungsi untuk struktur manual DAN struktur scoresheet/warisan; yang
     * kedua tiada row_id, jadi satu id TERBITAN yang stabil (md5 dm|pusat)
     * diberikan. Kestabilan itu penting: kalau id berubah setiap kali panel
     * dibuka, suntingan kedua akan melihat setiap pusat sebagai baharu dan
     * cascade akan memadam undi yang sepatutnya hanya berpindah.
     *
     * @return array{pusat:array<int,array<string,mixed>>,undi_awal:bool,undi_pos:bool}
     */
    public function collapse(?array $structure): array
    {
        $pusat = [];
        $undiAwal = false;
        $undiPos = false;

        foreach ($structure['rows'] ?? [] as $r) {
            $nama = (string) ($r['pusat'] ?? '');

            if ($nama === '') {
                $upper = strtoupper((string) ($r['saluran'] ?? ''));
                $undiAwal = $undiAwal || str_contains($upper, 'AWAL');
                $undiPos = $undiPos || str_contains($upper, 'POS');

                continue;
            }

            $dm = (string) ($r['dm'] ?? '');
            $rowId = (string) ($r['row_id'] ?? '') ?: 'pm_'.md5($dm.'|'.$nama);

            if (! isset($pusat[$rowId])) {
                $pusat[$rowId] = ['row_id' => $rowId, 'dm' => $dm, 'pusat' => $nama, 'saluran_count' => 0];
            }
            $pusat[$rowId]['saluran_count']++;
        }

        return ['pusat' => array_values($pusat), 'undi_awal' => $undiAwal, 'undi_pos' => $undiPos];
    }

    /**
     * Nama lama → nama baharu, dipadan melalui row_id sahaja.
     *
     * Sengaja TIDAK meneka melalui persamaan nama: menamakan semula ialah
     * tepat perkara yang memutuskan persamaan itu.
     *
     * @param  array<int,array{row_id:string,pusat:string}>  $pusatList
     * @return array<string,string>
     */
    public function renameMap(?array $oldStructure, array $pusatList): array
    {
        $lama = [];
        foreach ($this->collapse($oldStructure)['pusat'] as $p) {
            $lama[$p['row_id']] = $p['pusat'];
        }

        $map = [];
        foreach ($pusatList as $p) {
            $id = (string) ($p['row_id'] ?? '');
            $baharu = (string) ($p['pusat'] ?? '');
            if (isset($lama[$id]) && $lama[$id] !== '' && $lama[$id] !== $baharu) {
                $map[$lama[$id]] = $baharu;
            }
        }

        return $map;
    }

    /**
     * Setiap kunci 'PUSAT|SALURAN' yang dikekalkan oleh struktur ini.
     * Undi di luar senarai ini ialah undi yang akan hilang.
     *
     * @return array<string,bool>
     */
    public function survivingKeys(array $structure): array
    {
        $keys = [];
        foreach ($structure['rows'] ?? [] as $r) {
            $keys[((string) ($r['pusat'] ?? '')).'|'.((string) ($r['saluran'] ?? ''))] = true;
        }

        return $keys;
    }
}
