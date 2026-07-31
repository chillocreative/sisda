<?php
// tests/Feature/Borang14ReferencePriorityTest.php
//
// Kegagalan produksi 21/7/2026: selepas memuat naik scoresheet Juasseh, skrin
// Keyin memaparkan baris DPT ("DM: PELANGAI / KG KUALA JEMAPOH / Saluran 1")
// dengan SETIAP sel 0, dan Ringkasan hanya menunjukkan 98/73/203 — baris
// UNDI POS, satu-satunya baris yang labelnya kebetulan sepadan.
//
// Undi tidak pernah hilang. Ia ditulis di bawah kunci scoresheet
// ("SEKOLAH KEBANGSAAN TENGKEK|1|1") sedangkan grid dibina daripada kunci DPT.
// Ujian ini mengunci keutamaan yang membetulkannya.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Borang14ReferencePriorityTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $bandar = Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'JUASSEH', 'bandar_id' => $bandar->id]);
    }

    /** Roll DPT bagi kerusi ini — Lokaliti menjadi Pusat Mengundi, satu saluran setiap satu. */
    private function seedDpt(): void
    {
        foreach (['KG KUALA JEMAPOH', 'KG PELANGAI HILIR'] as $lokaliti) {
            DB::table('pangkalan_data_pengundi')->insert([
                'nama' => 'PENGUNDI '.$lokaliti,
                'no_ic' => (string) random_int(100000000000, 999999999999),
                'kadun' => 'JUASSEH',
                'daerah_mengundi' => 'PELANGAI',
                'lokaliti' => $lokaliti,
                'is_deceased' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Borang dengan struktur scoresheet sebenar: 2 saluran dalam satu Pusat Mengundi. */
    private function formWithScoresheet(): Borang14Form
    {
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => 'published', 'source' => 'scoresheet',
            'parties' => [['slot' => 1, 'nama' => 'PN'], ['slot' => 2, 'nama' => 'BN']],
            'structure' => ['rows' => [
                ['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SEKOLAH KEBANGSAAN TENGKEK', 'saluran' => '1'],
                ['dm' => 'KAMPONG TENGKEK', 'pusat' => 'SEKOLAH KEBANGSAAN TENGKEK', 'saluran' => '2'],
                ['dm' => null, 'pusat' => '', 'saluran' => 'UNDI POS'],
            ]],
        ]);

        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SEKOLAH KEBANGSAAN TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 48]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SEKOLAH KEBANGSAAN TENGKEK', 'saluran' => '2', 'slot' => 1, 'undi' => 102]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 1, 'undi' => 98]);

        return $form;
    }

    private function payload(): array
    {
        $user = User::factory()->create(['role' => 'super_admin', 'telephone' => '0123457777']);

        return $this->actingAs($user)->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023,
        ]))->assertOk()->json();
    }

    /**
     * INI ujian regresi bagi pepijat itu: DPT wujud DAN scoresheet wujud —
     * scoresheet mesti menang.
     */
    public function test_the_scoresheet_structure_beats_the_dpt_estimate(): void
    {
        $this->seedDpt();
        $this->formWithScoresheet();

        $data = $this->payload();

        $this->assertSame('scoresheet', $data['reference']['source']);

        $pusat = collect($data['reference']['daerah_mengundi'])
            ->flatMap(fn ($dm) => array_column($dm['pusat_mengundi'], 'nama'))->all();

        $this->assertContains('SEKOLAH KEBANGSAAN TENGKEK', $pusat);
        $this->assertNotContains('KG KUALA JEMAPOH', $pusat, 'baris DPT tidak boleh menggantikan pecahan rasmi');
    }

    /**
     * Kunci sel yang dirender MESTI sepadan dengan kunci undi yang tersimpan —
     * ketidakpadanan itulah yang membuatkan setiap sel memaparkan 0.
     */
    public function test_every_stored_vote_key_exists_in_the_rendered_reference(): void
    {
        $this->seedDpt();
        $form = $this->formWithScoresheet();

        $data = $this->payload();

        $dirender = [];
        foreach ($data['reference']['daerah_mengundi'] as $dm) {
            foreach ($dm['pusat_mengundi'] as $pm) {
                foreach ($pm['saluran'] as $s) {
                    $dirender[] = $pm['nama'].'|'.$s['no'];
                }
            }
        }

        foreach ($form->votes()->where('pusat', '!=', '')->get() as $v) {
            $this->assertContains($v->pusat.'|'.$v->saluran, $dirender,
                "undi tersimpan di bawah {$v->pusat}|{$v->saluran} tetapi tiada baris sepadan dirender");
        }

        // Dan undi itu memang boleh dibaca semula melalui kunci yang sama.
        $this->assertSame(48, $data['votes']['dun|SEKOLAH KEBANGSAAN TENGKEK|1|1']);
        $this->assertSame(102, $data['votes']['dun|SEKOLAH KEBANGSAAN TENGKEK|2|1']);
    }

    /** Tanpa scoresheet, anggaran DPT masih digunakan — ia lebih baik daripada tiada apa-apa. */
    public function test_the_dpt_estimate_is_still_used_when_there_is_no_scoresheet(): void
    {
        $this->seedDpt();

        $data = $this->payload();

        $this->assertSame('dpt_estimate', $data['reference']['source']);
        $pusat = collect($data['reference']['daerah_mengundi'])
            ->flatMap(fn ($dm) => array_column($dm['pusat_mengundi'], 'nama'))->all();
        $this->assertContains('KG KUALA JEMAPOH', $pusat);
    }
}
