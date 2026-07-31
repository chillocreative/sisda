<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Support\Borang14Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jumlah pengundi berdaftar pada Scoreboard.
 *
 * Borang14Reference memulangkan DUA bentuk berbeza:
 *   - Fail JSON terkurasi  : daerah_mengundi[].jumlah_berdaftar WUJUD
 *   - Terbitan DPT         : TIADA jumlah_berdaftar; kiraan berada pada
 *                            daerah_mengundi[].pusat_mengundi[].saluran[].berdaftar
 *
 * Hanya SATU DUN mempunyai fail terkurasi, jadi hampir setiap kerusi menggunakan
 * bentuk terbitan DPT. Kod lama menjumlahkan bentuk pertama sahaja dengan `?? 0`,
 * menyebabkan total_berdaftar menjadi 0 secara senyap dan papan memaparkan
 * "% Keluar Mengundi: 0.0%" — angka yang direka. Peraturan projek: nilai tidak
 * diketahui MESTI null, bukan sifar.
 */
class ScoreboardBerdaftarTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $bandar->id]);
    }

    /** Setiap baris pengundi hidup menyumbang 1 kepada berdaftar Pusat Mengundinya. */
    private function pengundi(string $daerahMengundi, string $lokaliti, int $bilangan): void
    {
        static $n = 0;
        $rows = [];
        foreach (range(1, $bilangan) as $ignored) {
            $n++;
            $rows[] = [
                'no_ic' => str_pad((string) $n, 12, '5', STR_PAD_LEFT),
                'nama' => "PENGUNDI {$n}",
                'kadun' => 'PILAH',
                'daerah_mengundi' => $daerahMengundi,
                'lokaliti' => $lokaliti,
                'is_deceased' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('pangkalan_data_pengundi')->insert($rows);
    }

    private function form(): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2026,
            'penjuru' => 2,
            'status' => 'published',
            'parties' => [['nama' => 'KEADILAN'], ['nama' => 'BERSATU']],
        ]);
    }

    /* ------------- unit: kedua-dua bentuk rujukan, dan bentuk kosong ------------- */

    public function test_helper_reads_the_curated_json_shape(): void
    {
        $reference = [
            'daerah_mengundi' => [
                ['nama' => 'DM SATU', 'jumlah_berdaftar' => 120],
                ['nama' => 'DM DUA', 'jumlah_berdaftar' => 80],
            ],
            'undi_awal' => ['berdaftar' => 15],
            'undi_pos' => ['berdaftar' => 5],
        ];

        $this->assertSame(220, Borang14Reference::jumlahBerdaftar($reference));
    }

    public function test_helper_reads_the_dpt_derived_shape(): void
    {
        // Bentuk ini TIADA jumlah_berdaftar — punca pepijat asal.
        $reference = [
            'daerah_mengundi' => [
                ['nama' => 'DM SATU', 'pusat_mengundi' => [
                    ['nama' => 'LOKALITI A', 'saluran' => [['no' => 1, 'berdaftar' => 3]]],
                    ['nama' => 'LOKALITI B', 'saluran' => [['no' => 1, 'berdaftar' => 2]]],
                ]],
                ['nama' => 'DM DUA', 'pusat_mengundi' => [
                    ['nama' => 'LOKALITI C', 'saluran' => [['no' => 1, 'berdaftar' => 4]]],
                ]],
            ],
            'undi_awal' => ['berdaftar' => 0],
            'undi_pos' => ['berdaftar' => 0],
        ];

        $this->assertSame(9, Borang14Reference::jumlahBerdaftar($reference));
    }

    public function test_helper_returns_null_not_zero_when_no_figures_exist(): void
    {
        $reference = [
            'daerah_mengundi' => [['nama' => 'DM SATU', 'pusat_mengundi' => []]],
            'undi_awal' => [],
            'undi_pos' => [],
        ];

        $hasil = Borang14Reference::jumlahBerdaftar($reference);

        $this->assertNull($hasil, 'Tidak diketahui mesti null.');
        $this->assertNotSame(0, $hasil, 'Tidak diketahui BUKAN sifar.');
    }

    public function test_helper_counts_a_genuine_zero_as_zero(): void
    {
        // Kerusi yang benar-benar melaporkan sifar berdaftar BUKAN "tidak diketahui".
        $reference = [
            'daerah_mengundi' => [['nama' => 'DM SATU', 'jumlah_berdaftar' => 0]],
            'undi_awal' => [],
            'undi_pos' => [],
        ];

        $this->assertSame(0, Borang14Reference::jumlahBerdaftar($reference));
    }

    /* ---------------- integrasi: melalui hujung data awam ---------------- */

    /**
     * Papan markah tersiar bagi DUN ini, membaca undi daripada $form.
     *
     * Nota: hujung awam kini dikunci pada KOD kerusi (/scoreboard/n27/data).
     * URL angka lama (/scoreboard/{id}/data) sudah tiada — segmen angka kini
     * hanya lencongan 301 — jadi ujian ini memandu laluan sebenar yang dilihat
     * orang awam, bukan laluan yang telah dibuang.
     */
    private function papanTersiar(Borang14Form $form): void
    {
        Scoreboard::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $form->id,
            'title' => 'PILAH',
            'status' => Scoreboard::STATUS_TERSIAR,
            'kod' => 'N27',
        ]);
    }

    public function test_public_payload_reports_the_real_registered_total(): void
    {
        $this->pengundi('DM SATU', 'LOKALITI A', 3);
        $this->pengundi('DM SATU', 'LOKALITI B', 2);
        $this->pengundi('DM DUA', 'LOKALITI C', 4);
        $this->papanTersiar($this->form());

        $payload = $this->getJson('/scoreboard/n27/data')->assertOk()->json();

        // 3 + 2 + 4 = 9. Kod lama memulangkan 0.
        $this->assertSame(9, $payload['total_berdaftar']);
    }

    public function test_deceased_voters_are_not_counted(): void
    {
        $this->pengundi('DM SATU', 'LOKALITI A', 3);
        DB::table('pangkalan_data_pengundi')->limit(1)->update(['is_deceased' => true]);
        $this->papanTersiar($this->form());

        $payload = $this->getJson('/scoreboard/n27/data')->assertOk()->json();

        $this->assertSame(2, $payload['total_berdaftar']);
    }
}
