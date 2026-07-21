<?php

namespace Tests\Feature;

use App\Models\KeahlianParti;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulasiPartyOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $user = User::create([
            'name' => 'Super',
            'email' => 'super@example.test',
            'telephone' => '0123456789',
            'password' => bcrypt('rahsia'),
            'role' => 'super_admin',
            'status' => 'approved',
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_party_dropdown_comes_from_master_data(): void
    {
        $this->actingAsSuperAdmin();

        KeahlianParti::create(['nama' => 'Pakatan Harapan', 'sort_order' => 1]);
        KeahlianParti::create(['nama' => 'Parti Sosialis Malaysia', 'sort_order' => 2]);

        $this->get(route('pilihanraya.simulasi'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('simulasiParties', [
                    ['kod' => 'PH', 'nama' => 'Pakatan Harapan'],
                    ['kod' => 'PSM', 'nama' => 'Parti Sosialis Malaysia'],
                ]));
    }

    /** The master table is per-Bandar, so one coalition can appear many times. */
    public function test_duplicate_names_across_bandar_appear_once(): void
    {
        $this->actingAsSuperAdmin();

        KeahlianParti::create(['nama' => 'Pakatan Harapan', 'sort_order' => 1]);
        KeahlianParti::create(['nama' => 'PAKATAN HARAPAN', 'sort_order' => 2]);

        $this->get(route('pilihanraya.simulasi'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('simulasiParties', [['kod' => 'PH', 'nama' => 'Pakatan Harapan']]));
    }

    /** An empty master table must not produce an empty dropdown. */
    public function test_falls_back_to_the_builtin_line_up_when_master_data_is_empty(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('pilihanraya.simulasi'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('simulasiParties', 6)
                ->where('simulasiParties.0', ['kod' => 'PH', 'nama' => 'Pakatan Harapan']));
    }
}
