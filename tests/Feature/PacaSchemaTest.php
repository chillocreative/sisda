<?php
namespace Tests\Feature;

use App\Models\PacaForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PacaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_full_paca_tree_persists_and_reads_back(): void
    {
        $form = PacaForm::create([
            'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn', 'tahun' => 2027,
        ]);
        $pusat = $form->pusatList()->create([
            'dm' => '041/03/01', 'pusat' => 'SK BUMBUNG LIMA',
            'public_token' => 'tok_'.str_repeat('a', 28), 'urutan' => 1,
        ]);
        $saluran = $pusat->saluranList()->create(['label' => '1', 'urutan' => 1]);
        $slot = $saluran->slots()->create([
            'jawatan' => 'PA1', 'masa_mula' => '08:00', 'masa_tamat' => '10:00', 'urutan' => 1,
            'petugas_nama' => 'AZMI', 'petugas_kp' => '680623-07-5749',
            'petugas_tel' => '010-2187454', 'petugas_parti' => 'KEADILAN',
        ]);

        $this->assertSame(1, $form->pusatList()->count());
        $this->assertSame('PA1', $slot->jawatan);
        // Relation walk up the tree: slot → saluran → pusat.
        $this->assertSame('SK BUMBUNG LIMA', $slot->saluran->pusat->pusat);
        $this->assertNull($saluran->slots()->create(['jawatan' => 'CA', 'urutan' => 2])->masa_tamat);
    }

    public function test_public_token_is_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $f = PacaForm::create(['kawasan_type' => 'dun', 'kawasan_id' => 1, 'jenis_pr' => 'prn', 'tahun' => 2027]);
        $f->pusatList()->create(['dm' => 'a', 'pusat' => 'A', 'public_token' => 'dup', 'urutan' => 1]);
        $f->pusatList()->create(['dm' => 'b', 'pusat' => 'B', 'public_token' => 'dup', 'urutan' => 2]);
    }
}
