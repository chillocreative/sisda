<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\VoterScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;
    private Bandar $bandar;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'JOHOR']);
        $this->bandar = Bandar::create(['nama' => 'SEGAMAT', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'BULOH KASAP', 'bandar_id' => $this->bandar->id]);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
    }

    public function test_user_sees_records_in_their_kadun(): void
    {
        $user = $this->makeUser('user');
        HasilCulaan::factory()->create(['kadun' => 'BULOH KASAP', 'bandar' => 'SEGAMAT']);
        HasilCulaan::factory()->create(['kadun' => 'JEMENTAH', 'bandar' => 'SEGAMAT']);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $user)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('BULOH KASAP', $rows->first()->kadun);
    }

    public function test_user_also_sees_records_they_submitted_outside_their_kadun(): void
    {
        $user = $this->makeUser('user');
        HasilCulaan::factory()->create(['kadun' => 'JEMENTAH', 'submitted_by' => $user->id]);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $user)->get();

        $this->assertCount(1, $rows);
    }

    public function test_admin_is_scoped_to_their_bandar_not_their_kadun(): void
    {
        $admin = $this->makeUser('admin');
        HasilCulaan::factory()->create(['kadun' => 'JEMENTAH', 'bandar' => 'SEGAMAT']);
        HasilCulaan::factory()->create(['kadun' => 'LABIS', 'bandar' => 'MUAR']);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $admin)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('SEGAMAT', $rows->first()->bandar);
    }

    public function test_super_admin_sees_everything(): void
    {
        $su = $this->makeUser('super_admin');
        HasilCulaan::factory()->create(['kadun' => 'JEMENTAH', 'bandar' => 'MUAR']);
        HasilCulaan::factory()->create(['kadun' => 'LABIS', 'bandar' => 'JOHOR BAHRU']);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $su)->get();

        $this->assertCount(2, $rows);
    }

    public function test_user_without_a_kadun_sees_nothing_rather_than_everything(): void
    {
        $user = $this->makeUser('user');
        $user->update(['kadun_id' => null]);
        $user->refresh();

        HasilCulaan::factory()->create(['kadun' => 'BULOH KASAP']);

        $rows = VoterScopeService::apply(HasilCulaan::query(), $user->fresh())->get();

        $this->assertCount(0, $rows, 'A user with no Kadun must match nothing, not leak every record.');
    }
}
