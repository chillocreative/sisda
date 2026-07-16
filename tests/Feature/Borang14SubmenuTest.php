<?php
// tests/Feature/Borang14SubmenuTest.php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Pilihanraya\KawasanResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $before = \DB::table('negeri')->count();

        $res = KawasanResolver::resolve($this->extracted(['negeri' => 'NEGERI REKAAN']));

        $this->assertFalse($res['ok']);
        $this->assertSame($before, \DB::table('negeri')->count(), 'Negeri TIDAK boleh dicipta.');
    }

    public function test_missing_kawasan_is_created_under_matched_negeri(): void
    {
        // Juasseh tiada dalam sistem — Negeri Sembilan ada 0 DUN.
        $res = KawasanResolver::resolve($this->extracted());

        $this->assertTrue($res['ok']);
        $this->assertSame('dun', $res['kawasan_type']);
        $this->assertDatabaseHas('kadun', ['nama' => 'JUASSEH']);
        $this->assertDatabaseHas('bandar', ['nama' => 'P.129']);
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
}
