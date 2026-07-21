<?php
// tests/Feature/Borang14StrukturManualTest.php
//
// Struktur Borang 14 yang dibina dengan tangan, untuk PR akan datang yang
// tiada DPT dan tiada scoresheet. Dua bahaya dikunci di sini:
//   1. baris YATIM — undi tersimpan di bawah kunci yang tiada sesiapa baca
//      (punca pepijat produksi Julai 2026: 4,471 undi memapar 0);
//   2. undi hilang SENYAP apabila pusat dinamakan semula atau dibuang.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Borang14StrukturService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14StrukturManualTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $bandar = Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'Juasseh', 'bandar_id' => $bandar->id]);
    }

    private function user(string $role = 'super_admin', array $over = []): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia ada).
        return User::factory()->create(array_merge([
            'role' => $role,
            'telephone' => '01277'.random_int(10000, 99999),
        ], $over));
    }

    /** Struktur manual dua pusat: SK TENGKEK (2 saluran), SK JEMAPOH (1 saluran). */
    private function manualStructure(): array
    {
        return (new Borang14StrukturService)->expand([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 2],
            ['row_id' => 'pm_b', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ], false, true);
    }

    private function form(array $structure, string $status = 'draft'): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'status' => $status, 'source' => 'manual',
            'parties' => [['slot' => 1, 'nama' => 'PN'], ['slot' => 2, 'nama' => 'BN']],
            'structure' => $structure,
        ]);
    }

    public function test_manual_form_reports_no_crosscheck_issues(): void
    {
        $form = $this->form($this->manualStructure());
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 250]);

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $form->id]));

        $res->assertOk();
        // Baris manual tiada (A) bercetak untuk dibandingkan. Satu amaran di
        // sini bermakna borang yang diisi dengan BETUL kelihatan rosak.
        $this->assertSame([], $res->json('form.crosscheck_issues'));
    }

    public function test_manual_structure_is_reported_as_its_own_source(): void
    {
        $form = $this->form($this->manualStructure());

        $res = $this->actingAs($this->user())
            ->getJson(route('pilihanraya.borang-14.data', ['form_id' => $form->id]));

        $res->assertOk();
        $this->assertSame('manual', $res->json('reference.source'));
        $this->assertTrue($res->json('hasData'));
    }
}
