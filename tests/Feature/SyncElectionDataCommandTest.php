<?php
// tests/Feature/SyncElectionDataCommandTest.php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\ElectionDataSetting;
use App\Models\ElectionSeat;
use App\Models\ElectionSeatResult;
use App\Models\Kadun;
use App\Models\Negeri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncElectionDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ElectionDataSetting::create(['api_key' => 'edmy_ujian', 'is_active' => true]);
    }

    /** Dua kerusi: satu di N9 (dipadan), satu di Johor (untuk menguji penapis --state). */
    private function fakeApi(): void
    {
        Http::fake([
            '*/v1/seats/dropdown*' => Http::response([
                ['seat' => 'Juasseh, Negeri Sembilan', 'slug' => 'n15-juasseh-negeri-sembilan', 'type' => 'dun'],
                ['seat' => 'Buloh Kasap, Johor', 'slug' => 'n17-buloh-kasap-johor', 'type' => 'dun'],
            ]),
            '*/v1/seats/results*' => Http::response([
                [
                    'election_name' => 'SE-15', 'date' => '2023-08-12', 'party' => 'BN',
                    'coalition' => 'BN', 'name' => 'Bibi Sharliza', 'majority' => 78,
                    'majority_perc' => 0.8657, 'voter_turnout' => 9122,
                    'voter_turnout_perc' => 68.03, 'voters_total' => 13408,
                    'votes_rejected' => 87,
                ],
                // Pilihan raya AKAN DATANG — setiap angka null.
                ['election_name' => 'SE-16', 'date' => '2026-08-01', 'party' => null, 'majority' => null],
            ]),
        ]);
    }

    private function seedGeography(): Kadun
    {
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $bandar = Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id, 'kod_parlimen' => 'P129']);

        return Kadun::create(['nama' => 'Juasseh', 'bandar_id' => $bandar->id, 'kod_dun' => 'N15']);
    }

    public function test_sync_stores_seats_and_results(): void
    {
        $this->fakeApi();
        $kadun = $this->seedGeography();

        $this->artisan('pilihanraya:sync-electiondata')->assertSuccessful();

        $seat = ElectionSeat::where('slug', 'n15-juasseh-negeri-sembilan')->firstOrFail();
        $this->assertSame('Juasseh', $seat->nama);
        $this->assertSame('N15', $seat->kod);
        $this->assertSame('Negeri Sembilan', $seat->negeri);
        $this->assertSame('dun', $seat->jenis);
        $this->assertSame($kadun->id, $seat->kadun_id);

        $this->assertSame(2, $seat->results()->count());
    }

    /** Angka Juasseh 2023 sebenar, disalin apa adanya. */
    public function test_the_completed_result_keeps_its_real_figures(): void
    {
        $this->fakeApi();
        $this->seedGeography();

        $this->artisan('pilihanraya:sync-electiondata')->assertSuccessful();

        $r = ElectionSeatResult::where('election_name', 'SE-15')->firstOrFail();
        $this->assertSame('BN', $r->party);
        $this->assertSame(78, $r->majority);
        $this->assertSame(13408, $r->voters_total);
        $this->assertSame(9122, $r->voter_turnout);
    }

    /**
     * PALING PENTING: pilihan raya akan datang mesti kekal null. Nilai 0 di sini
     * membaca sebagai "0 undi, 0 keluar mengundi" dan mereka-reka kekalahan
     * total bagi pilihan raya yang belum berlaku.
     */
    public function test_an_upcoming_election_stays_null_and_is_not_a_baseline(): void
    {
        $this->fakeApi();
        $this->seedGeography();

        $this->artisan('pilihanraya:sync-electiondata')->assertSuccessful();

        $akanDatang = ElectionSeatResult::where('election_name', 'SE-16')->firstOrFail();
        $this->assertNull($akanDatang->party);
        $this->assertNull($akanDatang->majority);
        $this->assertNull($akanDatang->voter_turnout);
        $this->assertFalse($akanDatang->isCompleted());

        // Garis dasar mesti melangkaunya dan menggunakan keputusan 2023 sebenar.
        $seat = ElectionSeat::where('slug', 'n15-juasseh-negeri-sembilan')->firstOrFail();
        $this->assertSame('SE-15', $seat->latestCompletedResult()->election_name);
    }

    public function test_running_twice_creates_no_duplicates(): void
    {
        $this->fakeApi();
        $this->seedGeography();

        $this->artisan('pilihanraya:sync-electiondata')->assertSuccessful();
        $this->artisan('pilihanraya:sync-electiondata')->assertSuccessful();

        $this->assertSame(2, ElectionSeat::count());
        $this->assertSame(4, ElectionSeatResult::count());   // 2 keputusan × 2 kerusi
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fakeApi();
        $this->seedGeography();

        $this->artisan('pilihanraya:sync-electiondata', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, ElectionSeat::count());
        $this->assertSame(0, ElectionSeatResult::count());
        $this->assertNull(ElectionDataSetting::current()->last_synced_at);
    }

    public function test_state_filter_limits_the_sync(): void
    {
        $this->fakeApi();
        $this->seedGeography();

        $this->artisan('pilihanraya:sync-electiondata', ['--state' => 'Negeri Sembilan'])->assertSuccessful();

        $this->assertSame(1, ElectionSeat::count());
        $this->assertSame('n15-juasseh-negeri-sembilan', ElectionSeat::first()->slug);
    }

    /**
     * Kerusi yang tidak dapat dipadan dengan geografi SISDA tetap disimpan,
     * tetapi TIDAK dipaut kepada kawasan yang salah — geografi SISDA dipadan
     * mengikut rentetan dan padanan yang tersasar akan mencemarkan data.
     */
    public function test_an_unmatched_seat_is_stored_without_a_link(): void
    {
        $this->fakeApi();
        $this->seedGeography();

        $this->artisan('pilihanraya:sync-electiondata')->assertSuccessful();

        $johor = ElectionSeat::where('slug', 'n17-buloh-kasap-johor')->firstOrFail();
        $this->assertNull($johor->kadun_id);
        $this->assertNull($johor->bandar_id);
    }

    public function test_it_refuses_to_run_without_a_key(): void
    {
        ElectionDataSetting::query()->delete();
        Http::fake();

        $this->artisan('pilihanraya:sync-electiondata')->assertFailed();
        Http::assertNothingSent();
    }
}
