<?php
// tests/Feature/Borang14CrosscheckTest.php
//
// Task 12 finding 3: crosscheckIssues() rebuilt 'jumlah_undian' as
// array_sum($undi) and 'calon' as array_fill(0, $nCalon, '') before calling
// ScoresheetExtractor::validateBalance() — feeding it values that can never
// disagree with themselves, making the 'jumlah_undian' and 'calon_count'
// rules mathematically unreachable (only 'balance' could ever fire). These
// tests prove all three rules can genuinely fire once the REAL frozen values
// (the sheet's own printed total, the sheet's own candidate list) are fed in
// instead of live-derived placeholders.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14CrosscheckTest extends TestCase
{
    use RefreshDatabase;

    private function seedGeography(): array
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);

        return [$negeri, $bandar, $kadun];
    }

    private function user(): User
    {
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123450009']);
    }

    private function fetchIssues(User $user, Kadun $kadun, int $tahun = 2023): array
    {
        $res = $this->actingAs($user)->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => $tahun,
        ]))->assertOk();

        return $res->json('form.crosscheck_issues') ?? [];
    }

    public function test_jumlah_undian_rule_fires_when_live_votes_diverge_from_the_sheets_printed_total(): void
    {
        [, , $kadun] = $this->seedGeography();

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'A'], ['nama' => 'B']],
                'rows' => [[
                    'pusat' => '', 'saluran' => 'UNDI POS',
                    // Sheet printed jumlah_undian=171 (98+73) and a=203.
                    'a' => 203, 'undi' => [98, 73], 'jumlah_undian' => 171,
                    'ditolak' => 18, 'tidak_dimasukkan' => 14,
                ]],
            ],
        ]);

        // Live entry diverges from the sheet's own printed total (171):
        // 100 + 73 = 173 != 171.
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 1, 'undi' => 100]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 2, 'undi' => 73]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 90, 'undi' => 18]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 91, 'undi' => 14]);

        $issues = $this->fetchIssues($this->user(), $kadun);

        $this->assertTrue(
            collect($issues)->contains(fn ($m) => str_contains($m, 'jumlah undian')),
            'jumlah_undian rule must fire when the live vote sum diverges from the sheet\'s own printed total. Got: '.json_encode($issues)
        );
    }

    public function test_calon_count_rule_fires_when_live_candidate_count_diverges_from_the_sheets_own_list(): void
    {
        [, , $kadun] = $this->seedGeography();

        // Frozen extraction saw only 2 candidates on the sheet, but the form's
        // live penjuru was later changed to 3 (e.g. via saveParties) without
        // the underlying extraction actually having a 3rd candidate column.
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 3, 'status' => 'draft',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'A'], ['nama' => 'B']],
                'rows' => [[
                    'pusat' => '', 'saluran' => 'UNDI POS',
                    'a' => 0, 'undi' => [0, 0], 'jumlah_undian' => 0,
                    'ditolak' => 0, 'tidak_dimasukkan' => 0,
                ]],
            ],
        ]);

        $issues = $this->fetchIssues($this->user(), $kadun);

        $this->assertTrue(
            collect($issues)->contains(fn ($m) => str_contains($m, 'bilangan calon')),
            'calon_count rule must fire when the live candidate count diverges from the sheet\'s own candidate list. Got: '.json_encode($issues)
        );
    }

    public function test_balance_rule_still_fires_on_column_a_mismatch(): void
    {
        [, , $kadun] = $this->seedGeography();

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'A'], ['nama' => 'B']],
                'rows' => [[
                    'pusat' => '', 'saluran' => 'UNDI POS',
                    'a' => 203, 'undi' => [98, 73], 'jumlah_undian' => 171,
                    'ditolak' => 18, 'tidak_dimasukkan' => 14,
                ]],
            ],
        ]);

        // Live votes match the printed totals exactly EXCEPT ditolak, which is
        // wrong — sum+ditolak+td = 98+73+0+14 = 185 != a (203).
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 1, 'undi' => 98]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 2, 'undi' => 73]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 90, 'undi' => 0]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 91, 'undi' => 14]);

        $issues = $this->fetchIssues($this->user(), $kadun);

        $this->assertTrue(
            collect($issues)->contains(fn ($m) => str_contains($m, '(A) dijangka')),
            'balance rule must still fire on a column (A) mismatch. Got: '.json_encode($issues)
        );
        // And it must NOT spuriously fire jumlah_undian here (98+73=171 matches the sheet exactly).
        $this->assertFalse(
            collect($issues)->contains(fn ($m) => str_contains($m, 'jumlah undian')),
            'jumlah_undian must not fire when the live sum matches the sheet\'s printed total. Got: '.json_encode($issues)
        );
    }
}
