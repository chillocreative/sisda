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
        // Label paparan: slot CA memikul nombor PA seterusnya (borang + PDF).
        $this->assertSame(
            ['PA1', 'PA2', 'PA3', 'PA4 / CA'],
            array_column($bumbung['saluran'][0]['slot'], 'jawatan_papar'),
        );
        // Satu pautan awam per KERUSI (bukan per Pusat).
        $this->assertNotEmpty($tree['public_url']);
        $this->assertStringContainsString('/paca/', $tree['public_url']);

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

    public function test_delete_slot_removes_the_row_and_relabels(): void
    {
        $this->borang14();
        $user = $this->user();
        $tree = $this->pacaTree($user);
        $saluranId = $tree['pusat'][0]['saluran'][0]['id'];
        // Buang PA2 daripada [PA1, PA2, PA3, CA] -> [PA1, PA2(bekas PA3), CA].
        $pa2 = collect($tree['pusat'][0]['saluran'][0]['slot'])->firstWhere('jawatan', 'PA2');

        $res = $this->actingAs($user)->postJson(route('pilihanraya.paca.slot.buang'), [
            'paca_slot_id' => $pa2['id'],
        ]);

        $res->assertOk();
        $saluran = collect(collect($res->json('paca.pusat'))->firstWhere('id', $tree['pusat'][0]['id'])['saluran'])
            ->firstWhere('id', $saluranId);

        $this->assertSame(['PA1', 'PA2', 'CA'], array_column($saluran['slot'], 'jawatan'));
        $this->assertFalse(collect($saluran['slot'])->contains('id', $pa2['id']));
    }

    public function test_delete_slot_refuses_to_empty_a_saluran(): void
    {
        $this->borang14();
        $user = $this->user();
        $tree = $this->pacaTree($user);
        $saluranId = $tree['pusat'][0]['saluran'][0]['id'];

        // Buang sehingga tinggal satu slot.
        $ids = array_column($tree['pusat'][0]['saluran'][0]['slot'], 'id');
        foreach (array_slice($ids, 0, count($ids) - 1) as $id) {
            $this->actingAs($user)->postJson(route('pilihanraya.paca.slot.buang'), ['paca_slot_id' => $id])->assertOk();
        }

        // Slot terakhir tidak boleh dibuang — saluran mesti ada sekurang-kurangnya satu.
        $this->actingAs($user)
            ->postJson(route('pilihanraya.paca.slot.buang'), ['paca_slot_id' => end($ids)])
            ->assertStatus(422);

        $this->assertSame(1, \App\Models\PacaSaluran::whereKey($saluranId)->first()->slots()->count());
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

    public function test_save_rejects_malformed_slot_time(): void
    {
        $this->borang14();
        $user = $this->user();
        $tree = $this->pacaTree($user);

        $payload = $this->simpanPayloadFrom($tree, function ($data, $slot) {
            if ($slot['jawatan'] === 'PA1') {
                // masa_tamat sengaja dibiar null supaya semakan perniagaan
                // minimumMet() (yang terlepas pasang bila salah satu masa
                // kosong) tidak "menangkap" nilai tidak sah ini secara
                // kebetulan — hanya date_format:H:i patut menolaknya.
                $data['masa_mula'] = '99:99';
                $data['masa_tamat'] = null;
            }

            return $data;
        });

        $res = $this->actingAs($user)->postJson(route('pilihanraya.paca.simpan'), $payload);

        $res->assertStatus(422);
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

    /**
     * Laluan kanak-kanak (child-id endpoints) menyelesaikan skop daripada
     * baris kanak-kanak itu sendiri (Pusat/Saluran/PacaForm/Snapshot), BUKAN
     * daripada parameter kawasan_type/kawasan_id di URL. Jika assertBolehAkses
     * tercicir daripada mana-mana satu kaedah ini pada masa hadapan, ujian
     * ini mesti gagal (403 dijangka, bukan 200/422) DAN tiada tulisan DB
     * berlaku.
     */
    public function test_child_id_endpoints_reject_admin_outside_the_owning_bandar(): void
    {
        $this->borang14();
        $owner = $this->user(); // super_admin — bina & simpan dahulu supaya id kanak-kanak & snapshot sedia ada
        $tree = $this->pacaTree($owner);

        $isi = $this->simpanPayloadFrom($tree, function ($data, $slot) {
            if ($slot['jawatan'] === 'PA1') {
                $data['petugas_nama'] = 'ASAL';
            }

            return $data;
        });
        $this->actingAs($owner)->postJson(route('pilihanraya.paca.simpan'), $isi)->assertOk();

        $form = PacaForm::findOrFail($tree['id']);
        $pusatId = $tree['pusat'][0]['id'];
        $saluranId = $tree['pusat'][0]['saluran'][0]['id'];
        $snapshot = PacaSnapshot::where('paca_form_id', $form->id)->latest('id')->first();

        $bandarLain = Bandar::create(['nama' => 'Bandar Lain', 'negeri_id' => $this->kadun->bandar->negeri_id]);
        $adminLain = $this->user('admin', ['bandar_id' => $bandarLain->id]);

        // paca.simpan — pelanggan luar Bandar cuba menimpa petugas.
        $payloadSimpan = $this->simpanPayloadFrom($this->pacaTree($owner), function ($data, $slot) {
            if ($slot['jawatan'] === 'PA1') {
                $data['petugas_nama'] = 'SERANG';
            }

            return $data;
        });
        $this->actingAs($adminLain)
            ->postJson(route('pilihanraya.paca.simpan'), $payloadSimpan)
            ->assertForbidden();
        $slotPa1 = $form->pusatList()->first()->saluranList()->first()->slots()->where('jawatan', 'PA1')->first();
        $this->assertSame('ASAL', $slotPa1->petugas_nama, 'paca.simpan luar Bandar tidak sepatutnya menulis apa-apa.');

        // paca.saluran.tambah
        $bilanganSaluranAsal = $form->pusatList()->first()->saluranList()->count();
        $this->actingAs($adminLain)
            ->postJson(route('pilihanraya.paca.saluran.tambah'), ['paca_pusat_id' => $pusatId])
            ->assertForbidden();
        $this->assertSame(
            $bilanganSaluranAsal,
            $form->pusatList()->first()->saluranList()->count(),
            'paca.saluran.tambah luar Bandar tidak sepatutnya menambah baris.'
        );

        // paca.slot.tambah
        $bilanganSlotAsal = $form->pusatList()->first()->saluranList()->first()->slots()->count();
        $this->actingAs($adminLain)
            ->postJson(route('pilihanraya.paca.slot.tambah'), ['paca_saluran_id' => $saluranId])
            ->assertForbidden();
        $this->assertSame(
            $bilanganSlotAsal,
            $form->pusatList()->first()->saluranList()->first()->slots()->count(),
            'paca.slot.tambah luar Bandar tidak sepatutnya menambah baris.'
        );

        // paca.sejarah
        $this->actingAs($adminLain)
            ->getJson(route('pilihanraya.paca.sejarah', ['paca_form_id' => $form->id]))
            ->assertForbidden();

        // paca.pulih
        $this->actingAs($adminLain)
            ->postJson(route('pilihanraya.paca.pulih'), ['snapshot_id' => $snapshot->id])
            ->assertForbidden();
        $slotPa1->refresh();
        $this->assertSame('ASAL', $slotPa1->petugas_nama, 'paca.pulih luar Bandar tidak sepatutnya menulis apa-apa.');
    }

    /** Laluan bahagia: admin biasa DALAM Bandar kerusi itu sendiri dibenarkan simpan(). */
    public function test_plain_admin_in_same_bandar_can_simpan(): void
    {
        $this->borang14();
        $owner = $this->user(); // super_admin — bina pokok dahulu
        $tree = $this->pacaTree($owner);

        $adminSamaBandar = $this->user('admin', ['bandar_id' => $this->kadun->bandar_id]);

        $payload = $this->simpanPayloadFrom($tree, function ($data, $slot) {
            if ($slot['jawatan'] === 'PA1') {
                $data['petugas_nama'] = 'DALAM BANDAR';
            }

            return $data;
        });

        $this->actingAs($adminSamaBandar)
            ->postJson(route('pilihanraya.paca.simpan'), $payload)
            ->assertOk();

        $form = PacaForm::findOrFail($tree['id']);
        $slotPa1 = $form->pusatList()->first()->saluranList()->first()->slots()->where('jawatan', 'PA1')->first();
        $this->assertSame('DALAM BANDAR', $slotPa1->petugas_nama);
    }

    private function sendoraAktif(): void
    {
        \App\Models\SendoraSetting::create([
            'api_url' => 'https://sendora.cc',
            'api_token' => 'tok_test',
            'device_id' => 73,
            'admin_phone' => '0148885659',
            'is_active' => true,
        ]);
    }

    public function test_whatsapp_sends_roster_pdf_as_send_file_payload(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/v1/send-file' => \Illuminate\Support\Facades\Http::response(['success' => true], 200),
        ]);
        $this->sendoraAktif();
        $this->borang14();

        $res = $this->actingAs($this->user())->postJson(route('pilihanraya.paca.whatsapp'), array_merge(
            $this->kawasanPayload(),
            ['telefon' => '012-3456789'],
        ));

        $res->assertOk();

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), '/api/v1/send-file')
                && $request->hasHeader('Authorization', 'Bearer tok_test')
                && (int) $body['device_id'] === 73
                && $body['to'] === '60123456789'                 // 012-3456789 -> antarabangsa
                && $body['mimetype'] === 'application/pdf'
                && ! empty($body['file_base64'])
                && str_starts_with(base64_decode($body['file_base64'], true) ?: '', '%PDF');
        });
    }

    public function test_whatsapp_returns_422_when_sendora_reports_failure(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*/api/v1/send-file' => \Illuminate\Support\Facades\Http::response(['success' => false, 'message' => 'device offline'], 422),
        ]);
        $this->sendoraAktif();
        $this->borang14();

        $res = $this->actingAs($this->user())->postJson(route('pilihanraya.paca.whatsapp'), array_merge(
            $this->kawasanPayload(),
            ['telefon' => '012-3456789'],
        ));

        $res->assertStatus(422);
    }

    public function test_whatsapp_requires_telefon(): void
    {
        $this->sendoraAktif();
        $this->borang14();

        $this->actingAs($this->user())->postJson(route('pilihanraya.paca.whatsapp'), $this->kawasanPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('telefon');
    }

    public function test_seat_selection_is_remembered_until_logout(): void
    {
        $this->borang14();
        $user = $this->user();

        // Memilih kerusi (XHR data()) menyimpan skop 'paca' dalam sesi.
        $this->actingAs($user)
            ->getJson(route('pilihanraya.paca.data', $this->kawasanPayload()))
            ->assertOk()
            ->assertSessionHas('sticky_filters.paca');

        // Kembali ke halaman PACA (GET kosong) -> rememberedFilters dikongsi
        // supaya SeatPicker boleh menyemai semula dropdown.
        $this->actingAs($user)
            ->get(route('pilihanraya.paca'))
            ->assertInertia(fn ($page) => $page
                ->where('rememberedFilters.kawasan_type', 'dun')
                ->where('rememberedFilters.kawasan_id', (string) $this->kadun->id));

        // Log keluar membatalkan sesi -> pilihan dilupakan (kunci tiada lagi).
        $this->post(route('logout'));
        $this->actingAs($user)
            ->get(route('pilihanraya.paca'))
            ->assertInertia(fn ($page) => $page->missing('rememberedFilters.kawasan_type'));
    }
}
