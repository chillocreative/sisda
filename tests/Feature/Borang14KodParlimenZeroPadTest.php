<?php
// tests/Feature/Borang14KodParlimenZeroPadTest.php
//
// Final pre-merge review finding (Important): KawasanResolver::match() compared
// 'P' . parlimen_kod against the seeded kod_parlimen verbatim. parlimen_kod is
// copied verbatim from the scoresheet's DM code segment (e.g. "041/12/03" ->
// "041"), so any seat numbered below 100 arrives zero-padded. Penang's seeder
// (database/seeders/PenangParlimenSeeder.php) stores 'P41'..'P53' — no leading
// zero — so 'P041' never matched 'P41', silently creating a DUPLICATE
// placeholder Parlimen (plus a duplicate DUN under it) detached from the real
// seat. Johor's codes (140-166) and Negeri Sembilan's (129-133) are all
// three-digit, which is why this went unnoticed until a Penang sheet was tried.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Pilihanraya\ScoresheetExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Borang14KodParlimenZeroPadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Negeri::create(['nama' => 'Pulau Pinang']);
    }

    /** A Penang DUN-level scoresheet whose parlimen_kod is zero-padded, as the extractor
     *  copies it verbatim from a DM code like "041/12/03". */
    private function penangDunFixture(array $over = []): array
    {
        $base = json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true);

        return array_merge($base, [
            'negeri' => 'PULAU PINANG',
            'kawasan_kod' => 'N.12',
            'kawasan_nama' => 'PERMATANG PASIR',
            'parlimen_kod' => '041',
        ], $over);
    }

    private function user(string $phone = '0123450030'): User
    {
        return User::factory()->create(['role' => 'admin', 'telephone' => $phone]);
    }

    private function mockExtractor(array $data, int $times = 1): void
    {
        $this->mock(ScoresheetExtractor::class, function ($mock) use ($data, $times) {
            $mock->shouldReceive('extractDetailed')->times($times)
                ->andReturn(['ok' => true, 'data' => $data, 'error' => null]);
        });
    }

    private function dryRunRequest(User $user)
    {
        return $this->actingAs($user)->post(route('pilihanraya.borang-14.upload'), [
            'dry_run' => 1,
            'fail' => UploadedFile::fake()->create('scoresheet.pdf', 10, 'application/pdf'),
            'jenis_pr' => 'prn',
            'tahun' => 2023,
        ]);
    }

    /** The failing case this finding is about: a real seeded seat 'P41' must match
     *  a zero-padded extracted parlimen_kod "041" — no duplicate placeholder Parlimen. */
    public function test_zero_padded_parlimen_kod_matches_existing_seeded_seat(): void
    {
        $negeri = Negeri::where('nama', 'Pulau Pinang')->first();
        $realBandar = Bandar::create(['nama' => 'Kepala Batas', 'kod_parlimen' => 'P41', 'negeri_id' => $negeri->id]);

        $this->mockExtractor($this->penangDunFixture());
        $user = $this->user();

        $beforeBandar = Bandar::count();

        $dry = $this->dryRunRequest($user)->assertOk();
        $this->assertCount(1, $dry->json('will_create'), 'Parlimen P41 already exists — only the DUN underneath should be reported as new.');
        $this->assertSame('dun', $dry->json('will_create.0.jenis'));

        $commit = $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $dry->json('token')])
            ->assertOk();

        $this->assertSame($beforeBandar, Bandar::count(), 'No duplicate Parlimen must be created for a zero-padded parlimen_kod that matches an existing seat.');
        $this->assertDatabaseHas('kadun', ['nama' => 'PERMATANG PASIR', 'bandar_id' => $realBandar->id]);
    }

    /** CREATE path must also normalise: a brand-new seat created from a zero-padded
     *  kod must be stored canonically (no leading zero), or the NEXT upload for the
     *  same seat would fail to match it and create yet another duplicate. */
    public function test_newly_created_parlimen_stores_canonical_kod_without_leading_zeros(): void
    {
        $this->mockExtractor($this->penangDunFixture(['parlimen_kod' => '007']));
        $user = $this->user('0123450031');

        $token = $this->dryRunRequest($user)->assertOk()->json('token');

        $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertOk();

        $this->assertDatabaseHas('bandar', ['kod_parlimen' => 'P7']);
        $this->assertDatabaseMissing('bandar', ['kod_parlimen' => 'P007']);
    }
}
