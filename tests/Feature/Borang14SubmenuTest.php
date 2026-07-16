<?php
// tests/Feature/Borang14SubmenuTest.php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Snapshot;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Pilihanraya\KawasanResolver;
use App\Services\Pilihanraya\ScoresheetExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Borang14SubmenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jadual geografi kosong di bawah RefreshDatabase — sedia data ujian.
        // "Negeri Sembilan" mesti wujud supaya KawasanResolver dapat memadankannya
        // (fixture scoresheet-juasseh-2023.json memulangkan "NEGERI SEMBILAN").
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);
    }

    private function extracted(array $over = []): array
    {
        return array_merge(
            json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true),
            $over
        );
    }

    public function test_unknown_negeri_is_rejected_and_creates_nothing(): void
    {
        $beforeNegeri = \DB::table('negeri')->count();
        $beforeBandar = \DB::table('bandar')->count();
        $beforeKadun = \DB::table('kadun')->count();

        $res = KawasanResolver::resolve($this->extracted(['negeri' => 'NEGERI REKAAN']));

        $this->assertFalse($res['ok']);
        $this->assertSame($beforeNegeri, \DB::table('negeri')->count(), 'Negeri TIDAK boleh dicipta.');
        $this->assertSame($beforeBandar, \DB::table('bandar')->count(), 'Bandar TIDAK boleh dicipta apabila negeri ditolak.');
        $this->assertSame($beforeKadun, \DB::table('kadun')->count(), 'Kadun TIDAK boleh dicipta apabila negeri ditolak.');
    }

    public function test_blank_kawasan_nama_is_rejected_and_creates_nothing(): void
    {
        $beforeNegeri = \DB::table('negeri')->count();
        $beforeBandar = \DB::table('bandar')->count();
        $beforeKadun = \DB::table('kadun')->count();

        // Negeri & Kod Parlimen sah, tetapi nama kawasan (DUN) kosong — mesti ditolak
        // TANPA meninggalkan sebarang bandar/kadun anak-yatim (rollback penuh).
        $res = KawasanResolver::resolve($this->extracted(['kawasan_nama' => '']));

        $this->assertFalse($res['ok']);
        $this->assertSame($beforeNegeri, \DB::table('negeri')->count(), 'Negeri TIDAK boleh dicipta.');
        $this->assertSame($beforeBandar, \DB::table('bandar')->count(), 'Bandar TIDAK boleh dicipta apabila nama kawasan kosong.');
        $this->assertSame($beforeKadun, \DB::table('kadun')->count(), 'Kadun TIDAK boleh dicipta apabila nama kawasan kosong.');
    }

    public function test_missing_kawasan_is_created_under_matched_negeri(): void
    {
        // Juasseh tiada dalam sistem — Negeri Sembilan ada 0 DUN.
        $res = KawasanResolver::resolve($this->extracted());

        $this->assertTrue($res['ok']);
        $this->assertSame('dun', $res['kawasan_type']);
        $this->assertDatabaseHas('kadun', ['nama' => 'JUASSEH']);
        // kod_parlimen mesti ikut konvensyen seeder sedia ada ('P' + nombor, tiada
        // titik — cth 'P160') supaya padanan kod (bukan nama) berfungsi konsisten.
        $this->assertDatabaseHas('bandar', ['nama' => 'P.129', 'kod_parlimen' => 'P129']);
    }

    public function test_publish_moves_draft_into_senarai(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'telephone' => '0123456789']);
        $kadun = Kadun::first();
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.publish'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertSame('published', $form->fresh()->status);
        $this->assertNotNull($form->fresh()->published_at);
    }

    public function test_senarai_returns_drafts_and_published(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'telephone' => '0123456788']);
        $kadun = Kadun::first();
        foreach ([['prn', 2022, 'draft'], ['prn', 2026, 'published']] as [$j, $t, $s]) {
            Borang14Form::create([
                'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
                'jenis_pr' => $j, 'tahun' => $t, 'penjuru' => 2, 'status' => $s,
            ]);
        }

        $res = $this->actingAs($user)->getJson(route('pilihanraya.borang-14.senarai', [
            'negeri_id' => $kadun->bandar->negeri_id,
        ]));

        $res->assertOk()->assertJsonCount(2, 'rows');
    }

    /**
     * Prove the ordering invariant in Borang14Controller::upload(): when a scoresheet
     * overwrites an EXISTING form, the Borang14Snapshot (reason 'before_scoresheet_overwrite')
     * must be written BEFORE the old votes are deleted. If the code snapshotted AFTER
     * deleting, the snapshot's votes would be empty — this test proves it captures the
     * OLD numbers, not the new ones and not nothing.
     */
    public function test_scoresheet_overwrite_snapshots_old_votes_before_deleting_them(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'telephone' => '0123456780']);

        $first = $this->extracted();
        $second = $this->extracted();
        $second['rows'][0]['undi'] = [500, 300];   // baris "UNDI POS" — nilai baru, boleh dibezakan drpd yang lama (98, 73)

        $this->mock(ScoresheetExtractor::class, function ($mock) use ($first, $second) {
            $mock->shouldReceive('extractDetailed')
                ->twice()
                ->andReturn(
                    ['ok' => true, 'data' => $first, 'error' => null],
                    ['ok' => true, 'data' => $second, 'error' => null],
                );
        });

        // Upload is now a two-step dry-run/commit flow: dry run extracts + resolves
        // WITHOUT writing, commit reads the cached extraction back out by token.
        // The extractor mock above expects exactly 2 calls — one per dry run below;
        // commit must NOT re-extract.
        $dryRun = fn (string $name) => $this->actingAs($user)->post(route('pilihanraya.borang-14.upload'), [
            'dry_run' => 1,
            'fail' => UploadedFile::fake()->create($name, 10, 'application/pdf'),
            'jenis_pr' => 'prn',
            'tahun' => 2023,
        ]);
        $commit = fn (string $token) => $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token]);

        // Muat naik pertama: borang baru — TIADA snapshot patut dicipta.
        $token1 = $dryRun('scoresheet-1.pdf')->assertOk()->json('token');
        $res1 = $commit($token1)->assertOk();
        $formId = $res1->json('form_id');
        $this->assertSame(
            0,
            Borang14Snapshot::where('borang14_form_id', $formId)->count(),
            'Muat naik pertama (borang baru) tidak sepatutnya mencipta snapshot.'
        );

        // Muat naik kedua: borang SUDAH wujud — scoresheet menang, tetapi snapshot
        // keadaan LAMA mesti dicipta dahulu sebelum undi lama dipadam.
        $token2 = $dryRun('scoresheet-2.pdf')->assertOk()->json('token');
        $commit($token2)->assertOk();

        $snap = Borang14Snapshot::where('borang14_form_id', $formId)
            ->where('reason', 'before_scoresheet_overwrite')->first();
        $this->assertNotNull($snap, 'Snapshot before_scoresheet_overwrite mesti wujud.');

        $oldVote = collect($snap->votes)
            ->first(fn ($v) => $v['pusat'] === '' && $v['saluran'] === 'UNDI POS' && (int) $v['slot'] === 1);
        $this->assertNotNull($oldVote, 'Snapshot mesti mengandungi undi LAMA — bukan kosong.');
        $this->assertSame(98, $oldVote['undi'], 'Snapshot mesti tangkap nilai SEBELUM overwrite (98), bukan selepas padam (kosong) atau nilai baru (500).');

        $newVote = Borang14Vote::where('borang14_form_id', $formId)
            ->where('pusat', '')->where('saluran', 'UNDI POS')->where('slot', 1)->first();
        $this->assertSame(500, $newVote->undi, 'Borang semasa mesti mempunyai nilai BARU selepas overwrite.');
    }
}
