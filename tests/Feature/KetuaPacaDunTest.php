<?php
// tests/Feature/KetuaPacaDunTest.php
//
// Peranan `ketua_paca_dun` — Ketua PACA bagi SATU DUN sahaja. Ujian ini
// mengunci tiga jaminan:
//
//   1. Skop — hanya kerusi DUN pada users.kadun_id yang boleh dilihat atau
//      disunting. DUN lain (walaupun dalam Parlimen yang sama) mesti 403,
//      BUKAN 404/422 — 404 membocorkan sama ada borang itu wujud.
//   2. Sekatan memusnah — bina-semula/pulih/sejarah memadam atau menulis
//      ganti keseluruhan roster; kekal admin sahaja.
//   3. Pendaratan — /dashboard mengalih ke PACA. Tanpa cabang eksplisit,
//      DashboardController jatuh melalui ke papan pemuka Super Admin
//      peringkat kebangsaan bagi mana-mana peranan yang tidak dikenali.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\PacaSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KetuaPacaDunTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $bandar;

    private Kadun $kadun;      // DUN milik Ketua PACA

    private Kadun $kadunLain;  // DUN jiran, Parlimen SAMA — mesti tetap ditolak

    protected function setUp(): void
    {
        parent::setUp();
        // Halaman Inertia dirender melalui app.blade.php; tanpa ini ujian
        // bergantung pada public/build/manifest.json yang tidak wujud dalam CI.
        $this->withoutVite();
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan']);
        $this->bandar = Bandar::create(['nama' => 'Kuala Pilah', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'Juasseh', 'bandar_id' => $this->bandar->id]);
        $this->kadunLain = Kadun::create(['nama' => 'Seri Menanti', 'bandar_id' => $this->bandar->id]);
    }

    private function user(string $role, array $over = []): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia ada).
        return User::factory()->create(array_merge([
            'role' => $role,
            'telephone' => '01277'.random_int(10000, 99999),
        ], $over));
    }

    private function ketua(): User
    {
        return $this->user('ketua_paca_dun', [
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
    }

    /** Satu Borang14Form berscoresheet bagi $kadun — sumber semaian PacaBuilderService. */
    private function borang14(Kadun $kadun): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parties' => [], 'status' => 'published', 'source' => 'scoresheet',
            'structure' => ['rows' => [
                ['dm' => '041/03/01', 'pusat' => 'SK BUMBUNG LIMA', 'saluran' => '1'],
                ['dm' => '041/03/02', 'pusat' => 'SK PAYA KELADI', 'saluran' => '1'],
            ]],
        ]);
    }

    /** @return array<string,mixed> */
    private function kawasanPayload(Kadun $kadun): array
    {
        return [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
        ];
    }

    private function pacaTree(User $user, Kadun $kadun): array
    {
        return $this->actingAs($user)
            ->getJson(route('pilihanraya.paca.data', $this->kawasanPayload($kadun)))
            ->json('paca');
    }

    /** Payload simpan() minimum daripada pokok semasa, menetapkan nama petugas pada setiap slot. */
    private function simpanPayloadFrom(array $tree, ?string $nama = null): array
    {
        $pusat = [];
        foreach ($tree['pusat'] as $p) {
            $saluran = [];
            foreach ($p['saluran'] as $s) {
                $slot = [];
                foreach ($s['slot'] as $sl) {
                    $slot[] = [
                        'id' => $sl['id'],
                        'masa_mula' => $sl['masa_mula'],
                        'masa_tamat' => $sl['masa_tamat'],
                        'petugas_nama' => $nama ?? $sl['petugas_nama'],
                        'petugas_kp' => $sl['petugas_kp'],
                        'petugas_tel' => $sl['petugas_tel'],
                        'petugas_parti' => $sl['petugas_parti'],
                    ];
                }
                $saluran[] = ['id' => $s['id'], 'slot' => $slot];
            }
            $pusat[] = [
                'id' => $p['id'],
                'ketua_nama' => $p['ketua_nama'],
                'ketua_tel' => $p['ketua_tel'],
                'saluran' => $saluran,
            ];
        }

        return ['paca_form_id' => $tree['id'], 'pusat' => $pusat];
    }

    // ---------------------------------------------------------------- skop

    public function test_index_only_lists_the_users_own_dun_seat(): void
    {
        $this->borang14($this->kadun);
        $this->borang14($this->kadunLain);

        $res = $this->actingAs($this->ketua())->get(route('pilihanraya.paca'));

        $res->assertOk();
        $seats = $res->viewData('page')['props']['seats'];
        $this->assertCount(1, $seats, 'Ketua PACA DUN mesti melihat SATU kerusi sahaja.');
        $this->assertSame($this->kadun->id, $seats[0]['kawasan_id']);
        $this->assertSame('Juasseh', $seats[0]['dun']);
    }

    public function test_index_locks_the_seat_and_hides_structure_controls(): void
    {
        $this->borang14($this->kadun);

        $props = $this->actingAs($this->ketua())
            ->get(route('pilihanraya.paca'))
            ->viewData('page')['props'];

        $this->assertNotNull($props['kerusiTerkunci'], 'Kerusi tunggal mesti dipilih automatik.');
        $this->assertSame($this->kadun->id, $props['kerusiTerkunci']['kawasan_id']);
        $this->assertFalse($props['bolehUrusStruktur']);
    }

    public function test_admin_keeps_the_free_seat_picker(): void
    {
        $this->borang14($this->kadun);
        $this->borang14($this->kadunLain);

        $admin = $this->user('admin', [
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);

        $props = $this->actingAs($admin)->get(route('pilihanraya.paca'))->viewData('page')['props'];

        $this->assertCount(2, $props['seats'], 'Admin kekal melihat semua kerusi dalam Parlimennya.');
        $this->assertNull($props['kerusiTerkunci']);
        $this->assertTrue($props['bolehUrusStruktur']);
    }

    public function test_can_load_own_dun_roster(): void
    {
        $this->borang14($this->kadun);

        $tree = $this->pacaTree($this->ketua(), $this->kadun);

        $this->assertSame(2, count($tree['pusat']));
    }

    public function test_cannot_load_another_duns_roster(): void
    {
        $this->borang14($this->kadunLain);

        $this->actingAs($this->ketua())
            ->getJson(route('pilihanraya.paca.data', $this->kawasanPayload($this->kadunLain)))
            ->assertForbidden();
    }

    public function test_cannot_load_a_parlimen_level_roster(): void
    {
        // Kerusi Parlimen tiada kadun_id untuk dipadankan — mesti ditolak terus.
        Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->bandar->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 2,
            'parties' => [], 'status' => 'published', 'source' => 'scoresheet',
            'structure' => ['rows' => [['dm' => '041/03/01', 'pusat' => 'SK A', 'saluran' => '1']]],
        ]);

        $this->actingAs($this->ketua())
            ->getJson(route('pilihanraya.paca.data', [
                'kawasan_type' => 'parlimen', 'kawasan_id' => $this->bandar->id,
                'jenis_pr' => 'pru', 'tahun' => 2027,
            ]))
            ->assertForbidden();
    }

    public function test_403_not_404_when_the_other_duns_form_does_not_exist(): void
    {
        // Tiada Borang14Form langsung bagi kadunLain. Balasan mesti tetap 403 —
        // 404 akan mengesahkan/menafikan kewujudan borang kepada penyerang.
        $this->actingAs($this->ketua())
            ->getJson(route('pilihanraya.paca.data', $this->kawasanPayload($this->kadunLain)))
            ->assertForbidden();
    }

    // --------------------------------------------------------------- menulis

    public function test_can_save_petugas_on_own_dun(): void
    {
        $this->borang14($this->kadun);
        $ketua = $this->ketua();
        $tree = $this->pacaTree($ketua, $this->kadun);

        $this->actingAs($ketua)
            ->postJson(route('pilihanraya.paca.simpan'), $this->simpanPayloadFrom($tree, 'AZMI'))
            ->assertOk();

        $slot = \App\Models\PacaForm::find($tree['id'])
            ->pusatList()->first()->saluranList()->first()->slots()->where('jawatan', 'PA1')->first();
        $this->assertSame('AZMI', $slot->petugas_nama);
    }

    public function test_cannot_save_onto_another_duns_roster(): void
    {
        $this->borang14($this->kadunLain);
        $admin = $this->user('super_admin');
        $tree = $this->pacaTree($admin, $this->kadunLain);

        $this->actingAs($this->ketua())
            ->postJson(route('pilihanraya.paca.simpan'), $this->simpanPayloadFrom($tree, 'PENCEROBOH'))
            ->assertForbidden();

        $slot = \App\Models\PacaForm::find($tree['id'])
            ->pusatList()->first()->saluranList()->first()->slots()->where('jawatan', 'PA1')->first();
        $this->assertNull($slot->petugas_nama, 'Roster DUN lain mesti kekal tidak tersentuh.');
    }

    public function test_can_download_pdf_for_own_dun(): void
    {
        $this->borang14($this->kadun);

        $this->actingAs($this->ketua())
            ->get(route('pilihanraya.paca.pdf', $this->kawasanPayload($this->kadun)))
            ->assertOk();
    }

    // ------------------------------------------------- operasi memusnah

    public function test_cannot_rebuild_the_roster(): void
    {
        $this->borang14($this->kadun);
        $ketua = $this->ketua();
        $this->pacaTree($ketua, $this->kadun);

        $this->actingAs($ketua)
            ->postJson(route('pilihanraya.paca.bina-semula'), $this->kawasanPayload($this->kadun))
            ->assertForbidden();
    }

    public function test_cannot_list_or_restore_snapshots(): void
    {
        $this->borang14($this->kadun);
        $admin = $this->user('super_admin');
        $tree = $this->pacaTree($admin, $this->kadun);
        $this->actingAs($admin)
            ->postJson(route('pilihanraya.paca.simpan'), $this->simpanPayloadFrom($tree, 'AZMI'))
            ->assertOk();
        $snap = PacaSnapshot::where('paca_form_id', $tree['id'])->firstOrFail();

        $ketua = $this->ketua();
        $this->actingAs($ketua)
            ->getJson(route('pilihanraya.paca.sejarah', ['paca_form_id' => $tree['id']]))
            ->assertForbidden();
        $this->actingAs($ketua)
            ->postJson(route('pilihanraya.paca.pulih'), ['snapshot_id' => $snap->id])
            ->assertForbidden();
    }

    // -------------------------------------------------- pendaratan & menu

    public function test_dashboard_redirects_to_paca(): void
    {
        $this->actingAs($this->ketua())
            ->get(route('dashboard'))
            ->assertRedirect(route('pilihanraya.paca'));
    }

    public function test_other_pilihanraya_pages_stay_closed(): void
    {
        $ketua = $this->ketua();

        foreach (['pilihanraya.war-room', 'pilihanraya.borang-14', 'pilihanraya.analisa'] as $nama) {
            $this->actingAs($ketua)->get(route($nama))
                ->assertRedirect(route('dashboard'));
        }

        // Scoreboard kini di luar kumpulan 'admin' (Tugasan 4 — setiap pemilik
        // kerusi menguruskan papan sendiri melalui SeatScope), jadi ia disekat
        // oleh pengawal (403), bukan oleh middleware EnsureAdmin (redirect).
        // Lihat ScoreboardAccessTest::test_ketua_paca_dun_is_refused.
        $this->actingAs($ketua)->get(route('pilihanraya.scoreboard'))
            ->assertForbidden();
    }

    public function test_plain_user_is_still_blocked_from_paca(): void
    {
        $this->borang14($this->kadun);

        $this->actingAs($this->user('user', ['kadun_id' => $this->kadun->id]))
            ->get(route('pilihanraya.paca'))
            ->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------ pentadbiran akaun

    public function test_admin_can_assign_the_role_within_own_parlimen(): void
    {
        $admin = $this->user('admin', [
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
        $ahli = $this->user('user', [
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->put(route('users.update', $ahli), [
            'name' => $ahli->name,
            'telephone' => $ahli->telephone,
            'email' => $ahli->email,
            'role' => 'ketua_paca_dun',
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertTrue($ahli->fresh()->isKetuaPacaDun());
    }
}
