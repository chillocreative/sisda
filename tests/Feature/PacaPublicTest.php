<?php
// tests/Feature/PacaPublicTest.php
//
// Laluan awam (tiada log masuk) PacaPublicController: GET /paca/{token}
// memulangkan Inertia Public/Paca bagi Pusat token itu SAHAJA, dan payload
// itu tidak sekali-kali membawa petugas_kp/petugas_tel milik pengisi sedia
// ada (sebab utama pengawal ini wujud). POST /paca/{token}/hantar mengisi
// satu slot kosong kepunyaan Pusat token itu, menolak slot yang sudah
// terisi (422) atau kepunyaan Pusat lain (404), dan mengesahkan IC.
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

    /** Tahun berlainan bagi setiap panggilan pusat() — mengelak perlanggaran unique bagi paca_forms. */
    private static int $tahun = 2020;

    /** Satu Pusat berdiri sendiri (satu Saluran, slot PA1 kosong + CA sudah terisi). */
    private function pusat(array $over = []): PacaPusat
    {
        $form = PacaForm::create([
            'kawasan_type' => 'dun', 'kawasan_id' => 1,
            'jenis_pr' => 'prn', 'tahun' => self::$tahun++,
        ]);

        return $form->pusatList()->create(array_merge([
            'dm' => '041/03/01',
            'pusat' => 'SK BUMBUNG LIMA',
            'public_token' => 'tok_'.str_repeat('a', 28),
            'urutan' => 1,
        ], $over));
    }

    /**
     * Laluan GET awam yang MESTI dinilai sebagai satu navigasi Inertia (bukan
     * muatan HTML penuh) — halaman React Public/Paca.jsx belum wujud lagi
     * (dibina pada Tugasan 7 berasingan), jadi render Blade penuh akan gagal
     * kerana manifest Vite tiada entri untuknya. Header X-Inertia memintas
     * itu dan memulangkan JSON page-object terus, sama seperti navigasi SPA
     * sebenar. (assertInertia() bawaan pakej ini hanya menyokong laluan
     * render HTML penuh — ->assertViewHas('page') — jadi payload di sini
     * disemak terus daripada JSON, bukan menerusi macro itu.)
     */
    private function getInertia(string $url)
    {
        // Mesti sepadan versi aset semasa (cincangan manifest.json Vite),
        // jika tidak middleware Inertia memulangkan 409 (isyarat "muat semula
        // penuh") dan bukan payload page-object yang diuji di sini.
        $manifest = public_path('build/manifest.json');
        $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : null;

        return $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ])->get($url);
    }

    private function saluran(PacaPusat $pusat, array $over = []): PacaSaluran
    {
        return $pusat->saluranList()->create(array_merge([
            'label' => '1',
            'urutan' => 1,
        ], $over));
    }

    private function slot(PacaSaluran $saluran, array $over = []): PacaSlot
    {
        return $saluran->slots()->create(array_merge([
            'jawatan' => 'PA1',
            'masa_mula' => '08:00',
            'masa_tamat' => '10:00',
            'urutan' => 1,
        ], $over));
    }

    public function test_valid_token_renders_the_right_pusat(): void
    {
        $pusat = $this->pusat();
        $saluran = $this->saluran($pusat);
        $this->slot($saluran);

        $response = $this->getInertia(route('paca.public', $pusat->public_token));

        $response->assertOk();
        $page = $response->json();

        $this->assertSame('Public/Paca', $page['component']);
        $this->assertSame('SK BUMBUNG LIMA', $page['props']['pusat']['pusat']);
        $this->assertSame('041/03/01', $page['props']['pusat']['dm']);
        $this->assertSame('PA1', $page['props']['saluran'][0]['slot'][0]['jawatan']);
        $this->assertSame('08:00 - 10:00', $page['props']['saluran'][0]['slot'][0]['masa']);
        $this->assertFalse($page['props']['saluran'][0]['slot'][0]['terisi']);
    }

    public function test_public_payload_never_exposes_ic_or_phone_of_an_existing_filler(): void
    {
        $pusat = $this->pusat();
        $saluran = $this->saluran($pusat);
        $this->slot($saluran, [
            'petugas_nama' => 'AZMI', 'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '010-2187454', 'petugas_parti' => 'KEADILAN',
        ]);

        $response = $this->getInertia(route('paca.public', $pusat->public_token));

        $response->assertOk();
        $slotProp = $response->json('props.saluran.0.slot.0');

        $this->assertTrue($slotProp['terisi']);
        $this->assertSame('KEADILAN', $slotProp['parti']);
        $this->assertArrayNotHasKey('petugas_kp', $slotProp);
        $this->assertArrayNotHasKey('petugas_tel', $slotProp);
        $this->assertArrayNotHasKey('petugas_nama', $slotProp);

        // Kekang serangan "kunci lain" — pastikan tiada sebarang jejak
        // mentah IC/tel/nama pengisi terselit di mana-mana dalam JSON
        // penuh yang benar-benar dihantar ke pelayar.
        $response->assertDontSee(self::IC_SAH, false);
        $response->assertDontSee('010-2187454', false);
        $response->assertDontSee('AZMI', false);
    }

    public function test_unknown_token_is_404(): void
    {
        $this->getJson(route('paca.public', 'tidak-wujud'))->assertNotFound();
    }

    public function test_submit_to_an_empty_slot_fills_it(): void
    {
        $pusat = $this->pusat();
        $saluran = $this->saluran($pusat);
        $slot = $this->slot($saluran);

        $res = $this->postJson(route('paca.public.hantar', $pusat->public_token), [
            'paca_slot_id' => $slot->id,
            'petugas_nama' => 'ROSLAN',
            'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '012-3456789',
            'petugas_parti' => 'BEBAS',
        ]);

        $res->assertOk();
        $slot->refresh();
        $this->assertSame('ROSLAN', $slot->petugas_nama);
        $this->assertSame(self::IC_SAH, $slot->petugas_kp);
        $this->assertSame('012-3456789', $slot->petugas_tel);
        $this->assertSame('BEBAS', $slot->petugas_parti);
    }

    public function test_submit_to_an_already_filled_slot_is_422(): void
    {
        $pusat = $this->pusat();
        $saluran = $this->saluran($pusat);
        $slot = $this->slot($saluran, [
            'petugas_nama' => 'SEDIA ADA', 'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '019-1112222', 'petugas_parti' => 'BEBAS',
        ]);

        $res = $this->postJson(route('paca.public.hantar', $pusat->public_token), [
            'paca_slot_id' => $slot->id,
            'petugas_nama' => 'BARU',
            'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '012-3456789',
            'petugas_parti' => 'BEBAS',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('slot ini sudah diisi', json_encode($res->json()));

        $slot->refresh();
        $this->assertSame('SEDIA ADA', $slot->petugas_nama, 'Percubaan menimpa slot terisi tidak sepatutnya menulis apa-apa.');
    }

    public function test_invalid_ic_is_422(): void
    {
        $pusat = $this->pusat();
        $saluran = $this->saluran($pusat);
        $slot = $this->slot($saluran);

        $res = $this->postJson(route('paca.public.hantar', $pusat->public_token), [
            'paca_slot_id' => $slot->id,
            'petugas_nama' => 'ROSLAN',
            'petugas_kp' => '999999-99-9999', // 99 bulan — tarikh tidak sah
            'petugas_tel' => '012-3456789',
            'petugas_parti' => 'BEBAS',
        ]);

        $res->assertStatus(422);
        $slot->refresh();
        $this->assertNull($slot->petugas_nama, 'IC tidak sah tidak sepatutnya mengisi slot.');
    }

    public function test_slot_from_a_different_pusat_is_404(): void
    {
        $pusatA = $this->pusat(['public_token' => 'tok_a']);
        $saluranA = $this->saluran($pusatA);
        $this->slot($saluranA);

        $pusatB = $this->pusat(['public_token' => 'tok_b']);
        $saluranB = $this->saluran($pusatB);
        $slotB = $this->slot($saluranB);

        // Cuba mengisi slot Pusat B melalui token Pusat A.
        $res = $this->postJson(route('paca.public.hantar', $pusatA->public_token), [
            'paca_slot_id' => $slotB->id,
            'petugas_nama' => 'SERANG',
            'petugas_kp' => self::IC_SAH,
            'petugas_tel' => '012-3456789',
            'petugas_parti' => 'BEBAS',
        ]);

        $res->assertNotFound();
        $slotB->refresh();
        $this->assertNull($slotB->petugas_nama, 'Slot Pusat lain tidak sepatutnya terisi melalui token salah.');
    }
}
