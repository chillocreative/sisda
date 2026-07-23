<?php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\PacaForm;
use App\Services\Pilihanraya\PacaBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PacaBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function form(): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn', 'tahun' => 2027,
            'penjuru' => 2, 'parties' => [], 'status' => 'published', 'source' => 'scoresheet',
            'structure' => ['rows' => [
                ['dm' => '041/03/01', 'pusat' => 'SK BUMBUNG LIMA', 'saluran' => '1'],
                ['dm' => '041/03/01', 'pusat' => 'SK BUMBUNG LIMA', 'saluran' => '2'],
                ['dm' => '041/03/02', 'pusat' => 'SK PAYA KELADI', 'saluran' => '1'],
                ['dm' => '', 'pusat' => '', 'saluran' => 'UNDI POS'], // sentinel — must be skipped
            ]],
        ]);
    }

    public function test_build_seeds_pusat_saluran_and_default_slots(): void
    {
        $paca = app(PacaBuilderService::class)->buildFrom($this->form());

        // Two real pusat, sentinel excluded.
        $this->assertSame(2, $paca->pusatList()->count());
        $bumbung = $paca->pusatList()->where('pusat', 'SK BUMBUNG LIMA')->first();
        $this->assertSame(2, $bumbung->saluranList()->count());
        // Each saluran seeded with 4 default slots ending in CA.
        $saluran = $bumbung->saluranList()->first();
        $this->assertSame(['PA1','PA2','PA3','CA'], $saluran->slots()->pluck('jawatan')->all());
        $this->assertNotEmpty($bumbung->public_token);
    }

    public function test_build_is_idempotent_and_never_reseeds(): void
    {
        $svc = app(PacaBuilderService::class);
        $form = $this->form();
        // Guna semula instans Borang14Form yang SAMA — bukan panggil form()
        // kali kedua, yang akan mencipta baris kedua dan melanggar unique
        // index borang14_forms_election_unique (kawasan_type, kawasan_id,
        // jenis_pr, tahun). Niat ujian ini ialah idempotency buildFrom() bagi
        // SATU kerusi/PR, bukan penciptaan Borang14Form berganda.
        $first = $svc->buildFrom($form);
        $first->pusatList()->first()->update(['ketua_nama' => 'DIEDIT']);

        $again = $svc->buildFrom($form);
        $this->assertSame($first->id, $again->id);
        $this->assertSame('DIEDIT', $again->pusatList()->first()->ketua_nama);
        $this->assertSame(1, PacaForm::count());
    }

    public function test_seats_with_scoresheet_lists_only_structured_forms(): void
    {
        $this->form(); // has structure
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => 99, 'jenis_pr' => 'prn', 'tahun' => 2027,
            'penjuru' => 2, 'parties' => [], 'status' => 'draft', 'source' => 'manual', 'structure' => null,
        ]);
        $seats = app(PacaBuilderService::class)->seatsWithScoresheet();
        $this->assertCount(1, $seats);
        $this->assertSame(41, $seats[0]['kawasan_id']);
    }

    /**
     * seatsWithScoresheet(bandarId) mesti lingkupkan pemilih admin biasa
     * kepada kerusi Bandar-nya sahaja; bandarId=null (super_admin) pulangkan
     * kesemuanya.
     */
    public function test_seats_with_scoresheet_scopes_by_bandar(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $bandarX = Bandar::create(['nama' => 'Bandar X', 'negeri_id' => $negeri->id]);
        $bandarY = Bandar::create(['nama' => 'Bandar Y', 'negeri_id' => $negeri->id]);
        $kadunX = Kadun::create(['nama' => 'Kadun X', 'bandar_id' => $bandarX->id]);
        $kadunY = Kadun::create(['nama' => 'Kadun Y', 'bandar_id' => $bandarY->id]);

        $rows = ['rows' => [['dm' => '041/03/01', 'pusat' => 'SK BUMBUNG LIMA', 'saluran' => '1']]];

        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadunX->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
            'penjuru' => 2, 'parties' => [], 'status' => 'published', 'source' => 'scoresheet', 'structure' => $rows,
        ]);
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadunY->id, 'jenis_pr' => 'prn', 'tahun' => 2027,
            'penjuru' => 2, 'parties' => [], 'status' => 'published', 'source' => 'scoresheet', 'structure' => $rows,
        ]);

        $svc = app(PacaBuilderService::class);

        $seatsX = $svc->seatsWithScoresheet($bandarX->id);
        $this->assertCount(1, $seatsX);
        $this->assertSame($kadunX->id, $seatsX[0]['kawasan_id']);

        $seatsAll = $svc->seatsWithScoresheet();
        $this->assertCount(2, $seatsAll);
    }
}
