<?php

namespace Tests\Unit;

use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Services\Pilihanraya\Borang14ScenarioMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14ScenarioMapperTest extends TestCase
{
    use RefreshDatabase;

    /** Bina borang bersumber scoresheet yang meniru Juasseh PRN 2023. */
    private function juassehForm(): Borang14Form
    {
        $negeri = \App\Models\Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = \App\Models\Bandar::create(['nama' => 'P.129', 'negeri_id' => $negeri->id]);
        $kadun  = \App\Models\Kadun::create(['nama' => 'JUASSEH', 'bandar_id' => $bandar->id]);

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'scoresheet',
            'parties' => [
                ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'PERIKATAN NASIONAL'],
                ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PAKATAN HARAPAN'],
            ],
            'structure' => [
                'jumlah_pemilih' => 13408,
                'rows' => [
                    ['dm' => null, 'pusat' => '', 'saluran' => 'UNDI POS'],
                    ['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SK TENGKEK', 'saluran' => '1'],
                    ['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SK TENGKEK', 'saluran' => '2'],
                    ['dm' => 'KAMPONG TAPAK', 'pusat' => 'SK TAPAK', 'saluran' => '1'],
                ],
            ],
        ]);

        $cells = [
            // [pusat, saluran, slot, undi]
            ['',          'UNDI POS', 1, 98], ['',          'UNDI POS', 2, 73], ['',          'UNDI POS', 90, 18], ['', 'UNDI POS', 91, 14],
            ['SK TENGKEK', '1', 1, 48], ['SK TENGKEK', '1', 2, 76], ['SK TENGKEK', '1', 90, 3],
            ['SK TENGKEK', '2', 1, 102], ['SK TENGKEK', '2', 2, 108], ['SK TENGKEK', '2', 90, 1],
            ['SK TAPAK',   '1', 1, 42], ['SK TAPAK',   '1', 2, 51], ['SK TAPAK',   '1', 90, 0],
        ];
        foreach ($cells as [$pusat, $saluran, $slot, $undi]) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id, 'pusat' => $pusat,
                'saluran' => $saluran, 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        return $form->fresh();
    }

    public function test_maps_per_daerah_mengundi_not_per_saluran(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());

        $kawasan = array_column($out['rows'], 'kawasan');
        sort($kawasan);
        $this->assertSame(['KAMPONG TAPAK', 'KAMPONG TENGKEK', 'UNDI POS'], $kawasan);

        // Tengkek = dua saluran dijumlahkan: PN 48+102=150, PH 76+108=184, ditolak 3+1=4
        $tengkek = collect($out['rows'])->firstWhere('kawasan', 'KAMPONG TENGKEK');
        $this->assertSame(150, $tengkek['undi']['PERIKATAN NASIONAL']);
        $this->assertSame(184, $tengkek['undi']['PAKATAN HARAPAN']);
        $this->assertSame(4, $tengkek['ditolak']);
        $this->assertSame(150 + 184 + 4, $tengkek['keluar']);
    }

    public function test_undi_pos_is_its_own_row(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());
        $pos = collect($out['rows'])->firstWhere('kawasan', 'UNDI POS');

        $this->assertNotNull($pos);
        $this->assertSame(98, $pos['undi']['PERIKATAN NASIONAL']);
        $this->assertSame(73, $pos['undi']['PAKATAN HARAPAN']);
        $this->assertSame(18, $pos['ditolak']);
        $this->assertSame(98 + 73 + 18, $pos['keluar']);
    }

    public function test_slots_90_and_91_never_become_parties(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());

        foreach ($out['rows'] as $r) {
            $this->assertSame(['PERIKATAN NASIONAL', 'PAKATAN HARAPAN'], array_keys($r['undi']));
        }
        $this->assertSame(['PERIKATAN NASIONAL', 'PAKATAN HARAPAN'], $out['totals']['parties']);
    }

    public function test_pemilih_is_null_when_no_berdaftar_is_known(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());

        foreach ($out['rows'] as $r) {
            $this->assertNull($r['pemilih'], 'Scoresheet tiada berdaftar per DM — mesti null, bukan 0.');
        }
    }

    public function test_totals_pemilih_comes_from_scoresheet_header(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());
        $this->assertSame(13408, $out['totals']['pemilih']);
    }

    public function test_totals_sum_every_row(): void
    {
        $out = app(Borang14ScenarioMapper::class)->map($this->juassehForm());

        // PN 98+48+102+42 = 290 ; PH 73+76+108+51 = 308 ; ditolak 18+3+1+0 = 22
        $this->assertSame(290, $out['totals']['undi']['PERIKATAN NASIONAL']);
        $this->assertSame(308, $out['totals']['undi']['PAKATAN HARAPAN']);
        $this->assertSame(22, $out['totals']['ditolak']);
        $this->assertSame(290 + 308 + 22, $out['totals']['keluar']);
    }

    public function test_form_with_no_party_names_is_rejected(): void
    {
        $form = $this->juassehForm();
        $form->update(['parties' => [['slot' => 1, 'keahlian_parti_id' => null, 'nama' => null]]]);

        $this->expectException(\RuntimeException::class);
        app(Borang14ScenarioMapper::class)->map($form->fresh());
    }

    public function test_form_with_no_structure_is_rejected(): void
    {
        $form = $this->juassehForm();
        $form->update(['structure' => null]);

        $this->expectException(\RuntimeException::class);
        app(Borang14ScenarioMapper::class)->map($form->fresh());
    }
}
