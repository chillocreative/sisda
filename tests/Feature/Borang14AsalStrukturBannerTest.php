<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ASAL STRUKTUR mesti SENTIASA dinyatakan — pada skrin DAN pada cetakan.
 *
 * Rujukan terbitan DPT datang dalam DUA rasa:
 *   - 'dpt_estimate' — anggaran (kumpul ikut Lokaliti, satu Saluran setiap
 *     Pusat Mengundi). Amaran KUNING.
 *   - 'dpt_sebenar'  — struktur sebenar daripada fail DPPR/DPI. Pengesahan
 *     bernada TENANG.
 *
 * Reka bentuk (§Antara Muka): "Jangan senyapkan kedua-duanya — pengendali
 * perlu tahu yang mana satu sedang dipaparkan." Sebuah kerusi 'dpt_sebenar'
 * yang SENYAP lebih buruk daripada amaran yang sentiasa ada, kerana ketiadaan
 * amaran jadi tidak bermakna: pengendali tidak dapat membezakan struktur
 * bergazet daripada anggaran.
 *
 * KONTRAK MERENTAS SEMPADAN, sama corak dengan Borang14CellKeyContractTest:
 * ujian `source` wujud DUA KALI — sekali dalam JSX (skrin) dan sekali dalam
 * Blade (PDF). Tiada pelari ujian JS dalam repo ini, jadi ujian ini memaku
 * kedua-dua belah supaya satu pihak tidak boleh berubah tanpa satu lagi.
 */
class Borang14AsalStrukturBannerTest extends TestCase
{
    private const JSX = 'resources/js/Pages/Pilihanraya/borang14/KeyinTab.jsx';

    private const BLADE = 'resources/views/pdf/borang14.blade.php';

    private function baca(string $relatif): string
    {
        $path = dirname(__DIR__, 2).'/'.$relatif;
        $this->assertFileExists($path, 'Fail hilang — '.$relatif);

        return (string) file_get_contents($path);
    }

    /**
     * Amaran anggaran hari ini mesti kekal HARFIAH sama. Ia sudah dilihat
     * pengendali pada setiap kerusi tanpa struktur sebenar; menukarnya semasa
     * menambah kes baharu akan menyembunyikan regresi dalam bunyi perubahan.
     */
    public function test_amaran_anggaran_kekal_tidak_berubah_pada_skrin_dan_pdf(): void
    {
        $jsx = $this->baca(self::JSX);
        $blade = $this->baca(self::BLADE);

        $this->assertStringContainsString("reference.source === 'dpt_estimate'", $jsx);
        $this->assertStringContainsString(
            'Pusat Mengundi &amp; Berdaftar dianggarkan daripada data DPT yang dimuat naik (dikumpul ikut Lokaliti, satu Saluran setiap Pusat Mengundi) — bukan pecahan Saluran rasmi gazet SPR.',
            $jsx,
        );

        $this->assertStringContainsString("(\$reference['source'] ?? null) === 'dpt_estimate'", $blade);
        $this->assertStringContainsString(
            'Pusat Mengundi &amp; Berdaftar dianggarkan daripada data DPT (ikut Lokaliti) &mdash; bukan pecahan Saluran rasmi gazet SPR.',
            $blade,
        );
    }

    public function test_struktur_sebenar_disahkan_pada_skrin(): void
    {
        $jsx = $this->baca(self::JSX);

        $this->assertStringContainsString(
            "reference.source === 'dpt_sebenar'",
            $jsx,
            'Skrin Keyin mesti menguji dpt_sebenar — jika tidak, kerusi berstruktur sebenar tidak memaparkan apa-apa asal.',
        );
        // Nada TENANG, bukan amaran kuning: ini berita baik.
        $this->assertStringContainsString('t.bannerOk', $jsx);
        foreach (['Daerah Mengundi', 'Pusat Mengundi', 'Saluran', 'DPPR/DPI', 'angka sebenar', 'bukan anggaran'] as $frasa) {
            $this->assertStringContainsString($frasa, $jsx, "Pengesahan skrin mesti menyebut '{$frasa}'.");
        }
    }

    public function test_token_banner_ok_wujud_dan_berbeza_daripada_amaran(): void
    {
        $tema = $this->baca('resources/js/Pages/Pilihanraya/theme.js');

        $this->assertStringContainsString('bannerOk:', $tema);
        $this->assertStringContainsString('bg-emerald-50', $tema, 'bannerOk mesti bernada tenang, bukan kuning amaran.');
        $this->assertStringContainsString('bg-amber-50', $tema, 'banner amaran kuning mesti kekal.');
    }

