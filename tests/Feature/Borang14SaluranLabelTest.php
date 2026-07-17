<?php
// tests/Feature/Borang14SaluranLabelTest.php
//
// Task 12 finding 1: referenceFromStructure() detected Undi Pos/Awal rows via
// str_contains($saluran, 'POS'/'AWAL') but then discarded the actual $saluran
// string, while votes are stored keyed on that EXACT string (putVote() uses
// $row['saluran']) and the frontend rendered a hardcoded 'UNDI POS' label. If
// the AI emits anything other than the exact literal 'UNDI POS' (e.g. an extra
// suffix), the row's votes render as 0 and any edit writes an orphan row under
// a key nothing reads. This proves the real saluran string survives into the
// reference so the frontend can key off it instead of a hardcoded constant.
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

class Borang14SaluranLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_saluran_label_other_than_the_exact_literal_is_preserved_and_keys_the_vote(): void
    {
        // Deliberately NOT the exact 'UNDI POS' literal the frontend used to
        // hardcode — a plausible real-world AI reading.
        $fixture = $this->extractedFixture();
        $fixture['rows'][0]['saluran'] = 'UNDI POS (B)';

        $this->mock(ScoresheetExtractor::class, function ($mock) use ($fixture) {
            $mock->shouldReceive('extractDetailed')->once()
                ->andReturn(['ok' => true, 'data' => $fixture, 'error' => null]);
        });

        $user = $this->user();

        $token = $this->actingAs($user)->post(route('pilihanraya.borang-14.upload'), [
            'dry_run' => 1,
            'fail' => UploadedFile::fake()->create('scoresheet.pdf', 10, 'application/pdf'),
            'jenis_pr' => 'prn',
            'tahun' => 2023,
        ])->assertOk()->json('token');

        $commit = $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertOk();

        $formId = $commit->json('form_id');
        $form = Borang14Form::findOrFail($formId);

        $res = $this->actingAs($user)->getJson(route('pilihanraya.borang-14.data', ['form_id' => $formId]))
            ->assertOk();

        // The reference must carry the REAL saluran string, not a hardcoded constant.
        $this->assertSame('UNDI POS (B)', $res->json('reference.undi_pos.label'),
            'Reference must carry the actual saluran string read from the sheet, not discard it.');

        // Votes are stored keyed on the exact saluran string (putVote() uses $row['saluran']).
        $votes = $res->json('votes');
        $key = '|UNDI POS (B)|1';
        $this->assertArrayHasKey($key, $votes,
            'Votes must be retrievable under the REAL saluran string used at write time.');
        $this->assertSame(98, $votes[$key]);

        // Sanity: confirm the vote really was written under that exact saluran in the DB.
        $this->assertDatabaseHas('borang14_votes', [
            'borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS (B)', 'slot' => 1, 'undi' => 98,
        ]);
    }
}
