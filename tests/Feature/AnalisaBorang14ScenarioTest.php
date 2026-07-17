<?php

namespace Tests\Feature;

use App\Models\AnalisaComparison;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalisaBorang14ScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        // UserFactory tidak set telephone (NOT NULL) — pepijat sedia ada, luar skop.
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123456789']);
    }

    private function seedSeat(string $dunNama = 'JUASSEH'): array
    {
        $negeri = \App\Models\Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = \App\Models\Bandar::create(['nama' => 'P.129', 'negeri_id' => $negeri->id]);
        $kadun  = \App\Models\Kadun::create(['nama' => $dunNama, 'bandar_id' => $bandar->id]);

        return [$negeri, $bandar, $kadun];
    }

    private function form(\App\Models\Kadun $kadun): Borang14Form
    {
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'scoresheet',
            // keahlian_parti_id mesti sudah dipetakan — borang belum dipetakan
            // (calon sahaja) ditolak mapper, lihat ujian sedia/rejection di bawah.
            'parties' => [
                ['slot' => 1, 'keahlian_parti_id' => 101, 'nama' => 'PERIKATAN NASIONAL'],
                ['slot' => 2, 'keahlian_parti_id' => 102, 'nama' => 'PAKATAN HARAPAN'],
            ],
            'structure' => [
                'jumlah_pemilih' => 13408,
                'rows' => [['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SK TENGKEK', 'saluran' => '1']],
            ],
        ]);
        foreach ([[1, 48], [2, 76], [90, 3]] as [$slot, $undi]) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK',
                'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        return $form->fresh();
    }

    private function comparison(\App\Models\Bandar $bandar, \App\Models\Kadun $kadun): AnalisaComparison
    {
        return AnalisaComparison::create([
            'title' => 'Ujian', 'level' => 'dun',
            'bandar_id' => $bandar->id, 'kadun_id' => $kadun->id,
            'negeri' => 'NEGERI SEMBILAN', 'parlimen' => 'P.129', 'dun' => $kadun->nama,
        ]);
    }

    public function test_lists_only_forms_for_the_comparison_seat(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $this->form($kadun);

        // borang kerusi lain — tidak boleh muncul
        $lain = \App\Models\Kadun::create(['nama' => 'LAIN', 'bandar_id' => $bandar->id]);
        $this->form($lain);

        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.analisa.comparisons.borang14', $c));

        $res->assertOk()->assertJsonCount(1, 'forms');
        $this->assertSame(2023, $res->json('forms.0.tahun'));
    }

    public function test_creates_scenario_from_a_borang14_form(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $form = $this->form($kadun);
        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), [
                'form_id' => $form->id,
            ]);

        $res->assertOk();
        $this->assertSame(1, $c->scenarios()->count());

        $s = $c->scenarios()->first();
        $this->assertSame('PRN 2023', $s->label);
        $this->assertSame('2023-01-01', $s->election_date->format('Y-m-d'));
        $this->assertSame(127, $s->parsed_totals['keluar']);          // 48 + 76 + 3
        $this->assertSame(3, $s->parsed_totals['ditolak']);
        $this->assertSame(48, $s->parsed_totals['undi']['PERIKATAN NASIONAL']);
        $this->assertStringContainsString('Borang 14', $s->source_filename);
    }

    public function test_rejects_a_form_belonging_to_another_seat(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $lain = \App\Models\Kadun::create(['nama' => 'LAIN', 'bandar_id' => $bandar->id]);
        $formLain = $this->form($lain);
        $c = $this->comparison($bandar, $kadun);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), [
                'form_id' => $formLain->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $c->scenarios()->count());
    }

    public function test_enforces_the_three_scenario_limit(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $c = $this->comparison($bandar, $kadun);

        foreach ([2020, 2021, 2022] as $i => $tahun) {
            $f = $this->form($kadun);
            $f->update(['tahun' => $tahun]);
            $c->scenarios()->create([
                'position' => $i + 1, 'label' => "PRN {$tahun}",
                'election_date' => "{$tahun}-01-01",
                'parsed_rows' => [], 'parsed_totals' => [], 'row_count' => 0,
            ]);
        }

        $form = $this->form($kadun);
        $form->update(['tahun' => 2023]);

        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), [
                'form_id' => $form->id,
            ])
            ->assertStatus(422);
    }

    /**
     * Finding 2: scoresheet import seeds parties[].nama with the CANDIDATE's
     * own name as a placeholder (keahlian_parti_id null until mapped in
     * Keyin). `sedia` must require every slot to be mapped, not merely a
     * non-empty nama, else the AI ends up comparing a candidate's name
     * against a real party.
     */
    public function test_borang14_tersedia_flags_unmapped_form_as_not_ready(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $form = $this->form($kadun);
        $form->update(['parties' => [
            ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'EDDIN SYAZLEE BIN SHITH'],
            ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PAKATAN HARAPAN'],
        ]]);
        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.analisa.comparisons.borang14', $c));

        $res->assertOk();
        $this->assertFalse($res->json('forms.0.sedia'), 'Borang dengan calon belum dipetakan tidak boleh ditandakan sedia.');
    }

    public function test_borang14_tersedia_flags_fully_mapped_form_as_ready(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $this->form($kadun); // parties sudah dipetakan (keahlian_parti_id diisi) dalam helper form()
        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.analisa.comparisons.borang14', $c));

        $res->assertOk();
        $this->assertTrue($res->json('forms.0.sedia'));
    }

    /** Mapper-level rejection must surface as a BM 422 message, not a 500. */
    public function test_creating_scenario_from_unmapped_form_is_rejected(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $form = $this->form($kadun);
        $form->update(['parties' => [
            ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'EDDIN SYAZLEE BIN SHITH'],
            ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PAKATAN HARAPAN'],
        ]]);
        $c = $this->comparison($bandar, $kadun);

        $res = $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), [
                'form_id' => $form->id,
            ]);

        $res->assertStatus(422);
        $this->assertSame(0, $c->scenarios()->count());
    }

    public function test_borang14_and_upload_scenarios_coexist(): void
    {
        [, $bandar, $kadun] = $this->seedSeat();
        $form = $this->form($kadun);
        $c = $this->comparison($bandar, $kadun);

        // senario dari Borang 14
        $this->actingAs($this->user())
            ->postJson(route('pilihanraya.analisa.comparisons.scenarios.borang14', $c), ['form_id' => $form->id])
            ->assertOk();

        // senario "upload" dibina terus dengan bentuk yang sama
        $c->scenarios()->create([
            'position' => 2, 'label' => 'PRN 2018', 'election_date' => '2018-05-09',
            'source_filename' => 'upload.xlsx',
            'parsed_rows' => [['kawasan' => 'KAMPONG TENGKEK', 'pemilih' => 2000,
                               'keluar' => 1500, 'ditolak' => 10,
                               'undi' => ['PERIKATAN NASIONAL' => 700, 'PAKATAN HARAPAN' => 790]]],
            'parsed_totals' => ['pemilih' => 2000, 'keluar' => 1500, 'ditolak' => 10,
                                'undi' => ['PERIKATAN NASIONAL' => 700, 'PAKATAN HARAPAN' => 790],
                                'parties' => ['PERIKATAN NASIONAL', 'PAKATAN HARAPAN']],
            'row_count' => 1,
        ]);

        // Kedua-duanya mesti berbentuk serasi untuk ElectionComparisonService.
        $this->assertSame(2, $c->scenarios()->count());
        foreach ($c->fresh('scenarios')->scenarios as $s) {
            $this->assertArrayHasKey('undi', $s->parsed_totals);
            $this->assertArrayHasKey('parties', $s->parsed_totals);
            foreach ($s->parsed_rows as $r) {
                $this->assertArrayHasKey('kawasan', $r);
                $this->assertArrayHasKey('undi', $r);
            }
        }
    }
}
