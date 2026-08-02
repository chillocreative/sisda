<?php
// tests/Feature/Borang14PdfRingkasanTest.php
namespace Tests\Feature;

use Tests\TestCase;

/**
 * Muka 1 cetakan Borang 14 ialah RINGKASAN KEPUTUSAN.
 *
 * Ujian ini mengunci satu perkara di atas segalanya: setiap angka ringkasan
 * dijumlahkan daripada SEL UNDI yang sama seperti jadual pecahan saluran di
 * muka-muka berikutnya. Ringkasan yang dikira daripada sumber kedua boleh
 * bercanggah dengan pecahannya sendiri pada cetakan yang SATU, dan cetakan itu
 * hidup lebih lama daripada skrin.
 *
 * Ia turut mengunci peraturan "tidak diketahui BUKAN sifar": berdaftar yang
 * tiada mesti mencetak '—' dan bukan 0 — 0 di sana menghasilkan peratus keluar
 * mengundi yang direka.
 */
class Borang14PdfRingkasanTest extends TestCase
{
    /** Satu DUN, satu pusat, dua saluran; berdaftar diketahui. */
    private function reference(?int $berdaftarS1 = 100, ?int $berdaftarS2 = 100, ?int $awal = 10, ?int $pos = 10): array
    {
        return [
            'negeri' => 'Negeri Ujian', 'parlimen' => 'Parlimen Ujian', 'dun' => 'Dun Ujian',
            'daerah_mengundi' => [[
                'nama' => 'DM Ujian',
                'pusat_mengundi' => [[
                    'nama' => 'PM Ujian',
                    'saluran' => [
                        ['no' => 1, 'berdaftar' => $berdaftarS1],
                        ['no' => 2, 'berdaftar' => $berdaftarS2],
                    ],
                ]],
            ]],
            'undi_awal' => ['berdaftar' => $awal], 'undi_pos' => ['berdaftar' => $pos],
            'source' => 'dpt_sebenar',
        ];
    }

    private function render(array $reference, array $votes, array $parties, int $penjuru = 2, array $extra = []): string
    {
        return view('pdf.borang14', array_merge([
            'reference' => $reference, 'penjuru' => $penjuru, 'penjuruLabel' => '1 vs 1',
            'parties' => $parties, 'votes' => $votes, 'contest' => 'dun',
            'logo' => null, 'isBulohKasap' => false,
            'jenisPr' => 'PRN', 'tahun' => 2026, 'statusBorang' => 'published',
        ], $extra))->render();
    }

    /** Baris jadual mengikut class — ringkasan ('kedudukan'/'stat') atau pecahan ('saluran'). */
    private function rows(string $html, string $class, int $index = 0): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $table = $xpath->query('//table[contains(@class, "' . $class . '")]')->item($index);
        $this->assertNotNull($table, "Jadual .{$class} #{$index} tiada dalam cetakan.");