    /**
     * PDF ialah artifak bercetak yang hidup lebih lama daripada skrin. Baris
     * asal mesti dicetak, dan maknanya tidak boleh bergantung pada WARNA
     * sahaja — cetakan hitam-putih dan fotokopi lazim di pusat penjumlahan.
     */
    public function test_struktur_sebenar_dicetak_pada_pdf_tanpa_bergantung_pada_warna(): void
    {
        $blade = $this->baca(self::BLADE);

        $this->assertStringContainsString("(\$reference['source'] ?? null) === 'dpt_sebenar'", $blade);
        $this->assertStringContainsString('font-weight:bold', $blade, 'Saluran isyarat kedua selain warna diperlukan untuk cetakan hitam-putih.');

        $html = view('pdf.borang14', [
            'reference' => [
                'negeri' => 'Negeri Ujian', 'parlimen' => 'Parlimen Ujian', 'dun' => 'Dun Ujian',
                'daerah_mengundi' => [[
                    'nama' => 'DM Ujian',
                    'pusat_mengundi' => [[
                        'nama' => 'PM Ujian',
                        'saluran' => [['no' => 1, 'berdaftar' => 20]],
                    ]],
                ]],
                'undi_awal' => ['berdaftar' => 0], 'undi_pos' => ['berdaftar' => 0],
                'source' => 'dpt_sebenar',
            ],
            'penjuru' => 2, 'penjuruLabel' => '1 vs 1',
            'parties' => [['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B']],
            'votes' => [], 'contest' => 'dun', 'logo' => null, 'isBulohKasap' => false,
        ])->render();

        $teks = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));

        $this->assertStringContainsString('diambil terus daripada fail DPPR/DPI', $teks);
        $this->assertStringContainsString('angka sebenar, bukan anggaran', $teks);
        $this->assertStringNotContainsString('dianggarkan daripada data DPT', $teks, 'Kerusi dpt_sebenar tidak boleh mencetak amaran anggaran.');
    }

    public function test_pdf_kerusi_anggaran_masih_mencetak_amaran_kuning_yang_sama(): void
    {
        $html = view('pdf.borang14', [
            'reference' => [
                'negeri' => 'Negeri Ujian', 'parlimen' => 'Parlimen Ujian', 'dun' => 'Dun Ujian',
                'daerah_mengundi' => [[
                    'nama' => 'DM Ujian',
                    'pusat_mengundi' => [[
                        'nama' => 'PM Ujian',
                        'saluran' => [['no' => 1, 'berdaftar' => 20]],
                    ]],
                ]],
                'undi_awal' => ['berdaftar' => 0], 'undi_pos' => ['berdaftar' => 0],
                'source' => 'dpt_estimate',
            ],
            'penjuru' => 2, 'penjuruLabel' => '1 vs 1',
            'parties' => [['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B']],
            'votes' => [], 'contest' => 'dun', 'logo' => null, 'isBulohKasap' => false,
        ])->render();

        $teks = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));

        $this->assertStringContainsString(
            'Pusat Mengundi & Berdaftar dianggarkan daripada data DPT (ikut Lokaliti) — bukan pecahan Saluran rasmi gazet SPR.',
            $teks,
        );
        $this->assertStringNotContainsString('DPPR/DPI', $teks, 'Kerusi anggaran tidak boleh mencetak pengesahan struktur sebenar.');
    }

    /**
     * Rujukan terkurasi/scoresheet/warisan tidak membawa 'source' terbitan DPT
     * dan mesti kekal seperti hari ini — tiada baris asal DPT langsung.
     */
    public function test_sumber_bukan_dpt_tidak_mencetak_mana_mana_baris_dpt(): void
    {
        $html = view('pdf.borang14', [
            'reference' => [
                'negeri' => 'Negeri Ujian', 'parlimen' => 'Parlimen Ujian', 'dun' => 'Dun Ujian',
                'daerah_mengundi' => [[
                    'nama' => 'DM Ujian',
                    'pusat_mengundi' => [[
                        'nama' => 'PM Ujian',
                        'saluran' => [['no' => 1, 'berdaftar' => 20]],
                    ]],
                ]],
                'undi_awal' => ['berdaftar' => 0], 'undi_pos' => ['berdaftar' => 0],
                'source' => 'scoresheet',
            ],
            'penjuru' => 2, 'penjuruLabel' => '1 vs 1',
            'parties' => [['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B']],
            'votes' => [], 'contest' => 'dun', 'logo' => null, 'isBulohKasap' => false,
        ])->render();

        $teks = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))));

        $this->assertStringNotContainsString('dianggarkan daripada data DPT', $teks);
        $this->assertStringNotContainsString('DPPR/DPI', $teks);
    }

    /**
     * Kedua-dua fail mesti menamakan satu sama lain dalam komen. Itulah satu-
     * satunya benang yang menghubungkan mereka merentas sempadan PHP/JSX —
     * repo ini sudah pernah digigit oleh penyimpangan jenis ini (cellKey).
     */
    public function test_kedua_dua_fail_menamakan_satu_sama_lain_dalam_komen(): void
    {
        $this->assertStringContainsString('borang14.blade.php', $this->baca(self::JSX));
        $this->assertStringContainsString('KeyinTab.jsx', $this->baca(self::BLADE));
    }
}
