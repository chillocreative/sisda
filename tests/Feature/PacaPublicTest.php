<?php
// tests/Feature/PacaPublicTest.php
//
// Laluan awam (tiada log masuk) PacaPublicController: GET /paca/{token}
// memulangkan Inertia Public/Paca bagi SATU KERUSI (DUN) — SEMUA Pusat
// Mengundi kerusi itu, setiap satu dengan Saluran->slot. Payload itu tidak
// sekali-kali membawa petugas_kp/petugas_tel/nama milik pengisi sedia ada
// (sebab utama pengawal ini wujud). POST /paca/{token}/hantar mengisi satu
// slot kosong kepunyaan MANA-MANA Pusat kerusi itu, menolak slot terisi
// (422) atau kepunyaan kerusi LAIN (404), dan mengesahkan IC.
namespace Tests\Feature;

use App\Models\PacaForm;
use App\Models\PacaPusat;
use App\Models\PacaSaluran;
use App\Models\PacaSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PacaPublicTest extends TestCase
{
    use RefreshDatabase;

    private const IC_SAH = '680623-07-5749';

    /** Tahun berlainan bagi setiap panggilan form() — mengelak perlanggaran unique paca_forms. */
    private static int $tahun = 2020;

    /** Satu kerusi (PacaForm) dengan token awam per-DUN. */
    private function form(array $over = []): PacaForm
    {
        return PacaForm::create(array_merge([
            'kawasan_type' => 'dun', 'kawasan_id' => 1,
            'jenis_pr' => 'prn', 'tahun' => self::$tahun++,
            'public_token' => 'tok_'.self::$tahun.'_'.str_repeat('a', 20),
        ], $over));
    }

    private function pusat(PacaForm $form, array $over = []): PacaPusat
    {
        return $form->pusatList()->create(array_merge([
            'dm' => '041/03/01',
            'pusat' => 'SK BUMBUNG LIMA',
            'public_token' => 'pp_'.self::$tahun.'_'.str_repeat('a', 24),
            'urutan' => 1,
        ], $over));
    }

    private function saluran(PacaPusat $pusat, array $over = []): PacaSaluran
    {
        return $pusat->saluranList()->create(array_merge(['label' => '1', 'urutan' => 1], $over));
    }

    private function slot(PacaSaluran $saluran, array $over = []): PacaSlot
    {
        return $saluran->slots()->create(array_merge([
            'jawatan' => 'PA1', 'masa_mula' => '08:00', 'masa_tamat' => '10:00', 'urutan' => 1,
        ], $over));
    }

    /**
     * GET awam MESTI dinilai sebagai satu navigasi Inertia (bukan HTML penuh):
     * render Blade penuh gagal kerana manifest Vite CI. Header X-Inertia
     * memulangkan JSON page-object terus, sama seperti navigasi SPA sebenar.
     */
    private function getInertia(string $url)
    {
        $manifest = public_path('build/manifest.json');
        $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : null;

        return $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get($url);
    }

    public function test_valid_token_renders_every_pusat_of_the_dun(): void
    {
        $form = $this->form();
        $a = $this->pusat($form, ['pusat' => 'SK BUMBUNG LIMA', 'dm' => '041/03/01', 'public_token' => 'pp_a', 'urutan' => 1]);
        $this->slot($this->saluran($a));
        $b = $this->pusat($form, ['pusat' => 'SK PAYA KELADI', 'dm' => '041/03/02', 'public_token' => 'pp_b', 'urutan' => 2]);
        $this->slot($this->saluran($b));

        $page = $this->getInertia(route('paca.public', $form->public_token))->assertOk()->json();

        $this->assertSame('Public/Paca', $page['component']);
        // Kedua-dua Pusat kerusi ini dipaparkan pada satu pautan.
        $names = array_column($page['props']['pusat'], 'pusat');
        $this->assertSame(['SK BUMBUNG LIMA', 'SK PAYA KELADI'], $names);
        $this->assertSame('PA1', $page['props']['pusat'][0]['saluran'][0]['slot'][0]['jawatan']);
        $this->assertSame('08:00 - 10:00', $page['props']['pusat'][0]['saluran'][0]['slot'][0]['masa']);
    }

    public function test_public_payload_labels_ca_with_the_next_pa_number(): void
    {
        $form = $this->form();
        $pusat = $this->pusat($form, ['pusat' => 'SK BUMBUNG LIMA', 'dm' => '041/03/01', 'public_token' => 'pp_a']);
        $saluran = $this->saluran($pusat);
        $this->slot($saluran);
        $this->slot($saluran, ['jawatan' => 'PA2', 'masa_mula' => '10:00', 'masa_tamat' => '12:00', 'urutan' => 2]);
        $this->slot($saluran, ['jawatan' => 'CA', 'masa_mula' => '12:00', 'masa_tamat' => null, 'urutan' => 3]);

        $page = $this->getInertia(route('paca.public', $form->public_token))->assertOk()->json();
        $slot = $page['props']['pusat'][0]['saluran'][0]['slot'];

        $this->assertSame(['PA1', 'PA2', 'PA3 / CA'], array_column($slot, 'jawatan_papar'));
        // Nilai LOGIK kekal 'CA' — pelabelan semula/tempoh minimum bergantung padanya.
        $this->assertSame('CA', $slot[2]['jawatan']);
    }

    public function test_public_payload_never_exposes_ic_or_phone_or_name(): void
    {
        $form = $this->form();
        $p = $this->pusat($form);
        $this->slot($this->saluran($p), [
            'petugas_nama' => 'AZMI', 'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '010-2187454', 'petugas_parti' => 'KEADILAN',
        ]);

        $response = $this->getInertia(route('paca.public', $form->public_token))->assertOk();
        $slotProp = $response->json('props.pusat.0.saluran.0.slot.0');

        $this->assertTrue($slotProp['terisi']);
        $this->assertSame('KEADILAN', $slotProp['parti']);
        $this->assertArrayNotHasKey('petugas_kp', $slotProp);
        $this->assertArrayNotHasKey('petugas_tel', $slotProp);
        $this->assertArrayNotHasKey('petugas_nama', $slotProp);

        $response->assertDontSee(self::IC_SAH, false);
        $response->assertDontSee('010-2187454', false);
        $response->assertDontSee('AZMI', false);
    }

    public function test_unknown_token_is_404(): void
    {
        $this->getJson(route('paca.public', 'tidak-wujud'))->assertNotFound();
    }

    public function test_submit_fills_an_empty_slot_of_any_pusat_in_the_dun(): void
    {
        $form = $this->form();
        // Slot berada di Pusat KEDUA kerusi ini — bukti pengisian berskop KERUSI.
        $this->pusat($form, ['public_token' => 'pp_x', 'urutan' => 1]);
        $b = $this->pusat($form, ['pusat' => 'SK PAYA KELADI', 'public_token' => 'pp_y', 'urutan' => 2]);
        $slot = $this->slot($this->saluran($b));

        $res = $this->postJson(route('paca.public.hantar', $form->public_token), [
            'paca_slot_id' => $slot->id,
            'petugas_nama' => 'ROSLAN', 'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '012-3456789', 'petugas_parti' => 'BEBAS',
        ]);

        $res->assertOk();
        $slot->refresh();
        $this->assertSame('ROSLAN', $slot->petugas_nama);
        $this->assertSame('BEBAS', $slot->petugas_parti);
    }

    public function test_submit_to_a_filled_slot_overwrites_it(): void
    {
        // Slot terisi BOLEH dikemas kini melalui pautan awam — petugas
        // membetulkan butiran sendiri. Nama/KP/Tel kekal wajib, jadi kemaskini
        // hanya MENGGANTIKAN dengan pendaftaran sah lain, bukan mengosongkan.
        $form = $this->form();
        $slot = $this->slot($this->saluran($this->pusat($form)), [
            'petugas_nama' => 'SEDIA ADA', 'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '019-1112222', 'petugas_parti' => 'BEBAS',
        ]);

        $res = $this->postJson(route('paca.public.hantar', $form->public_token), [
            'paca_slot_id' => $slot->id,
            'petugas_nama' => 'BARU', 'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '012-3456789', 'petugas_parti' => 'PAKATAN HARAPAN',
        ]);

        $res->assertOk();
        $slot->refresh();
        $this->assertSame('BARU', $slot->petugas_nama);
        $this->assertSame('012-3456789', $slot->petugas_tel);
        $this->assertSame('PAKATAN HARAPAN', $slot->petugas_parti);
    }

    public function test_invalid_ic_is_422(): void
    {
        $form = $this->form();
        $slot = $this->slot($this->saluran($this->pusat($form)));

        $res = $this->postJson(route('paca.public.hantar', $form->public_token), [
            'paca_slot_id' => $slot->id,
            'petugas_nama' => 'ROSLAN', 'petugas_kp' => '999999-99-9999',
            'petugas_tel' => '012-3456789', 'petugas_parti' => 'BEBAS',
        ]);

        $res->assertStatus(422);
        $this->assertNull($slot->refresh()->petugas_nama);
    }

    public function test_slot_from_a_different_dun_is_404(): void
    {
        $formA = $this->form(['public_token' => 'tok_a']);
        $this->slot($this->saluran($this->pusat($formA, ['public_token' => 'pp_a1'])));

        $formB = $this->form(['public_token' => 'tok_b']);
        $slotB = $this->slot($this->saluran($this->pusat($formB, ['public_token' => 'pp_b1'])));

        // Cuba mengisi slot kerusi B melalui token kerusi A.
        $res = $this->postJson(route('paca.public.hantar', $formA->public_token), [
            'paca_slot_id' => $slotB->id,
            'petugas_nama' => 'SERANG', 'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '012-3456789', 'petugas_parti' => 'BEBAS',
        ]);

        $res->assertNotFound();
        $this->assertNull($slotB->refresh()->petugas_nama);
    }
}