        $rows = [];
        foreach ($xpath->query('.//tr', $table) as $tr) {
            $cells = [];
            foreach ($xpath->query('./td|./th', $tr) as $cell) {
                $cells[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent));
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * Kedudukan calon disusun mengikut undi (bukan mengikut slot), pemenang
     * dinamakan bersama majoriti, dan Undi Awal & Undi Pos DIKIRA ke dalam
     * jumlah — bahagian yang paling mudah tertinggal.
     */
    public function test_ringkasan_menyusun_calon_mengikut_undi_dan_mengira_undi_awal_pos(): void
    {
        // Slot 1 (PARTI A) = 10+10 saluran + 0 awal + 0 pos  = 20
        // Slot 2 (PARTI B) = 30+30 saluran + 5 awal + 5 pos  = 70  -> pemenang
        // Majoriti = 70 - 20 = 50; undi sah = 90.
        $votes = [
            'dun|PM Ujian|1|1' => 10, 'dun|PM Ujian|1|2' => 30,
            'dun|PM Ujian|2|1' => 10, 'dun|PM Ujian|2|2' => 30,
            'dun||UNDI AWAL|2' => 5, 'dun||UNDI POS|2' => 5,
        ];

        $html = $this->render($this->reference(), $votes, [
            ['slot' => 1, 'nama' => 'PARTI A', 'calon' => 'Ali bin Abu'],
            ['slot' => 2, 'nama' => 'PARTI B', 'calon' => 'Siti binti Bakar'],
        ]);

        $rows = $this->rows($html, 'kedudukan');

        $this->assertSame(['#', 'Parti', 'Calon', 'Jumlah Undi', '% Undi Sah', 'Agihan'], $rows[0]);
        // Disusun menurun: PARTI B dahulu walaupun ia slot 2.
        $this->assertSame(['1', 'PARTI B', 'Siti binti Bakar', '70', '77.8%'], array_slice($rows[1], 0, 5));
        $this->assertSame(['2', 'PARTI A', 'Ali bin Abu', '20', '22.2%'], array_slice($rows[2], 0, 5));

        $this->assertStringContainsString('Siti binti Bakar (PARTI B)', $html);
        $this->assertStringContainsString('Majoriti', $html);
        $this->assertStringContainsString('50', $html);
    }

    /**
     * Statistik ringkasan mesti bersetuju dengan formula skrin:
     * keluar = undi sah + Ditolak (C) + Tak Dimasukkan (D), dan slot 90/91
     * TIDAK PERNAH masuk ke dalam jumlah mana-mana parti.
     */
    public function test_statistik_ringkasan_mengikut_formula_yang_sama_seperti_pecahan_saluran(): void
    {
        // Sah = 40+30 = 70. C = 2+1 = 3. D = 1+0 = 1. Keluar = 74.
        // Berdaftar = 100+100+10+10 = 220. % keluar = 74/220 = 33.6%.
        // Tak keluar = 146 -> 66.4%.
        $votes = [
            'dun|PM Ujian|1|1' => 40, 'dun|PM Ujian|1|90' => 2, 'dun|PM Ujian|1|91' => 1,
            'dun|PM Ujian|2|2' => 30, 'dun|PM Ujian|2|90' => 1,
        ];

        $html = $this->render($this->reference(), $votes, [
            ['slot' => 1, 'nama' => 'PARTI A'],
            ['slot' => 2, 'nama' => 'PARTI B'],
        ]);

        // Label dan nilai ialah dua <span> bersebelahan, jadi textContent
        // merapatkannya tanpa ruang — "Jumlah Undi Sah70".
        $teks = implode(' | ', array_merge(...$this->rows($html, 'stat')));

        $this->assertStringContainsString('Jumlah Undi Sah70', $teks);
        $this->assertStringContainsString('Undi Ditolak (C)3', $teks);
        $this->assertStringContainsString('Tak Dimasukkan (D)1', $teks);
        $this->assertStringContainsString('Jumlah Keluar Mengundi74', $teks);
        $this->assertStringContainsString('Pengundi Berdaftar220', $teks);
        $this->assertStringContainsString('% Keluar Mengundi33.6%', $teks);
        $this->assertStringContainsString('Tidak Keluar146', $teks);
        $this->assertStringContainsString('% Tidak Keluar66.4%', $teks);

        // Slot 90/91 tidak mencemari jumlah parti.
        $kedudukan = $this->rows($html, 'kedudukan');
        $this->assertSame('40', $kedudukan[1][3]);
        $this->assertSame('30', $kedudukan[2][3]);
    }

    /**
     * Berdaftar yang TIDAK DIKETAHUI (borang scoresheet sahaja / struktur
     * diwarisi) mesti mencetak '—' — bukan 0, dan bukan peratus yang dikira
     * daripada 0.
     */
    public function test_berdaftar_tidak_diketahui_dicetak_sengkang_bukan_sifar(): void
    {
        $votes = ['dun|PM Ujian|1|1' => 5, 'dun|PM Ujian|1|2' => 3];

        $html = $this->render(
            $this->reference(null, null, null, null),
            $votes,
            [['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B']],
        );

        $teks = implode(' | ', array_merge(...$this->rows($html, 'stat')));

        $this->assertStringContainsString('Pengundi Berdaftar—', $teks);
        $this->assertStringContainsString('% Keluar Mengundi—', $teks);
        $this->assertStringContainsString('Tidak Keluar—', $teks);
        $this->assertStringContainsString('% Tidak Keluar—', $teks);
        // Sifar palsu ialah kegagalan yang ditakuti — pastikan ia TIDAK dicetak.
        $this->assertStringNotContainsString('Pengundi Berdaftar0', $teks);
        $this->assertStringNotContainsString('% Keluar Mengundi0', $teks);
        // Undi sah kekal angka sebenar — hanya penyebut yang tidak diketahui.
        $this->assertStringContainsString('Jumlah Undi Sah8', $teks);
        $this->assertStringContainsString('tidak dapat dikira', $html);
    }

    /** Seri di tempat teratas: tiada pemenang diisytiharkan, tiada majoriti direka. */
    public function test_seri_di_tempat_teratas_tidak_mengisytiharkan_pemenang(): void
    {
        $votes = ['dun|PM Ujian|1|1' => 25, 'dun|PM Ujian|1|2' => 25];

        $html = $this->render($this->reference(), $votes, [
            ['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B'],
        ]);

        $this->assertStringContainsString('SERI di tempat teratas', $html);
        $this->assertStringNotContainsString('Undi Tertinggi', $html);
        $this->assertStringNotContainsString('Majoriti', $html);
    }

    /** Borang tanpa undi langsung: ringkasan berkata begitu, bukan mengisytiharkan pemenang 0 undi. */
    public function test_borang_tanpa_undi_tidak_mengisytiharkan_pemenang(): void
    {
        $html = $this->render($this->reference(), [], [
            ['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B'],
        ]);

        $this->assertStringContainsString('Belum ada undi direkodkan', $html);
        $this->assertStringNotContainsString('Undi Tertinggi', $html);
    }

    /** Ringkasan mesti berada SEBELUM pecahan saluran, dan dipisahkan muka sendiri. */
    public function test_ringkasan_berada_pada_muka_pertama(): void
    {
        $html = $this->render($this->reference(), ['dun|PM Ujian|1|1' => 5], [
            ['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B'],
        ]);

        $this->assertLessThan(
            strpos($html, 'Pusat Mengundi: PM Ujian'),
            strpos($html, 'RINGKASAN KEPUTUSAN'),
            'Ringkasan mesti dicetak sebelum blok pecahan saluran.',
        );
        $this->assertStringContainsString('page-break-after: always', $html);
    }
}
