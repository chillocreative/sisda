<?php
// tests/Feature/Borang14UploadParlimenTest.php
//
// Final pre-merge review finding 3 (Important): KawasanResolver::match() used
// to hardcode kawasan_type => Borang14Form::KAWASAN_DUN, so a Parlimen-level
// scoresheet was silently written as a DUN. An SPR sheet header distinguishes
// them via kawasan_kod's prefix — "N.15" (DUN) vs "P.129" (Parlimen). These
// tests prove the upload path resolves to the correct kawasan_type from that
// prefix, and rejects with a clear Bahasa Melayu message when the prefix is
// absent or unrecognised rather than silently assuming DUN.
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

class Borang14UploadParlimenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Negeri::create(['nama' => 'Negeri Sembilan']);
    }

    /** A Parlimen-level scoresheet header: "BAHAGIAN PILIHAN RAYA PERSEKUTUAN : P.129 JUASSEH". */
    private function parlimenFixture(array $over = []): array
    {
        $base = json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true);

        // Parlimen-level sheets carry a "P."-prefixed kawasan_kod naming the seat
        // itself (no DUN sub-level below it) — never a "N."-prefixed one.
        return array_merge($base, ['kawasan_kod' => 'P.129', 'parlimen_kod' => '129'], $over);
    }

    private function user(string $phone = '0123450020'): User
    {
        return User::factory()->create(['role' => 'admin', 'telephone' => $phone]);
    }

    private function mockExtractor(array $data): void
    {
        $this->mock(ScoresheetExtractor::class, function ($mock) use ($data) {
            $mock->shouldReceive('extractDetailed')->once()
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

    public function test_upload_resolves_parlimen_type_from_kawasan_kod_prefix(): void
    {
        $this->mockExtractor($this->parlimenFixture());
        $user = $this->user();

        $dry = $this->dryRunRequest($user)->assertOk();
        $token = $dry->json('token');

        $commit = $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertOk();

        $form = Borang14Form::findOrFail($commit->json('form_id'));
        $this->assertSame('parlimen', $form->kawasan_type, 'A "P."-prefixed scoresheet must be written as a Parlimen form, not a DUN.');
        $this->assertInstanceOf(Bandar::class, $form->kawasan());
        $this->assertSame(0, Kadun::count(), 'A pure Parlimen-level scoresheet must never fabricate a DUN.');
    }

    public function test_upload_rejects_unrecognised_kawasan_kod_prefix(): void
    {
        $this->mockExtractor($this->parlimenFixture(['kawasan_kod' => 'X.99']));
        $user = $this->user('0123450021');

        $res = $this->dryRunRequest($user)->assertStatus(422);

        $this->assertNotEmpty($res->json('message'));
        $this->assertSame(0, Borang14Form::count());
        $this->assertSame(0, Bandar::count());
        $this->assertSame(0, Kadun::count());
    }

    public function test_upload_rejects_missing_kawasan_kod(): void
    {
        $this->mockExtractor($this->parlimenFixture(['kawasan_kod' => '']));
        $user = $this->user('0123450022');

        $res = $this->dryRunRequest($user)->assertStatus(422);

        $this->assertNotEmpty($res->json('message'));
        $this->assertSame(0, Borang14Form::count());
    }

    public function test_dry_run_surfaces_resolved_kawasan_type_for_the_confirm_panel(): void
    {
        $this->mockExtractor($this->parlimenFixture());
        $user = $this->user('0123450023');

        $dry = $this->dryRunRequest($user)->assertOk();

        $this->assertSame('parlimen', $dry->json('kawasan_type'), 'Dry run must surface the resolved seat type so the confirm panel can show it before writing.');
    }

    /**
     * Finding (Minor): a Parlimen-level sheet DOES carry its own real name in
     * kawasan_nama (unlike a DUN sheet, which only knows its parent Parlimen by
     * kod, never by name). The resolver used to always name a newly-created
     * Parlimen 'P.<kod>', throwing away that real name even when it was known —
     * so the confirm panel would show "Parlimen JUASSEH" while the DB row was
     * actually named "P.129".
     */
    public function test_new_parlimen_bandar_is_named_after_the_sheets_real_seat_name(): void
    {
        $this->mockExtractor($this->parlimenFixture());
        $user = $this->user('0123450024');

        $token = $this->dryRunRequest($user)->assertOk()->json('token');

        $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertOk();

        $this->assertDatabaseHas('bandar', ['nama' => 'JUASSEH', 'kod_parlimen' => 'P129']);
        $this->assertDatabaseMissing('bandar', ['nama' => 'P.129']);
    }
}
