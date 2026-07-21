<?php
// tests/Feature/ElectionDataServiceTest.php
//
// Kontrak yang dikunci di sini:
//   1. Perkhidmatan TIDAK PERNAH melontar — API mati merosotkan satu kad sahaja.
//   2. Angka yang tiada kekal NULL. Pilihan raya AKAN DATANG dipulangkan oleh
//      API dengan setiap angka null; menukarnya kepada 0 akan mereka-reka
//      kekalahan total yang tidak pernah berlaku.
//   3. slugFor() memulangkan null dan bukannya meneka — kerusi yang salah
//      memaparkan keputusan kerusi ORANG LAIN sebagai keputusan kerusi ini.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\ElectionDataSetting;
use App\Models\ElectionSeat;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Services\Pilihanraya\ElectionDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ElectionDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private ElectionDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        ElectionDataSetting::create(['api_key' => 'edmy_ujian', 'is_active' => true]);
        $this->service = app(ElectionDataService::class);
    }

    private function seedJuasseh(): Kadun
    {
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $bandar = Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id, 'kod_parlimen' => 'P129']);

        return Kadun::create(['nama' => 'Juasseh', 'bandar_id' => $bandar->id, 'kod_dun' => 'N15']);
    }

    public function test_seats_are_returned_on_success(): void
    {
        Http::fake([
            '*/v1/seats/dropdown*' => Http::response([
                ['seat' => 'Juasseh, Negeri Sembilan', 'slug' => 'n15-juasseh-negeri-sembilan', 'type' => 'dun'],
            ]),
        ]);

        $this->assertCount(1, $this->service->seats());
    }

    /** Kunci yang tidak sah tidak boleh menghasilkan pengecualian. */
    public function test_an_unauthorised_response_degrades_to_empty(): void
    {
        Http::fake(['*' => Http::response(['error' => 'unauthorised'], 401)]);

        $this->assertSame([], $this->service->seats());
        $this->assertNull($this->service->ballot('Juasseh', 'Negeri Sembilan', '2023-08-12'));
    }

    /** Rangkaian mati juga tidak boleh melontar. */
    public function test_a_connection_failure_degrades_to_empty(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $this->assertSame([], $this->service->seats());
        $this->assertSame([], $this->service->seatResults('n15-juasseh-negeri-sembilan'));
    }

    /** Tiada kunci dikonfigurasi = tiada panggilan keluar langsung. */
    public function test_nothing_is_requested_when_no_key_is_configured(): void
    {
        ElectionDataSetting::query()->delete();
        Http::fake();

        $this->assertFalse($this->service->isConfigured());
        $this->assertSame([], $this->service->seats());
        Http::assertNothingSent();
    }

    /**
     * Pilihan raya AKAN DATANG (cth SE-16 pada 2026-08-01) dipulangkan dengan
     * party/majority null. Nilai itu mesti sampai kepada pemanggil sebagai null.
     */
    public function test_null_figures_for_an_upcoming_election_survive_as_null(): void
    {
        Http::fake([
            '*/v1/seats/results*' => Http::response([
                ['election_name' => 'SE-16', 'date' => '2026-08-01', 'party' => null, 'majority' => null, 'voter_turnout' => null],
            ]),
        ]);

        $row = $this->service->seatResults('n15-juasseh-negeri-sembilan')[0];

        $this->assertNull($row['party']);
        $this->assertNull($row['majority']);
        $this->assertNull($row['voter_turnout']);
    }

    public function test_slug_is_built_from_the_seeded_seat_code(): void
    {
        $kadun = $this->seedJuasseh();
        ElectionSeat::create([
            'slug' => 'n15-juasseh-negeri-sembilan', 'nama' => 'Juasseh',
            'kod' => 'N15', 'negeri' => 'Negeri Sembilan', 'jenis' => 'dun',
        ]);

        $this->assertSame('n15-juasseh-negeri-sembilan', $this->service->slugFor($kadun));
    }

    public function test_parlimen_slug_uses_its_own_code(): void
    {
        $kadun = $this->seedJuasseh();
        ElectionSeat::create([
            'slug' => 'p129-kuala-pilah-negeri-sembilan', 'nama' => 'Kuala Pilah',
            'kod' => 'P129', 'negeri' => 'Negeri Sembilan', 'jenis' => 'parlimen',
        ]);

        $this->assertSame('p129-kuala-pilah-negeri-sembilan', $this->service->slugFor($kadun->bandar));
    }

    /** Negeri tanpa kod diisi masih boleh dipadan mengikut nama + negeri. */
    public function test_a_seat_without_a_seeded_code_falls_back_to_name_matching(): void
    {
        $kadun = $this->seedJuasseh();
        $kadun->update(['kod_dun' => null]);
        ElectionSeat::create([
            'slug' => 'n15-juasseh-negeri-sembilan', 'nama' => 'JUASSEH',
            'kod' => 'N15', 'negeri' => 'NEGERI SEMBILAN', 'jenis' => 'dun',
        ]);

        $this->assertSame('n15-juasseh-negeri-sembilan', $this->service->slugFor($kadun));
    }

    public function test_an_unknown_seat_returns_null_rather_than_a_guess(): void
    {
        $kadun = $this->seedJuasseh();
        $kadun->update(['kod_dun' => null]);

        $this->assertNull($this->service->slugFor($kadun));
    }

    /**
     * Dua kerusi bernama sama dalam negeri yang sama bermakna data bercanggah.
     * Memilih satu secara rambang akan memaparkan keputusan kerusi yang salah.
     */
    public function test_an_ambiguous_name_match_returns_null(): void
    {
        $kadun = $this->seedJuasseh();
        $kadun->update(['kod_dun' => null]);
        foreach (['a-juasseh', 'b-juasseh'] as $slug) {
            ElectionSeat::create([
                'slug' => $slug, 'nama' => 'Juasseh', 'kod' => null,
                'negeri' => 'Negeri Sembilan', 'jenis' => 'dun',
            ]);
        }

        $this->assertNull($this->service->slugFor($kadun));
    }
}
