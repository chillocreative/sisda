<?php
// tests/Feature/AnalisaRasmiScenarioTest.php
//
// Menambah keputusan rasmi SPR (electiondata.my) sebagai senario Analisa.
// Dua bahaya dikunci di sini:
//   1. Keputusan kerusi ORANG LAIN tidak boleh disisipkan ke dalam perbandingan
//      ini — naratif AI akan memperihalkannya sebagai kerusi ini.
//   2. Keputusan tanpa pecahan calon ditolak, bukan diterima sebagai senario
//      kosong yang menghasilkan peratusan direka.
namespace Tests\Feature;

use App\Models\AnalisaComparison;
use App\Models\Bandar;
use App\Models\ElectionSeat;
use App\Models\ElectionSeatResult;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaRasmiScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        // UserFactory tidak set telephone (NOT NULL) — pepijat sedia ada, luar skop.
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123456789']);
    }

    /** @return array{0: Bandar, 1: Kadun} */
    private function seedSeat(string $dunNama = 'JUASSEH'): array
    {
        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'KUALA PILAH', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => $dunNama, 'bandar_id' => $bandar->id]);

        return [$bandar, $kadun];
    }

    private function seat(Kadun $kadun, string $slug = 'n15-juasseh-negeri-sembilan'): ElectionSeat
    {
        return ElectionSeat::create([
            'slug' => $slug, 'nama' => $kadun->nama, 'kod' => 'N15',
            'negeri' => 'NEGERI SEMBILAN', 'jenis' => 'dun', 'kadun_id' => $kadun->id,
        ]);
    }

    private function keputusan(ElectionSeat $seat, array $over = []): ElectionSeatResult
    {
        return ElectionSeatResult::create(array_merge([
            'election_seat_id' => $seat->id,
            'election_name' => 'SE-15',
            'tarikh' => '2023-08-12',
            'party' => 'BN',
            'ballot' => [
                ['name' => 'Bibi Sharliza', 'party' => 'BN', 'votes' => 4549],
                ['name' => 'Eddin Syazlee', 'party' => 'PN', 'votes' => 4471],
            ],
            'voters_total' => 13408,
            'voter_turnout' => 9122,
            'votes_rejected' => 87,
        ], $over));
    }

    private function comparison(Bandar $bandar, Kadun $kadun): AnalisaComparison
    {
        return AnalisaComparison::create([
            'title' => 'Ujian', 'level' => 'dun',
            'bandar_id' => $bandar->id, 'kadun_id' => $kadun->id,
            'negeri' => 'NEGERI SEMBILAN', 'parlimen' => 'KUALA PILAH', 'dun' => $kadun->nama,
        ]);
    }

    public function test_lists_official_results_and_flags_which_can_be_used(): void
    {
        [$bandar, $kadun] = $this->seedSeat();
        $seat = $this->seat($kadun);
        $this->keputusan($seat);
        // Keputusan lama: ringkasan pemenang sahaja, tiada pecahan calon.
        $this->keputusan($seat, ['election_name' => 'SE-14', 'tarikh' => '2018-05-09', 'ballot' => null]);

        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.analisa.comparisons.rasmi', $c));

        $res->assertOk()->assertJsonCount(2, 'keputusan');
        // Disusun terkini dahulu, dan hanya yang berpecahan ditanda `sedia`.
        $this->assertTrue($res->json('keputusan.0.sedia'));
        $this->assertFalse($res->json('keputusan.1.sedia'));
    }

    public function test_creates_a_scenario_from_an_official_result(): void
    {
        [$bandar, $kadun] = $this->seedSeat();
        $result = $this->keputusan($this->seat($kadun));
        $c = $this->comparison($bandar, $kadun);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.rasmi', $c), ['result_id' => $result->id])
            ->assertOk();

        $senario = $c->fresh('scenarios')->scenarios->first();

        $this->assertSame('SE-15 (Rasmi)', $senario->label);
        $this->assertSame(['BN' => 4549, 'PN' => 4471], $senario->parsed_totals['undi']);
        // Angka rasmi disalin apa adanya, bukan dikira semula daripada undi.
        $this->assertSame(9122, $senario->parsed_totals['keluar']);
        // Pengundi berdaftar — yang scoresheet Borang 14 tidak pernah bawa.
        $this->assertSame(13408, $senario->parsed_totals['pemilih']);
    }

    public function test_a_result_from_another_seat_is_rejected(): void
    {
        [$bandar, $kadun] = $this->seedSeat();
        $this->seat($kadun);

        // Kerusi lain sepenuhnya, dengan keputusannya sendiri.
        $lain = Kadun::create(['nama' => 'LAIN', 'bandar_id' => $bandar->id]);
        $seatLain = $this->seat($lain, 'n16-lain-negeri-sembilan');
        $resultLain = $this->keputusan($seatLain);

        $c = $this->comparison($bandar, $kadun);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.rasmi', $c), ['result_id' => $resultLain->id])
            ->assertStatus(422);

        $this->assertSame(0, $c->fresh()->scenarios()->count());
    }

    public function test_a_result_without_a_candidate_breakdown_is_rejected(): void
    {
        [$bandar, $kadun] = $this->seedSeat();
        $result = $this->keputusan($this->seat($kadun), ['ballot' => null]);
        $c = $this->comparison($bandar, $kadun);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.rasmi', $c), ['result_id' => $result->id])
            ->assertStatus(422);

        $this->assertSame(0, $c->fresh()->scenarios()->count());
    }

    public function test_the_three_scenario_limit_is_enforced(): void
    {
        [$bandar, $kadun] = $this->seedSeat();
        $seat = $this->seat($kadun);
        $c = $this->comparison($bandar, $kadun);

        foreach ([1, 2, 3] as $i) {
            $c->scenarios()->create([
                'position' => $i, 'label' => "S{$i}", 'election_date' => '2020-01-01',
                'source_filename' => 'x', 'parsed_rows' => [], 'parsed_totals' => [], 'row_count' => 0,
            ]);
        }

        $result = $this->keputusan($seat);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.rasmi', $c), ['result_id' => $result->id])
            ->assertStatus(422);

        $this->assertSame(3, $c->fresh()->scenarios()->count());
    }

    public function test_the_ai_fact_payload_excludes_elections_that_have_not_happened(): void
    {
        // electiondata.my memulangkan pilihan raya AKAN DATANG sebagai stub:
        // party null, setiap angka null. Memberikannya kepada model naratif
        // sebagai "sejarah kerusi" — dan ia diisih PALING ATAS kerana tarikhnya
        // paling lewat — menjemput naratif tentang pilihan raya yang belum
        // berlaku. ElectionSeat::latestCompletedResult() sudah menapisnya;
        // laluan fakta mesti sama.
        [$bandar, $kadun] = $this->seedSeat();
        $seat = $this->seat($kadun);
        $this->keputusan($seat);
        $this->keputusan($seat, [
            'election_name' => 'SE-16', 'tarikh' => '2030-08-01', 'party' => null,
            'ballot' => null, 'voters_total' => null, 'voter_turnout' => null, 'votes_rejected' => null,
        ]);

        $c = $this->comparison($bandar, $kadun);
        // buildFactPayload() memanggil currentRoll(), yang khusus MySQL dan
        // tidak boleh berjalan pada SQLite CI — uji kaedah ini terus.
        $rasmi = app(\App\Services\Pilihanraya\ElectionComparisonService::class)->officialHistory($c);

        $this->assertCount(1, $rasmi);
        $this->assertSame('SE-15', $rasmi[0]['pilihanraya']);
    }

    public function test_the_fact_payload_keeps_unknown_official_figures_null(): void
    {
        [$bandar, $kadun] = $this->seedSeat();
        $seat = $this->seat($kadun);
        $this->keputusan($seat, ['voters_total' => null, 'voter_turnout' => null]);

        $c = $this->comparison($bandar, $kadun);
        $rasmi = app(\App\Services\Pilihanraya\ElectionComparisonService::class)->officialHistory($c);

        $this->assertNull($rasmi[0]['pengundi_berdaftar']);
        $this->assertNull($rasmi[0]['keluar_mengundi']);
    }

    public function test_official_history_works_at_parlimen_level_too(): void
    {
        // Kedua-dua endpoint dan officialHistory() bercabang mengikut `level`;
        // cabang Bandar tidak pernah diuji hujung ke hujung.
        [$bandar] = $this->seedSeat();
        $seat = ElectionSeat::create([
            'slug' => 'p129-kuala-pilah-negeri-sembilan', 'nama' => 'KUALA PILAH', 'kod' => 'P129',
            'negeri' => 'NEGERI SEMBILAN', 'jenis' => 'parlimen', 'bandar_id' => $bandar->id,
        ]);
        $this->keputusan($seat, ['election_name' => 'GE-15']);

        $c = AnalisaComparison::create([
            'title' => 'Ujian P', 'level' => 'parlimen',
            'bandar_id' => $bandar->id, 'kadun_id' => null,
            'negeri' => 'NEGERI SEMBILAN', 'parlimen' => 'KUALA PILAH', 'dun' => null,
        ]);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.analisa.comparisons.rasmi', $c));

        $res->assertOk()->assertJsonCount(1, 'keputusan');

        $rasmi = app(\App\Services\Pilihanraya\ElectionComparisonService::class)->officialHistory($c);
        $this->assertSame('GE-15', $rasmi[0]['pilihanraya']);
    }

    public function test_official_and_borang14_scenarios_share_one_shape(): void
    {
        // Kod hiliran (scenarioSummary, deltas, jadual, PDF) membaca kedua-dua
        // bentuk tanpa cabang khas. Dua bentuk yang hanyut bermakna naratif AI
        // memperihalkan angka berbeza daripada jadual di sebelahnya.
        [, $kadun] = $this->seedSeat();
        $mapped = (new \App\Services\Pilihanraya\ElectionResultScenarioMapper)
            ->map($this->keputusan($this->seat($kadun)));

        $this->assertSame(
            ['pemilih', 'keluar', 'ditolak', 'undi', 'parties'],
            array_keys($mapped['totals']),
        );
        $this->assertSame(
            ['kawasan', 'pemilih', 'keluar', 'ditolak', 'undi'],
            array_keys($mapped['rows'][0]),
        );
    }

    public function test_a_seat_that_was_never_synced_reports_a_reason_not_an_error(): void
    {
        [$bandar, $kadun] = $this->seedSeat();
        // Tiada ElectionSeat langsung — negeri belum disegerakkan.
        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.analisa.comparisons.rasmi', $c));

        $res->assertOk()->assertJsonCount(0, 'keputusan');
        $this->assertNotEmpty($res->json('sebab'));
    }
}
