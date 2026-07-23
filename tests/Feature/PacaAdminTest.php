<?php
// tests/Feature/PacaAdminTest.php
//
// Pengawal admin PACABA: data() bina/muatkan pokok Pusat->Saluran->slot,
// simpan() menyimpan petugas DENGAN sejarah (satu snapshot pra-suntingan
// setiap simpanan) dan menguatkuasakan tempoh minimum 2 jam, tambahSaluran/
// tambahSlot menambah baris dan melabel semula, sejarah()/pulih() memulihkan
// snapshot. Kebenaran dilingkup pada Bandar admin biasa — kerusi di luar
// Bandar itu mesti 403, bukan 404/422 (jangan bocorkan kandungan borang).
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\PacaForm;
use App\Models\PacaSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PacaAdminTest extends TestCase
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

    /**
     * Satu Borang14Form berscoresheet (dua Pusat, satu dengan 2 saluran)
     * bagi $this->kadun — sumber semaian PacaBuilderService.
     */
    private function borang14(): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parties' => [], 'status' => 'published', 'source' => 'scoresheet',
            'structure' => ['rows' => [
                ['dm' => '041/03/01', 'pusat' => 'SK BUMBUNG LIMA', 'saluran' => '1'],
                ['dm' => '041/03/01', 'pusat' => 'SK BUMBUNG LIMA', 'saluran' => '2'],
                ['dm' => '041/03/02', 'pusat' => 'SK PAYA KELADI', 'saluran' => '1'],
            ]],
        ]);
    }

    /** @return array<string,mixed> */
    private function kawasanPayload(): array
    {
        return [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
        ];
    }

    /** Bina (via HTTP data()) dan pulangkan payload pokok penuh sebagai array. */
    private function pacaTree(User $user): array
    {
        return $this->actingAs($user)
            ->getJson(route('pilihanraya.paca.data', $this->kawasanPayload()))
            ->json('paca');
    }

    /** Bina payload simpan() daripada pokok semasa, dengan satu penukaran callback pada setiap slot. */
    private function simpanPayloadFrom(array $tree, ?callable $ubahSlot = null): array
    {
        $pusat = [];
        foreach ($tree['pusat'] as $p) {
            $saluran = [];
            foreach ($p['saluran'] as $s) {
                $slot = [];
                foreach ($s['slot'] as $sl) {
                    $data = [
                        'id' => $sl['id'],
                        'masa_mula' => $sl['masa_mula'],
                        'masa_tamat' => $sl['masa_tamat'],
                        'petugas_nama' => $sl['petugas_nama'],
                        'petugas_kp' => $sl['petugas_kp'],
                        'petugas_tel' => $sl['petugas_tel'],
                        'petugas_parti' => $sl['petugas_parti'],
                    ];
                    if ($ubahSlot) {
                        $data = $ubahSlot($data, $sl);
                    }
                    $slot[] = $data;
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

    public function test_data_builds_the_form_and_returns_the_pusat_tree(): void
    {
        $this->borang14();

        $tree = $this->pacaTree($this->user());

        $this->assertSame(2, count($tree['pusat']));
        $bumbung = collect($tree['pusat'])->firstWhere('pusat', 'SK BUMBUNG LIMA');
        $this->assertSame(2, count($bumbung['saluran']));
        $this->assertSame(['PA1', 'PA2', 'PA3', 'CA'], array_column($bumbung['saluran'][0]['slot'], 'jawatan'));
        $this->assertNotEmpty($bumbung['public_token']);
        $this->assertStringContainsString($bumbung['public_token'], $bumbung['public_url']);

        $this->assertSame(1, PacaForm::count());
    }

    public function test_save_persists_petugas_and_writes_exactly_one_snapshot(): void
    {
        $this->borang14();
        $user = $this->user();
        $tree = $this->pacaTree($user);

        $payload = $this->simpanPayloadFrom($tree, function ($data, $slot) {
            if ($slot['jawatan'] === 'PA1') {
                $data['petugas_nama'] = 'AZMI';
                $data['petugas_kp'] = '680623-07-5749';
                $data['petugas_tel'] = '010-2187454';
                $data['petugas_parti'] = 'KEADILAN';
            }

            return $data;
        });

        $this->actingAs($user)->postJson(route('pilihanraya.paca.simpan'), $payload)->assertOk();

        $form = PacaForm::findOrFail($tree['id']);
        $slotPa1 = $form->pusatList()->first()->saluranList()->first()->slots()->where('jawatan', 'PA1')->first();
        $this->assertSame('AZMI', $slotPa1->petugas_nama);

        $this->assertSame(1, PacaSnapshot::where('paca_form_id', $form->id)->count());
        $snap = PacaSnapshot::where('paca_form_id', $form->id)->first();
        $this->assertSame('before_edit', $snap->reason);
        // Snapshot mesti pegang keadaan PRA-suntingan (petugas masih kosong).
        $snapPa1 = collect($snap->data['pusat'][0]['saluran'][0]['slot'])->firstWhere('jawatan', 'PA1');
        $this->assertNull($snapPa1['petugas_nama']);
    }

    public function test_save_rejects_a_sub_two_hour_pa_slot(): void
    {
        $this->borang14();
        $user = $this->user();
        $tree = $this->pacaTree($user);

        $payload = $this->simpanPayloadFrom($tree, function ($data, $slot) {
            if ($slot['jawatan'] === 'PA1') {
                $data['masa_mula'] = '08:00';
                $data['masa_tamat'] = '09:30'; // 1h30m — kurang daripada 2 jam
            }

            return $data;
        });

        $res = $this->actingAs($user)->postJson(route('pilihanraya.paca.simpan'), $payload);

        $res->assertStatus(422);
        $this->assertStringContainsString('2 jam', $res->json('errors.pusat.0'));

        // Tiada apa patut tertulis — transaksi tidak sepatutnya berjalan.
        $this->assertSame(0, PacaSnapshot::count());
        $form = PacaForm::findOrFail($tree['id']);
        $slotPa1 = $form->pusatList()->first()->saluranList()->first()->slots()->where('jawatan', 'PA1')->first();
        $this->assertNotSame('09:30', $slotPa1->masa_tamat);
    }

    public function test_add_pa_appends_and_relabels_ca_still_last(): void
    {
        $this->borang14();
        $user = $this->user();
        $tree = $this->pacaTree($user);
        $saluranId = $tree['pusat'][0]['saluran'][0]['id'];

        $res = $this->actingAs($user)->postJson(route('pilihanraya.paca.slot.tambah'), [
            'paca_saluran_id' => $saluranId,
        ]);

        $res->assertOk();
        $saluran = collect($res->json('paca.pusat'))
            ->firstWhere('id', $tree['pusat'][0]['id'])['saluran'];
        $saluran = collect($saluran)->firstWhere('id', $saluranId);

        $this->assertSame(['PA1', 'PA2', 'PA3', 'PA4', 'CA'], array_column($saluran['slot'], 'jawatan'));
        $this->assertNull(collect($saluran['slot'])->firstWhere('jawatan', 'CA')['masa_tamat']);
    }

    public function test_add_saluran_appends_with_default_slots(): void
    {
        $this->borang14();
        $user = $this->user();
        $tree = $this->pacaTree($user);
        $pusatId = $tree['pusat'][0]['id'];
        $bilanganSaluranAsal = count($tree['pusat'][0]['saluran']);

        $res = $this->actingAs($user)->postJson(route('pilihanraya.paca.saluran.tambah'), [
            'paca_pusat_id' => $pusatId,
        ]);

        $res->assertOk();
        $pusat = collect($res->json('paca.pusat'))->firstWhere('id', $pusatId);
        $this->assertSame($bilanganSaluranAsal + 1, count($pusat['saluran']));
        $baharu = collect($pusat['saluran'])->last();
        $this->assertSame(['PA1', 'PA2', 'PA3', 'CA'], array_column($baharu['slot'], 'jawatan'));
    }

    public function test_pulih_restores_a_snapshot(): void
    {
        $this->borang14();
        $user = $this->user();
        $tree = $this->pacaTree($user);

        $isi = $this->simpanPayloadFrom($tree, function ($data, $slot) {
            if ($slot['jawatan'] === 'PA1') {
                $data['petugas_nama'] = 'AZMI';
            }

            return $data;
        });
        $this->actingAs($user)->postJson(route('pilihanraya.paca.simpan'), $isi)->assertOk();

        $form = PacaForm::findOrFail($tree['id']);

        // Simpanan kedua mengosongkan semula petugas (mensimulasi kesilapan).
        // Snapshot ditulis SEBELUM suntingan diguna pakai, jadi snapshot bagi
        // simpanan KEDUA inilah yang memegang keadaan AZMI (keadaan pra-
        // suntingan simpanan kedua) — bukan snapshot simpanan pertama, yang
        // memegang keadaan kosong SEBELUM AZMI wujud langsung.
        $kosong = $this->simpanPayloadFrom($this->pacaTree($user), function ($data, $slot) {
            $data['petugas_nama'] = null;

            return $data;
        });
        $this->actingAs($user)->postJson(route('pilihanraya.paca.simpan'), $kosong)->assertOk();

        $snap = PacaSnapshot::where('paca_form_id', $form->id)->latest('id')->first();

        $slotPa1 = $form->pusatList()->first()->saluranList()->first()->slots()->where('jawatan', 'PA1')->first();
        $this->assertNull($slotPa1->petugas_nama);

        $res = $this->actingAs($user)->postJson(route('pilihanraya.paca.pulih'), ['snapshot_id' => $snap->id]);
        $res->assertOk();

        $slotPa1->refresh();
        $this->assertSame('AZMI', $slotPa1->petugas_nama, 'Pulih mesti mengembalikan petugas yang disimpan snapshot itu.');
    }

    public function test_out_of_scope_admin_gets_403(): void
    {
        $this->borang14();
        $bandarLain = Bandar::create(['nama' => 'Bandar Lain', 'negeri_id' => $this->kadun->bandar->negeri_id]);
        $adminLain = $this->user('admin', ['bandar_id' => $bandarLain->id]);

        $this->actingAs($adminLain)
            ->getJson(route('pilihanraya.paca.data', $this->kawasanPayload()))
            ->assertForbidden();

        $this->assertSame(0, PacaForm::count(), 'Kerusi di luar Bandar admin ini tidak sepatutnya sempat dibina.');
    }
}
