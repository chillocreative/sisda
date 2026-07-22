<?php
// tests/Unit/ElectionResultScenarioMapperTest.php
//
// Kontrak yang dikunci di sini:
//   1. Bentuk keluaran SAMA seperti Borang14ScenarioMapper — semua kod hiliran
//      (scenarioSummary, deltas, carta, PDF) membacanya tanpa cabang khas.
//   2. Angka yang TIDAK DIKETAHUI kekal null. `pemilih` sifar akan menerbitkan
//      dakwaan "-100%" palsu — itu pepijat produksi sebenar yang sudah pernah
//      berlaku dalam laluan Analisa ini.
//   3. Keputusan tanpa pecahan calon MELEMPAR, bukan memulangkan senario kosong.
//      Senario dengan `undi` kosong menghasilkan peratusan yang direka.
namespace Tests\Unit;

use App\Models\ElectionSeatResult;
use App\Services\Pilihanraya\ElectionResultScenarioMapper;
use Tests\TestCase;

class ElectionResultScenarioMapperTest extends TestCase
{
    private ElectionResultScenarioMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ElectionResultScenarioMapper;
    }

    /** Bentuk ballot sebenar daripada /v1/results. */
    private function keputusan(array $over = []): ElectionSeatResult
    {
        return new ElectionSeatResult(array_merge([
            'election_name' => 'GE-15',
            'tarikh' => '2022-11-19',
            'ballot' => [
                ['name' => 'Rushdan bin Rusmi', 'party' => 'PAS', 'votes' => 26914, 'votes_perc' => 59.4],
                ['name' => 'Zahidi Zainul', 'party' => 'BN', 'votes' => 14400, 'votes_perc' => 31.8],
            ],
            'voters_total' => 60189,
            'voter_turnout' => 46059,
            'votes_rejected' => 745,
        ], $over));
    }

    public function test_ballot_becomes_a_party_vote_map(): void
    {
        $out = $this->mapper->map($this->keputusan());

        $this->assertSame(['PAS' => 26914, 'BN' => 14400], $out['totals']['undi']);
        $this->assertSame(['PAS', 'BN'], $out['totals']['parties']);
    }

    public function test_official_figures_are_copied_not_recomputed(): void
    {
        $out = $this->mapper->map($this->keputusan());

        // Keluar mengundi RASMI diguna apa adanya — bukan jumlah undi parti,
        // yang akan tersilap kerana ballot mungkin tidak menyenaraikan setiap
        // calon kecil.
        $this->assertSame(46059, $out['totals']['keluar']);
        $this->assertSame(745, $out['totals']['ditolak']);
        $this->assertSame(60189, $out['totals']['pemilih']);
    }

    public function test_an_unknown_registered_voter_count_stays_null(): void
    {
        // Data rasmi BIASANYA ada angka ini — itulah kelebihannya berbanding
        // scoresheet Borang 14. Tetapi apabila tiada, ia mesti kekal null.
        // Sifar di sini menjadi "penurunan 100%" dalam deltas().
        $out = $this->mapper->map($this->keputusan(['voters_total' => null]));

        $this->assertNull($out['totals']['pemilih']);
        $this->assertNull($out['rows'][0]['pemilih']);
    }

    public function test_an_unknown_turnout_is_not_recomputed_from_party_votes(): void
    {
        // Mengira semula daripada undi parti akan TERKURANG: ballot mungkin
        // tidak menyenaraikan setiap calon kecil, jadi jumlah kita sendiri
        // lebih rendah daripada angka SPR. Angka yang terkurang itu kemudian
        // menjadi PENYEBUT bagi setiap peratus undi — setiap syer parti
        // dilambungkan, dan dipaparkan sebagai angka rasmi.
        //
        // scenarioSummary() sudah pun ada sandarannya sendiri untuk keluar
        // yang tiada, jadi null di sini dikendalikan hiliran tanpa berpura-pura
        // ia angka rasmi.
        $out = $this->mapper->map($this->keputusan(['voter_turnout' => null]));

        $this->assertNull($out['totals']['keluar']);
        $this->assertNull($out['rows'][0]['keluar']);
    }

    public function test_an_unknown_rejected_count_stays_null_not_zero(): void
    {
        $out = $this->mapper->map($this->keputusan(['votes_rejected' => null]));

        $this->assertNull($out['totals']['ditolak']);
    }

    public function test_official_data_is_seat_level_so_exactly_one_row(): void
    {
        // Tiada pecahan Daerah Mengundi dalam data rasmi. Satu baris aras
        // kerusi — jangan reka baris per-lokaliti.
        $out = $this->mapper->map($this->keputusan());

        $this->assertCount(1, $out['rows']);
        $this->assertSame(['PAS' => 26914, 'BN' => 14400], $out['rows'][0]['undi']);
    }

    public function test_a_result_without_a_candidate_breakdown_is_still_usable(): void
    {
        // ~3,200 keputusan lama menyimpan ringkasan pemenang sahaja (sync hanya
        // mengambil ballot bagi keputusan lengkap TERKINI). Ia TETAP berguna
        // untuk perbandingan: keluar mengundi, pengundi berdaftar dan undi
        // ditolak semuanya diketahui.
        //
        // `parties` KOSONG ialah isyaratnya. Ia bukan "sifar undi" — ia
        // "pecahan tidak diketahui", dan hiliran mesti memaparkan "—" bukan 0%.
        $out = $this->mapper->map($this->keputusan(['ballot' => null]));

        $this->assertSame([], $out['totals']['undi']);
        $this->assertSame([], $out['totals']['parties']);

        // Angka yang MEMANG diketahui mesti bertahan.
        $this->assertSame(60189, $out['totals']['pemilih']);
        $this->assertSame(46059, $out['totals']['keluar']);
        $this->assertSame(745, $out['totals']['ditolak']);
    }

    public function test_a_ballot_entry_without_a_party_is_skipped_not_guessed(): void
    {
        $out = $this->mapper->map($this->keputusan(['ballot' => [
            ['name' => 'Rushdan bin Rusmi', 'party' => 'PAS', 'votes' => 26914],
            ['name' => 'Calon Bebas', 'party' => '', 'votes' => 300],
        ]]));

        $this->assertSame(['PAS' => 26914], $out['totals']['undi']);
    }
}
