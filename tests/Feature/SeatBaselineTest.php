<?php
// tests/Feature/SeatBaselineTest.php
//
// Garis dasar kerusi menggantikan angka bertulis-keras. Sebelum ini setiap
// kerusi di negara ini dikalibrasi dengan tally 2022 Buloh Kasap
// (undi_pn_2022 => 2999), jadi membuka Minima untuk Juasseh membandingkannya
// dengan angka Johor.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\ElectionSeat;
use App\Models\ElectionSeatResult;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Pilihanraya\SeatBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatBaselineTest extends TestCase
{
    use RefreshDatabase;

    private function juasseh(): Kadun
    {
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $bandar = Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id, 'kod_parlimen' => 'P129']);

        return Kadun::create(['nama' => 'Juasseh', 'bandar_id' => $bandar->id, 'kod_dun' => 'N15']);
    }

    /** Keputusan PRN Juasseh 2023 yang sebenar. */
    private function syncedResult(Kadun $kadun): ElectionSeatResult
    {
        $seat = ElectionSeat::create([
            'slug' => 'n15-juasseh-negeri-sembilan', 'nama' => 'Juasseh', 'kod' => 'N15',
            'negeri' => 'Negeri Sembilan', 'jenis' => 'dun', 'kadun_id' => $kadun->id,
        ]);

        return ElectionSeatResult::create([
            'election_seat_id' => $seat->id, 'election_name' => 'SE-15', 'tarikh' => '2023-08-12',
            'party' => 'BN', 'coalition' => 'BN', 'candidate' => 'Bibi Sharliza',
            'majority' => 78, 'majority_perc' => 0.8657,
            'voter_turnout' => 9122, 'voter_turnout_perc' => 68.03,
            'voters_total' => 13408, 'votes_rejected' => 87,
            'ballot' => [
                ['name' => 'Eddin Syazlee', 'party' => 'PN', 'votes' => 4471, 'votes_perc' => 49.57],
                ['name' => 'Bibi Sharliza', 'party' => 'BN', 'votes' => 4549, 'votes_perc' => 50.43],
            ],
        ]);
    }

    private function service(): SeatBaselineService
    {
        return app(SeatBaselineService::class);
    }

    public function test_baseline_returns_the_real_seat_result(): void
    {
        $kadun = $this->juasseh();
        $this->syncedResult($kadun);

        $b = $this->service()->forKawasan($kadun);

        $this->assertTrue($b['tersedia']);
        $this->assertSame('SE-15', $b['pilihanraya']);
        $this->assertSame(78, $b['majoriti']);
        $this->assertSame(13408, $b['pengundi_berdaftar']);
        $this->assertSame(9122, $b['keluar_mengundi']);

        // Pemenang mesti calon dengan undi TERTINGGI, bukan yang pertama
        // dalam senarai — senarai API tidak dijamin tersusun.
        $this->assertSame('BN', $b['pemenang']['parti']);
        $this->assertSame(4549, $b['pemenang']['undi']);
        $this->assertSame('PN', $b['penyaing']['parti']);
        $this->assertSame(4471, $b['penyaing']['undi']);
    }

    /** Inilah nilai yang menggantikan 2,999 Buloh Kasap yang bertulis-keras. */
    public function test_party_votes_reads_the_real_pn_total(): void
    {
        $kadun = $this->juasseh();
        $this->syncedResult($kadun);
        $s = $this->service();

        $this->assertSame(4471, $s->partyVotes($s->forKawasan($kadun), ['PN', 'PERIKATAN NASIONAL']));
    }

    /**
     * Parti yang tidak bertanding memulangkan NULL, bukan 0. 0 di sini akan
     * membaca sebagai "PH bertanding dan mendapat sifar undi".
     */
    public function test_a_party_that_did_not_contest_returns_null(): void
    {
        $kadun = $this->juasseh();
        $this->syncedResult($kadun);
        $s = $this->service();

        $this->assertNull($s->partyVotes($s->forKawasan($kadun), 'PH'));
    }

    /** Kerusi tanpa data disegerakkan: bentuk kosong, setiap angka null. */
    public function test_an_unsynced_seat_has_no_baseline(): void
    {
        $b = $this->service()->forKawasan($this->juasseh());

        $this->assertFalse($b['tersedia']);
        $this->assertNull($b['majoriti']);
        $this->assertNull($b['pengundi_berdaftar']);
        $this->assertSame([], $b['ballot']);
    }

    /**
     * Pilihan raya AKAN DATANG (party null) bukan garis dasar — memaparkannya
     * akan menerbitkan keputusan bagi pilihan raya yang belum berlaku.
     */
    public function test_an_upcoming_election_is_never_used_as_the_baseline(): void
    {
        $kadun = $this->juasseh();
        $result = $this->syncedResult($kadun);
        ElectionSeatResult::create([
            'election_seat_id' => $result->election_seat_id,
            'election_name' => 'SE-16', 'tarikh' => '2026-08-01',
            'party' => null, 'majority' => null,
        ]);

        $b = $this->service()->forKawasan($kadun);

        $this->assertSame('SE-15', $b['pilihanraya']);
        $this->assertSame(78, $b['majoriti']);
    }

    public function test_the_endpoint_returns_the_baseline(): void
    {
        $kadun = $this->juasseh();
        $this->syncedResult($kadun);
        $user = User::factory()->create(['role' => 'super_admin', 'telephone' => '0123458888']);

        $this->actingAs($user)
            ->getJson(route('pilihanraya.analisa.seat-baseline', ['kadun_id' => $kadun->id]))
            ->assertOk()
            ->assertJsonPath('baseline.tersedia', true)
            ->assertJsonPath('baseline.majoriti', 78);
    }

    /** Kawasan tanpa garis dasar ialah keadaan sah untuk dipaparkan "—", bukan ralat. */
    public function test_the_endpoint_returns_an_empty_shape_for_an_unsynced_seat(): void
    {
        $kadun = $this->juasseh();
        $user = User::factory()->create(['role' => 'super_admin', 'telephone' => '0123458887']);

        $this->actingAs($user)
            ->getJson(route('pilihanraya.analisa.seat-baseline', ['kadun_id' => $kadun->id]))
            ->assertOk()
            ->assertJsonPath('baseline.tersedia', false)
            ->assertJsonPath('baseline.majoriti', null);
    }

    /**
     * Minima tidak lagi boleh menyemai kerusi mana-mana dengan tally Buloh Kasap.
     * Tanpa garis dasar disegerakkan, ketiga-tiga asas Jadual 3 kekal null.
     */
    public function test_minima_no_longer_seeds_another_seats_tally(): void
    {
        $kadun = $this->juasseh();
        $user = User::factory()->create(['role' => 'super_admin', 'telephone' => '0123458886']);

        $andaian = $this->actingAs($user)
            ->get(route('pilihanraya.minima', ['kawasan' => (string) $kadun->id]))
            ->assertOk()
            ->viewData('page')['props']['minima']['andaian'];

        $this->assertNull($andaian['melayu_ph_2022']);
        $this->assertNull($andaian['melayu_bn_2022']);
        $this->assertNull($andaian['undi_pn_2022']);
    }

    public function test_minima_seeds_the_real_pn_total_when_synced(): void
    {
        $kadun = $this->juasseh();
        $this->syncedResult($kadun);
        $user = User::factory()->create(['role' => 'super_admin', 'telephone' => '0123458885']);

        $props = $this->actingAs($user)
            ->get(route('pilihanraya.minima', ['kawasan' => (string) $kadun->id]))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(4471, $props['minima']['andaian']['undi_pn_2022']);
        $this->assertTrue($props['garisDasar']['tersedia']);
    }
}
