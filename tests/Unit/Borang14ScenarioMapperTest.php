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
            // keahlian_parti_id sudah dipetakan (bukan null) — borang belum
            // dipetakan mesti ditolak mapper (lihat ujian di bawah), jadi
            // fixture "berjaya" ini mesti mewakili keadaan SELEPAS dipetakan.
            'parties' => [
                ['slot' => 1, 'keahlian_parti_id' => 101, 'nama' => 'PERIKATAN NASIONAL'],
                ['slot' => 2, 'keahlian_parti_id' => 102, 'nama' => 'PAKATAN HARAPAN'],
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

    /**
     * Finding 2: scoresheet import seeds parties[].nama with the CANDIDATE's
     * own name as a placeholder — keahlian_parti_id stays null until a human
     * maps it in Keyin. A form still carrying an unmapped slot must never be
     * fed to the AI as if both sides were real parties.
     */
    public function test_form_with_unmapped_party_is_rejected(): void
    {
        $form = $this->juassehForm();
        $form->update(['parties' => [
            ['slot' => 1, 'keahlian_parti_id' => null, 'nama' => 'EDDIN SYAZLEE BIN SHITH'], // calon, belum dipetakan
            ['slot' => 2, 'keahlian_parti_id' => 102, 'nama' => 'PAKATAN HARAPAN'],
        ]]);

        $this->expectException(\RuntimeException::class);
        app(Borang14ScenarioMapper::class)->map($form->fresh());
    }

    /**
     * Finding 1 (CRITICAL): a hand-keyed form (saveParties()/putVote() —
     * never writes `structure`) on a seat that HAS a curated Borang14Reference
     * must yield a REAL totals.pemilih, not null (which ElectionComparisonService
     * would then coerce to 0 and hand the AI a fabricated "-100%" turnout claim).
     */
    public function test_totals_pemilih_uses_curated_reference_for_hand_keyed_form(): void
    {
        $kadunId = 900001;
        $path = resource_path("data/borang14/kadun-{$kadunId}.json");
        file_put_contents($path, json_encode([
            'negeri' => 'NEGERI SEMBILAN', 'parlimen' => 'P.129', 'dun' => 'JUASSEH UJIAN',
            'daerah_mengundi' => [
                ['nama' => 'KAMPONG TENGKEK', 'pusat_mengundi' => [
                    ['nama' => 'SK TENGKEK', 'saluran' => [['no' => 1, 'berdaftar' => 500], ['no' => 2, 'berdaftar' => 600]]],
                ]],
                ['nama' => 'KAMPONG TAPAK', 'pusat_mengundi' => [
                    ['nama' => 'SK TAPAK', 'saluran' => [['no' => 1, 'berdaftar' => 400]]],
                ]],
            ],
            'undi_awal' => ['berdaftar' => 50],
            'undi_pos' => ['berdaftar' => 30],
        ]));

        try {
            $form = Borang14Form::create([
                'kawasan_type' => 'dun', 'kawasan_id' => $kadunId,
                'jenis_pr' => 'prn', 'tahun' => 2026, 'penjuru' => 2,
                'status' => 'published', 'source' => 'manual', 'structure' => null,
                'parties' => [
                    ['slot' => 1, 'keahlian_parti_id' => 101, 'nama' => 'PERIKATAN NASIONAL'],
                    ['slot' => 2, 'keahlian_parti_id' => 102, 'nama' => 'PAKATAN HARAPAN'],
                ],
            ]);
            foreach ([
                ['SK TENGKEK', '1', 1, 10], ['SK TENGKEK', '1', 2, 20],
                ['SK TAPAK', '1', 1, 5], ['SK TAPAK', '1', 2, 5],
                ['', 'UNDI POS', 1, 1], ['', 'UNDI POS', 2, 1],
            ] as [$pusat, $saluran, $slot, $undi]) {
                Borang14Vote::create([
                    'borang14_form_id' => $form->id, 'pusat' => $pusat,
                    'saluran' => $saluran, 'slot' => $slot, 'undi' => $undi,
                ]);
            }

            $out = app(Borang14ScenarioMapper::class)->map($form->fresh());

            // 500+600+400 (daerah_mengundi) + 50 (undi_awal) + 30 (undi_pos) = 1580.
            $this->assertSame(1580, $out['totals']['pemilih'], 'Borang tanpa structure di kerusi rujukan-kurasi mesti dapat jumlah pemilih SEBENAR, bukan null/0.');
        } finally {
            @unlink($path);
        }
    }

    /**
     * Finding 3: a DPT-derived reference is an APPROXIMATION (its own docblock
     * says callers must disclaim it) — the mapper must not hand it to the AI
     * as a certain figure. Falls through to the scoresheet header instead.
     */
    public function test_dpt_estimate_reference_is_not_used_for_totals_pemilih(): void
    {
        $negeri = \App\Models\Negeri::create(['nama' => 'NEGERI SEMBILAN DPT']);
        $bandar = \App\Models\Bandar::create(['nama' => 'P.DPT', 'negeri_id' => $negeri->id]);
        $kadun  = \App\Models\Kadun::create(['nama' => 'DPT UJIAN', 'bandar_id' => $bandar->id]);

        $user = \App\Models\User::factory()->create(['telephone' => '0123456789']);
        $batch = \App\Models\UploadBatch::create([
            'nama_fail' => 'ujian.csv', 'fail_path' => 'ujian.csv',
            'jumlah_rekod' => 1, 'status' => 'completed', 'is_active' => true, 'uploaded_by' => $user->id,
        ]);
        \App\Models\PangkalanDataPengundi::create([
            'upload_batch_id' => $batch->id, 'no_ic' => '900101011234', 'nama' => 'Pengundi Satu',
            'lokaliti' => 'Kampung Ujian', 'daerah_mengundi' => 'KAMPONG TENGKEK', 'kadun' => 'DPT UJIAN', 'negeri' => 'NEGERI SEMBILAN DPT',
        ]);

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2026, 'penjuru' => 2,
            'status' => 'published', 'source' => 'manual',
            'structure' => ['jumlah_pemilih' => 999],
            'parties' => [
                ['slot' => 1, 'keahlian_parti_id' => 101, 'nama' => 'PERIKATAN NASIONAL'],
                ['slot' => 2, 'keahlian_parti_id' => 102, 'nama' => 'PAKATAN HARAPAN'],
            ],
        ]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 1, 'undi' => 5]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 2, 'undi' => 5]);

        $out = app(Borang14ScenarioMapper::class)->map($form->fresh());

        // Rujukan yang wujud ialah dpt_estimate — mesti diabaikan untuk pemilih,
        // jatuh balik kepada kepala scoresheet (999), bukan angka anggaran DPT.
        $this->assertSame(999, $out['totals']['pemilih']);
    }

    /**
     * Finding 4: a brand-new election of a seat whose Keyin structure was
     * INHERITED from a previous election (no curated/DPT reference, no own
     * structure) must not fail closed with "Struktur saluran tiada" when the
     * Keyin screen itself resolves fine via the same inheritance fallback.
     */
    public function test_maps_using_structure_inherited_from_a_previous_election_of_the_same_seat(): void
    {
        $negeri = \App\Models\Negeri::create(['nama' => 'NEGERI SEMBILAN WARISI']);
        $bandar = \App\Models\Bandar::create(['nama' => 'P.WARISI', 'negeri_id' => $negeri->id]);
        $kadun  = \App\Models\Kadun::create(['nama' => 'WARISI UJIAN', 'bandar_id' => $bandar->id]);

        // Pilihan raya SUMBER (2023) — ada structure sendiri (dimuat naik scoresheet).
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'scoresheet',
            'structure' => [
                'rows' => [
                    ['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SK TENGKEK', 'saluran' => '1'],
                ],
            ],
        ]);

        // Pilihan raya BAHARU (2026) — dikeyin tangan, TIADA structure sendiri.
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2026, 'penjuru' => 2,
            'status' => 'published', 'source' => 'manual', 'structure' => null,
            'parties' => [
                ['slot' => 1, 'keahlian_parti_id' => 101, 'nama' => 'PERIKATAN NASIONAL'],
                ['slot' => 2, 'keahlian_parti_id' => 102, 'nama' => 'PAKATAN HARAPAN'],
            ],
        ]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 40]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 2, 'undi' => 60]);

        $out = app(Borang14ScenarioMapper::class)->map($form->fresh());

        $kawasan = array_column($out['rows'], 'kawasan');
        $this->assertSame(['KAMPONG TENGKEK'], $kawasan, 'Mesti gunakan DM yang diwarisi daripada structure pilihan raya 2023, bukan gagal dengan "Struktur saluran tiada".');
        $this->assertNull($out['rows'][0]['pemilih'], 'Tiada rujukan berdaftar untuk kerusi ini — pemilih baris mesti null, bukan direka.');
    }
}
