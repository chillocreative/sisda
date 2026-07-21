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
     * LABEL SALURAN/UNDI POS DIKEKALKAN, TIDAK DIKANONKAN SEMULA. Undi
     * disimpan berkunci pada rentetan saluran yang TEPAT seperti dibaca
     * daripada scoresheet — sheet sebenar mengandungi saluran kosong
     * (rujuk Borang14Controller.php:1178-1185) dan label undi pos yang bukan
     * literal 'UNDI POS'. Menomborkan semula '1'..'N' atau menulis semula
     * literal berkanun di sini akan menghanyutkan kunci itu, dan
     * survivingKeys() kemudian memadam undi berkenaan sebagai yatim.
     * Nombor hanya diberi kepada saluran yang BAHARU ditambah.
     *
     * @param  array<int,array{row_id:string,dm:string,pusat:string,saluran_count:int,saluran_labels?:array<int,string>}>  $pusatList
     * @return array{origin:string,calon:array,rows:array<int,array<string,string>>}
     */
    public function expand(
        array $pusatList,
        bool $undiAwal,
        bool $undiPos,
        ?string $undiAwalLabel = null,
        ?string $undiPosLabel = null,
        array $lainLain = [],
    ): array {
        $rows = [];

        foreach ($pusatList as $p) {
            $count = max(1, (int) ($p['saluran_count'] ?? 1));
            $labels = array_values($p['saluran_labels'] ?? []);
            for ($i = 1; $i <= $count; $i++) {
                $rows[] = [
                    'row_id'  => (string) $p['row_id'],
                    'dm'      => (string) ($p['dm'] ?? ''),
                    'pusat'   => (string) $p['pusat'],
                    // Label asal jika saluran ini sudah wujud; nombor hanya
                    // untuk yang baharu ditambah di hujung.
                    'saluran' => array_key_exists($i - 1, $labels)
                        ? (string) $labels[$i - 1]
                        : (string) $i,
                ];
            }
        }

        // Baris pusat-kosong, bentuk yang sama seperti keluaran scoresheet —
        // itulah yang referenceFromStructure() sudah tahu baca. Label yang
        // dikekalkan menang ke atas literal berkanun (lihat docblock).
        $awalSaluran = $undiAwalLabel ?? 'UNDI AWAL';
        $posSaluran = $undiPosLabel ?? 'UNDI POS';

        if ($undiAwal) {
            $rows[] = ['row_id' => 'pm_awal', 'dm' => '', 'pusat' => '', 'saluran' => $awalSaluran];
        }
        // Satu sheet boleh menggabungkan kedua-duanya dalam SATU baris
        // ("UNDI POS AWAL"), jadi collapse() menyalakan kedua-dua bendera
        // dengan label yang SAMA. Memancarkan dua baris di sini akan
        // menggandakan kunci undi yang sama; satu baris sahaja yang betul.
        if ($undiPos && ! ($undiAwal && $posSaluran === $awalSaluran)) {
            $rows[] = ['row_id' => 'pm_pos', 'dm' => '', 'pusat' => '', 'saluran' => $posSaluran];
        }

        // Baris pusat-kosong yang panel TIDAK boleh wakili (UNDI TIDAK
        // DIKEMBALIKAN, undi pos yang dipecah dua, apa sahaja yang dibaca AI).
        // Dibawa melalui suntingan tanpa disentuh: menggugurkannya di sini
        // menjadikan undi di bawahnya yatim, dan pengguna tiada cara untuk
        // menyelamatkannya kerana ia langsung tidak muncul dalam UI.
        foreach ($lainLain as $r) {
            $rows[] = [
                'row_id'  => (string) ($r['row_id'] ?? 'pm_lain'),
                'dm'      => (string) ($r['dm'] ?? ''),
                'pusat'   => '',
                'saluran' => (string) ($r['saluran'] ?? ''),
            ];
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
        $undiAwalLabel = null;
        $undiPosLabel = null;
        $lainLain = [];

        foreach ($structure['rows'] ?? [] as $r) {
            $nama = (string) ($r['pusat'] ?? '');

            if ($nama === '') {
                $label = (string) ($r['saluran'] ?? '');
                $upper = strtoupper($label);
                // Label MENTAH disimpan, bukan sekadar bendera: undi dikunci
                // padanya dan expand() mesti boleh memancarkannya semula
                // tepat-tepat. Yang PERTAMA sepadan menang — satu sheet hanya
                // ada satu baris undi awal / undi pos.
                $diwakili = false;
                if (str_contains($upper, 'AWAL') && $undiAwalLabel === null) {
                    $undiAwal = true;
                    $undiAwalLabel = $label;
                    $diwakili = true;
                }
                if (str_contains($upper, 'POS') && $undiPosLabel === null) {
                    $undiPos = true;
                    $undiPosLabel = $label;
                    $diwakili = true;
                }
                // Baris yang tiada kotak untuk mewakilinya — termasuk baris
                // KEDUA daripada keluarga yang sama — disimpan mentah supaya
                // expand() boleh memancarkannya semula. Lihat expand().
                if (! $diwakili) {
                    $lainLain[] = ['row_id' => (string) ($r['row_id'] ?? ''), 'dm' => (string) ($r['dm'] ?? ''), 'saluran' => $label];
                }

                continue;
            }

            $dm = (string) ($r['dm'] ?? '');
            $rowId = (string) ($r['row_id'] ?? '') ?: 'pm_'.md5($dm.'|'.$nama);

            if (! isset($pusat[$rowId])) {
                $pusat[$rowId] = [
                    'row_id' => $rowId, 'dm' => $dm, 'pusat' => $nama,
                    'saluran_count' => 0, 'saluran_labels' => [],
                ];
            }
            $pusat[$rowId]['saluran_count']++;
            $pusat[$rowId]['saluran_labels'][] = (string) ($r['saluran'] ?? '');
        }

        return [
            'pusat' => array_values($pusat),
            'undi_awal' => $undiAwal,
            'undi_pos' => $undiPos,
            'undi_awal_label' => $undiAwalLabel,
            'undi_pos_label' => $undiPosLabel,
            'lain_lain' => $lainLain,
        ];
    }

    /**
     * Rujukan yang DIPAPARKAN → entri per-Pusat untuk panel penyuntingan.
     *
     * Grid tidak selalunya dibina daripada `structure` borang ini: ia boleh
     * datang daripada JSON kurasi, anggaran DPT, atau struktur yang diwarisi
     * daripada pilihan raya lain. Bentuknya daerah_mengundi[] →
     * pusat_mengundi[] → saluran[], bukan rows[]. Tanpa penukaran ini panel
     * dibuka KOSONG di atas grid yang penuh undi, dan menyimpan ketika itu
     * memadam setiap undi yang tidak ditaip semula.
     *
     * row_id diterbitkan dengan peraturan yang SAMA seperti collapse() supaya
     * suntingan seterusnya mengenali pusat yang sama dan renameMap() dapat
     * membezakan "namakan semula" daripada "buang lalu tambah".
     *
     * @return array{pusat:array<int,array<string,mixed>>,undi_awal:bool,undi_pos:bool,undi_awal_label:null,undi_pos_label:null,lain_lain:array}
     */
    public function collapseReference(?array $reference): array
    {
        $pusat = [];
        $dilihat = [];

        foreach ($reference['daerah_mengundi'] ?? [] as $dm) {
            $namaDm = (string) ($dm['nama'] ?? '');
            foreach ($dm['pusat_mengundi'] ?? [] as $pm) {
                $nama = (string) ($pm['nama'] ?? '');
                if ($nama === '') {
                    continue;
                }
                $labels = collect($pm['saluran'] ?? [])
                    ->map(fn ($s) => (string) ($s['no'] ?? ''))->all();

                // Kunci undi ialah pusat|saluran — TIADA komponen DM — jadi
                // dua Pusat yang berkongsi nama di bawah DM berbeza
                // sememangnya SEL YANG SAMA. Anggaran DPT menghasilkannya
                // secara rutin: lokaliti kosong menjadi satu baris
                // 'TIADA LOKALITI' bagi setiap Daerah Mengundi. Membawanya
                // ke panel sebagai entri berasingan menghasilkan muatan yang
                // ditolak oleh endpoint simpan sendiri (nama mesti unik) —
                // panel menyerahkan keadaan tidak sah kepada pengguna lalu
                // menyalahkannya. Digabungkan di sini supaya panel
                // menggambarkan ruang kunci yang sebenar.
                $kunci = mb_strtoupper(trim($nama));
                if (isset($dilihat[$kunci])) {
                    continue;
                }
                $dilihat[$kunci] = true;

                $pusat[] = [
                    'row_id' => 'pm_'.md5($namaDm.'|'.$nama),
                    'dm' => $namaDm,
                    'pusat' => $nama,
                    'saluran_count' => max(1, count($labels)),
                    'saluran_labels' => $labels,
                ];
            }
        }

        // Baris undi awal/pos MESTI dibawa masuk. Anggaran DPT sentiasa
        // memancarkan kedua-duanya (Borang14Reference::deriveFromDpt()), dan
        // struktur warisan membawa LABEL MENTAHNYA
        // (referenceFromStructure()) — grid memaparkan baris itu dan undi
        // dikunci padanya. Membuka panel dengan kotak tidak bertanda
        // bermakna expand() tidak memancarkan baris itu dan simpanan
        // memadamnya; menggugurkan labelnya pula bermakna menandakan kotak
        // itu memancarkan literal berkanun, kunci hanyut, dan undi tetap
        // dipadam. Rujukan DPT tiada label — literal berkanun memang betul
        // di situ, kerana itulah yang grid sendiri guna.
        $awal = $reference['undi_awal'] ?? null;
        $pos = $reference['undi_pos'] ?? null;

        return [
            'pusat' => $pusat,
            'undi_awal' => $awal !== null,
            'undi_pos' => $pos !== null,
            'undi_awal_label' => $awal['label'] ?? null,
            'undi_pos_label' => $pos['label'] ?? null,
            'lain_lain' => [],
        ];
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
