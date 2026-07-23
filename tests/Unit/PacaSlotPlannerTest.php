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
