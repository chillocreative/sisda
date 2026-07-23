<?php
namespace Tests\Unit;

use App\Services\Pilihanraya\PacaSlotPlanner;
use Tests\TestCase;

class PacaSlotPlannerTest extends TestCase
{
    private PacaSlotPlanner $p;
    protected function setUp(): void { parent::setUp(); $this->p = new PacaSlotPlanner; }

    public function test_default_is_pa1_pa2_pa3_then_ca(): void
    {
        $slots = $this->p->defaultSlots();
        $this->assertSame(['PA1','PA2','PA3','CA'], array_column($slots, 'jawatan'));
    }

    public function test_defaults_start_at_8_in_two_hour_blocks_ca_open_ended(): void
    {
        $slots = $this->p->defaultSlots();
        $this->assertSame(['08:00','10:00'], [$slots[0]['masa_mula'], $slots[0]['masa_tamat']]);
        $this->assertSame('14:00', $slots[3]['masa_mula']);
        $this->assertNull($slots[3]['masa_tamat']); // CA ends "selesai"
    }

    public function test_last_slot_is_always_ca_after_relabel(): void
    {
        // Five slots must be PA1..PA4 + CA.
        $five = $this->p->defaultSlots(5);
        $this->assertSame(['PA1','PA2','PA3','PA4','CA'], array_column($five, 'jawatan'));

        // Relabel keeps order-derived jawatan even if input jawatan is wrong.
        $mislabelled = [
            ['jawatan'=>'CA','urutan'=>1], ['jawatan'=>'PA1','urutan'=>2], ['jawatan'=>'PA2','urutan'=>3],
        ];
        $this->assertSame(['PA1','PA2','CA'], array_column($this->p->relabel($mislabelled), 'jawatan'));
    }

    public function test_relabel_preserves_times_and_petugas_data(): void
    {
        // relabel() hanya tukar jawatan; masa dan petugas yang sudah diisi
        // MESTI kekal — jika tidak, menambah PA akan memadam data petugas.
        $in = [[
            'jawatan' => 'salah', 'urutan' => 1, 'masa_mula' => '08:00', 'masa_tamat' => '10:30',
            'petugas_nama' => 'AZMI', 'petugas_kp' => '680623-07-5749',
        ]];
        $out = $this->p->relabel($in)[0];

        $this->assertSame('CA', $out['jawatan']);        // satu slot -> CA
        $this->assertSame('08:00', $out['masa_mula']);
        $this->assertSame('10:30', $out['masa_tamat']);
        $this->assertSame('AZMI', $out['petugas_nama']);
        $this->assertSame('680623-07-5749', $out['petugas_kp']);
    }

    public function test_default_count_of_one_is_just_ca(): void
    {
        $slots = $this->p->defaultSlots(1);
        $this->assertSame(['CA'], array_column($slots, 'jawatan'));
        $this->assertNull($slots[0]['masa_tamat']);
    }

    public function test_minimum_accepts_non_zero_padded_typed_times(): void
    {
        // Masa yang ditaip pengguna boleh datang sebagai '9:30' bukan '09:30'.
        // 08:00 -> 9:30 ialah 90 minit, mesti gagal minimum 2 jam.
        $this->assertFalse($this->p->minimumMet(['jawatan'=>'PA1','masa_mula'=>'8:00','masa_tamat'=>'9:30']));
        $this->assertTrue($this->p->minimumMet(['jawatan'=>'PA1','masa_mula'=>'8:00','masa_tamat'=>'10:00']));
    }

    public function test_minimum_two_hours_enforced_for_pa_not_ca(): void
    {
        $this->assertFalse($this->p->minimumMet(['jawatan'=>'PA1','masa_mula'=>'08:00','masa_tamat'=>'09:30']));
        $this->assertTrue($this->p->minimumMet(['jawatan'=>'PA1','masa_mula'=>'08:00','masa_tamat'=>'10:00']));
        // CA (no tamat) is exempt.
        $this->assertTrue($this->p->minimumMet(['jawatan'=>'CA','masa_mula'=>'14:00','masa_tamat'=>null]));
        // A PA with no tamat yet (unfilled) is not a violation.
        $this->assertTrue($this->p->minimumMet(['jawatan'=>'PA2','masa_mula'=>'10:00','masa_tamat'=>null]));
    }
}
