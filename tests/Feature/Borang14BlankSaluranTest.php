<?php
// tests/Feature/Borang14BlankSaluranTest.php
//
// Final pre-merge review finding 5 (Important): putVote() wrote
// saluran ?? '1' while referenceFromStructure() and crosscheckIssues() read
// saluran ?? ''. Votes landed at pusat|1|slot but the frontend addresses
// pusat||slot — cells rendered 0 and any edit wrote an orphan row. Worse,
// putVote() OVERWROTE on the unique key, so N blank-saluran rows for one
// Pusat collided and only the last survived; the spec says such rows must
// be AGGREGATED (summed) into one row per Pusat instead.
namespace Tests\Feature;

use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Pilihanraya\ScoresheetExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Borang14BlankSaluranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Negeri::create(['nama' => 'Negeri Sembilan']);
    }

    /**
     * Two raw scoresheet rows for the SAME Pusat Mengundi where the AI could
     * not read the "No. Tempat Mengundi (Saluran)" column at all (key
     * entirely absent — not "" — the realistic failure mode of the extractor
     * simply omitting a field it couldn't read).
     */
    private function extractedWithBlankSaluranRows(): array
    {
        return [
            'negeri' => 'NEGERI SEMBILAN',
            'kawasan_kod' => 'N.15',
            'kawasan_nama' => 'JUASSEH',
            'parlimen_kod' => '129',
            'jumlah_pemilih' => 1000,
            'calon' => [
                ['nama' => 'CALON A', 'parti_tekaan' => null, 'yakin' => true],
                ['nama' => 'CALON B', 'parti_tekaan' => null, 'yakin' => true],
            ],
            'rows' => [
                [
                    'dm_kod' => '129/15/01', 'dm' => 'DM UJIAN', 'pusat' => 'PM UJIAN',
                    'a' => 10, 'undi' => [6, 4], 'jumlah_undian' => 10, 'ditolak' => 0, 'tidak_dimasukkan' => 0,
                ],
                [
                    'dm_kod' => '129/15/01', 'dm' => 'DM UJIAN', 'pusat' => 'PM UJIAN',
                    'a' => 8, 'undi' => [5, 3], 'jumlah_undian' => 8, 'ditolak' => 1, 'tidak_dimasukkan' => 2,
                ],
            ],
            'jumlah' => ['a' => 18, 'undi' => [11, 7], 'jumlah_undian' => 18, 'ditolak' => 1, 'tidak_dimasukkan' => 2],
        ];
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123450030']);
    }

    private function commitUpload(User $user, array $extracted): Borang14Form
    {
        $this->mock(ScoresheetExtractor::class, function ($mock) use ($extracted) {
            $mock->shouldReceive('extractDetailed')->once()
                ->andReturn(['ok' => true, 'data' => $extracted, 'error' => null]);
        });

        $dry = $this->actingAs($user)->post(route('pilihanraya.borang-14.upload'), [
            'dry_run' => 1,
            'fail' => UploadedFile::fake()->create('scoresheet.pdf', 10, 'application/pdf'),
            'jenis_pr' => 'prn',
            'tahun' => 2023,
        ])->assertOk();

        $commit = $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $dry->json('token')])
            ->assertOk();

        return Borang14Form::findOrFail($commit->json('form_id'));
    }

    public function test_blank_saluran_rows_for_the_same_pusat_are_aggregated_not_overwritten(): void
    {
        $form = $this->commitUpload($this->user(), $this->extractedWithBlankSaluranRows());

        // Exactly ONE row per (pusat, slot) — not one row surviving after N
        // overwrites, and not N orphaned rows under mismatched saluran keys.
        $slot1Rows = Borang14Vote::where('borang14_form_id', $form->id)
            ->where('pusat', 'PM UJIAN')->where('slot', 1)->get();
        $slot2Rows = Borang14Vote::where('borang14_form_id', $form->id)
            ->where('pusat', 'PM UJIAN')->where('slot', 2)->get();

        $this->assertCount(1, $slot1Rows, 'Blank-saluran rows sharing a Pusat must collapse to ONE row per slot, not N orphaned rows.');
        $this->assertCount(1, $slot2Rows);

        // Aggregated (summed), not last-write-wins: slot 1 = 6 + 5 = 11, slot 2 = 4 + 3 = 7.
        $this->assertSame(11, $slot1Rows->first()->undi, 'Two blank-saluran rows must be SUMMED, not overwritten (last-write-wins would give 5).');
        $this->assertSame(7, $slot2Rows->first()->undi, 'Two blank-saluran rows must be SUMMED, not overwritten (last-write-wins would give 3).');

        // Ditolak/tidak dimasukkan (slot 90/91) must aggregate the same way: 0+1=1, 0+2=2.
        $this->assertSame(1, Borang14Vote::where('borang14_form_id', $form->id)->where('pusat', 'PM UJIAN')->where('slot', 90)->value('undi'));
        $this->assertSame(2, Borang14Vote::where('borang14_form_id', $form->id)->where('pusat', 'PM UJIAN')->where('slot', 91)->value('undi'));
    }

    public function test_writer_and_reader_saluran_normalisation_agree_so_the_cell_key_matches(): void
    {
        $user = $this->user();
        $form = $this->commitUpload($user, $this->extractedWithBlankSaluranRows());

        $storedSaluran = Borang14Vote::where('borang14_form_id', $form->id)
            ->where('pusat', 'PM UJIAN')->where('slot', 1)->value('saluran');

        $res = $this->actingAs($user)->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $form->kawasan_id, 'jenis_pr' => 'prn', 'tahun' => 2023,
        ]))->assertOk();

        // The reference built from the frozen structure carries the saluran
        // label the frontend will key its lookup on
        // (cellKey = `${contest}|${pusat}|${saluran}|${slot}`).
        // The writer's stored saluran MUST equal that same value — any drift and
        // the cell renders 0 even though the vote is safely in the DB.
        $blocks = $res->json('reference.daerah_mengundi.0.pusat_mengundi.0');
        $this->assertNotNull($blocks);
        $this->assertCount(1, $blocks['saluran'], 'Duplicate blank-saluran rows for one Pusat must collapse to ONE displayed Saluran row, not N.');
        $referenceSaluranNo = $blocks['saluran'][0]['no'];

        $this->assertSame($referenceSaluranNo, $storedSaluran, 'Writer (putVote) and reader (referenceFromStructure) must normalise blank saluran to the SAME value.');

        $cellKey = 'dun|PM UJIAN|' . $referenceSaluranNo . '|1';
        $this->assertSame(11, $res->json('votes.' . $cellKey), 'The vote must be reachable under the exact cellKey the frontend computes — a mismatch renders 0 despite the DB holding the real number.');
    }
}
