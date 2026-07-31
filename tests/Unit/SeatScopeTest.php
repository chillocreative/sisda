<?php

namespace Tests\Unit;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Support\SeatScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SeatScope is the ONLY place the seat-permission rule is written. Every
 * scoreboard endpoint calls it, so this matrix is the feature's whole risk
 * surface. Three failure modes matter most: a null seat column must DENY
 * (the July 2026 IDORs were guards gated on nullable fields), an unapproved
 * user gets nothing regardless of role, and seats()/allows() must never
 * disagree — a seat absent from the picker must not be writable by hand.
 */
class SeatScopeTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $bandarA;
    private Bandar $bandarB;
    private Kadun $dunA1;
    private Kadun $dunA2;
    private Kadun $dunB1;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->bandarA = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $this->bandarB = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P130', 'negeri_id' => $negeri->id]);
        $this->dunA1 = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $this->bandarA->id]);
        $this->dunA2 = Kadun::create(['nama' => 'JOHOL', 'kod_dun' => 'N26', 'bandar_id' => $this->bandarA->id]);
        $this->dunB1 = Kadun::create(['nama' => 'BAHAU', 'kod_dun' => 'N31', 'bandar_id' => $this->bandarB->id]);
    }

    /** Built by hand, not by factory: UserFactory omits the NOT NULL telephone column. */
    private function user(string $role, ?int $bandarId = null, ?int $kadunId = null, string $status = 'approved'): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "Pengguna {$n}",
            'email' => "pengguna{$n}@example.test",
            'telephone' => '01300000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => $status,
            'bandar_id' => $bandarId,
            'kadun_id' => $kadunId,
        ]);
    }

    public function test_super_admin_may_touch_every_seat(): void
    {
        $u = $this->user('super_admin');

        $this->assertTrue(SeatScope::allows($u, SeatScope::DUN, $this->dunB1->id));
        $this->assertTrue(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarB->id));
    }

    public function test_admin_is_confined_to_own_parlimen_and_its_duns(): void
    {
        $u = $this->user('admin', bandarId: $this->bandarA->id);

        $this->assertTrue(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarA->id));
        $this->assertTrue(SeatScope::allows($u, SeatScope::DUN, $this->dunA1->id));
        $this->assertTrue(SeatScope::allows($u, SeatScope::DUN, $this->dunA2->id));

        $this->assertFalse(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarB->id));
        $this->assertFalse(SeatScope::allows($u, SeatScope::DUN, $this->dunB1->id));
    }

    public function test_user_and_super_user_get_only_their_own_dun(): void
    {
        foreach (['user', 'super_user'] as $role) {
            $u = $this->user($role, kadunId: $this->dunA1->id);

            $this->assertTrue(SeatScope::allows($u, SeatScope::DUN, $this->dunA1->id), $role);
            $this->assertFalse(SeatScope::allows($u, SeatScope::DUN, $this->dunA2->id), $role);
            $this->assertFalse(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarA->id), $role);
        }
    }

    public function test_ketua_paca_dun_gets_nothing(): void
    {
        $u = $this->user('ketua_paca_dun', kadunId: $this->dunA1->id);

        $this->assertFalse(SeatScope::allows($u, SeatScope::DUN, $this->dunA1->id));
        $this->assertSame([], SeatScope::seats($u));
    }

    public function test_null_seat_column_denies_instead_of_matching_everything(): void
    {
        $admin = $this->user('admin', bandarId: null);
        $this->assertFalse(SeatScope::allows($admin, SeatScope::PARLIMEN, $this->bandarA->id));
        $this->assertFalse(SeatScope::allows($admin, SeatScope::DUN, $this->dunA1->id));
        $this->assertSame([], SeatScope::seats($admin));

        $plain = $this->user('user', kadunId: null);
        $this->assertFalse(SeatScope::allows($plain, SeatScope::DUN, $this->dunA1->id));
        $this->assertSame([], SeatScope::seats($plain));
    }

    public function test_unapproved_user_gets_nothing_regardless_of_role(): void
    {
        $u = $this->user('admin', bandarId: $this->bandarA->id, status: 'pending');

        $this->assertFalse(SeatScope::allows($u, SeatScope::PARLIMEN, $this->bandarA->id));
        $this->assertSame([], SeatScope::seats($u));
    }

    public function test_guest_and_unknown_seat_type_are_denied(): void
    {
        $this->assertFalse(SeatScope::allows(null, SeatScope::DUN, $this->dunA1->id));
        $this->assertSame([], SeatScope::seats(null));

        $u = $this->user('super_admin');
        $this->assertFalse(SeatScope::allows($u, 'negeri', 1));
    }

    public function test_seats_and_allows_never_disagree(): void
    {
        $users = [
            $this->user('super_admin'),
            $this->user('admin', bandarId: $this->bandarA->id),
            $this->user('super_user', kadunId: $this->dunA1->id),
            $this->user('user', kadunId: $this->dunB1->id),
        ];

        $everySeat = [
            [SeatScope::PARLIMEN, $this->bandarA->id],
            [SeatScope::PARLIMEN, $this->bandarB->id],
            [SeatScope::DUN, $this->dunA1->id],
            [SeatScope::DUN, $this->dunA2->id],
            [SeatScope::DUN, $this->dunB1->id],
        ];

        foreach ($users as $u) {
            $listed = collect(SeatScope::seats($u))->map(fn ($s) => $s['type'].':'.$s['id'])->all();

            foreach ($everySeat as [$type, $id]) {
                $inPicker = in_array($type.':'.$id, $listed, true);
                $this->assertSame(
                    $inPicker,
                    SeatScope::allows($u, $type, $id),
                    "Peranan {$u->role}: seats() dan allows() bercanggah pada {$type}:{$id}",
                );
            }
        }
    }

    public function test_assert_aborts_with_403_for_a_foreign_seat(): void
    {
        $u = $this->user('user', kadunId: $this->dunA1->id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        SeatScope::assert($u, SeatScope::DUN, $this->dunB1->id);
    }

    public function test_seats_carries_the_display_name_and_code(): void
    {
        $u = $this->user('user', kadunId: $this->dunA1->id);

        $this->assertSame(
            [['type' => 'dun', 'id' => $this->dunA1->id, 'nama' => 'PILAH', 'kod' => 'N27']],
            SeatScope::seats($u),
        );
    }
}
