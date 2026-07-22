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
