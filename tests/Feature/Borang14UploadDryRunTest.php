<?php
// tests/Feature/Borang14UploadDryRunTest.php
//
// Covers the two-step upload flow: dry run (extract + resolve WITHOUT writing)
// then commit (create kawasan/form/votes from a cached token). See
// docs/superpowers/specs for the approved design: a misread seat name must be
// cancellable BEFORE any geography row is created.
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

class Borang14UploadDryRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // "Negeri Sembilan" must exist so KawasanResolver can match it (the
        // fixture's header says "NEGERI SEMBILAN"). Parlimen/DUN deliberately
        // do NOT match Juasseh, so every dry run here reports 2 would-create rows.
        Negeri::create(['nama' => 'Negeri Sembilan']);
    }

    private function extractedFixture(array $over = []): array
    {
        return array_merge(
            json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true),
            $over
        );
    }

    private function user(string $phone = '0123450001'): User
    {
        // UserFactory has a pre-existing bug: it does not set the NOT NULL
        // `telephone` column. Work around it locally rather than fixing the
        // shared factory (per earlier tests in this suite).
        return User::factory()->create(['role' => 'admin', 'telephone' => $phone]);
    }

    private function mockExtractor(array $data, int $times = 1): void
    {
        $this->mock(ScoresheetExtractor::class, function ($mock) use ($data, $times) {
            $mock->shouldReceive('extractDetailed')->times($times)
                ->andReturn(['ok' => true, 'data' => $data, 'error' => null]);
        });
    }

    private function dryRunRequest(User $user, ?string $filename = 'scoresheet.pdf')
    {
        return $this->actingAs($user)->post(route('pilihanraya.borang-14.upload'), [
            'dry_run' => 1,
            'fail' => UploadedFile::fake()->create($filename, 10, 'application/pdf'),
            'jenis_pr' => 'prn',
            'tahun' => 2023,
        ]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->mockExtractor($this->extractedFixture());
        $user = $this->user();

        $beforeBandar = Bandar::count();
        $beforeKadun = Kadun::count();
        $beforeForms = Borang14Form::count();

        $res = $this->dryRunRequest($user)->assertOk();

        $res->assertJson(['ok' => true]);
        $this->assertNotEmpty($res->json('token'));
        $this->assertCount(2, $res->json('will_create'), 'Parlimen + DUN both missing — both should be reported as would-create.');
        $this->assertSame('NEGERI SEMBILAN', $res->json('negeri'));
        $this->assertSame('JUASSEH', $res->json('kawasan_nama'));

        $this->assertSame($beforeBandar, Bandar::count(), 'Dry run must not create bandar.');
        $this->assertSame($beforeKadun, Kadun::count(), 'Dry run must not create kadun.');
        $this->assertSame($beforeForms, Borang14Form::count(), 'Dry run must not create borang14_forms.');
    }

    public function test_commit_with_token_creates(): void
    {
        $this->mockExtractor($this->extractedFixture(), 1); // extraction happens ONCE, only at dry run
        $user = $this->user();

        $dry = $this->dryRunRequest($user)->assertOk();
        $token = $dry->json('token');

        $beforeForms = Borang14Form::count();

        $commit = $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertOk();

        $commit->assertJson(['ok' => true]);
        $this->assertNotEmpty($commit->json('form_id'));
        $this->assertCount(2, $commit->json('created'));
        $this->assertSame($beforeForms + 1, Borang14Form::count());
        $this->assertDatabaseHas('kadun', ['nama' => 'JUASSEH']);
        $this->assertDatabaseHas('bandar', ['nama' => 'P.129', 'kod_parlimen' => '129']);
    }

    public function test_commit_forgets_token_so_it_cannot_be_replayed(): void
    {
        $this->mockExtractor($this->extractedFixture(), 1);
        $user = $this->user();

        $token = $this->dryRunRequest($user)->assertOk()->json('token');

        $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertOk();

        $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertStatus(422);
    }

    public function test_unknown_or_expired_token_is_rejected(): void
    {
        $user = $this->user();

        $res = $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => 'this-token-does-not-exist'])
            ->assertStatus(422);

        $this->assertNotEmpty($res->json('message'));
        $this->assertSame(0, Borang14Form::count());
        $this->assertSame(0, Bandar::count());
        $this->assertSame(0, Kadun::count());
    }

    public function test_token_belonging_to_another_user_is_rejected(): void
    {
        $this->mockExtractor($this->extractedFixture(), 1);
        $owner = $this->user('0123450002');
        $intruder = $this->user('0123450003');

        $token = $this->dryRunRequest($owner)->assertOk()->json('token');

        $this->actingAs($intruder)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertStatus(422);

        $this->assertSame(0, Borang14Form::count());
        $this->assertSame(0, Bandar::count());
        $this->assertSame(0, Kadun::count());
    }

    public function test_dry_run_unknown_negeri_rejects_and_creates_nothing(): void
    {
        $this->mockExtractor($this->extractedFixture(['negeri' => 'NEGERI REKAAN']));
        $user = $this->user('0123450004');

        $res = $this->dryRunRequest($user)->assertStatus(422);

        $this->assertNotEmpty($res->json('message'));
        $this->assertSame(0, Borang14Form::count());
        $this->assertSame(0, Bandar::count());
        $this->assertSame(0, Kadun::count());
    }

    public function test_dry_run_skips_will_create_when_kawasan_already_exists(): void
    {
        $negeri = Negeri::where('nama', 'Negeri Sembilan')->first();
        $bandar = Bandar::create(['nama' => 'P.129', 'kod_parlimen' => '129', 'negeri_id' => $negeri->id]);
        Kadun::create(['nama' => 'JUASSEH', 'bandar_id' => $bandar->id]);

        $this->mockExtractor($this->extractedFixture());
        $user = $this->user('0123450005');

        $res = $this->dryRunRequest($user)->assertOk();

        $this->assertSame([], $res->json('will_create'));
    }
}
