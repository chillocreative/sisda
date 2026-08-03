<?php
// tests/Feature/Borang14KunciTest.php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Snapshot;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\KeahlianParti;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kunci Borang 14 — pembekuan rekod yang sudah siap.
 *
 * DUA invarian dipaku di sini, dan kedua-duanya mudah hilang secara senyap:
 *
 *   1. Kunci menyekat SETIAP laluan tulis, bukan sekadar sel undi. Skrin
 *      Borang 14 mempunyai enam pintu tulis yang berlainan (undi, calon/skop,
 *      struktur, reset, revert, muat naik scoresheet) ditambah padam dan
 *      terbit/nyahterbit. Satu pintu yang terlepas bermakna rekod "berkunci"
 *      masih boleh dipinda — kegagalan senyap sepenuhnya.
 *
 *   2. Kunci mengikat SUPER ADMIN juga. Kunci yang boleh dipintas oleh peranan
 *      tertinggi bukan kunci, cuma cadangan — dan pengendali akan mempercayai
 *      lencana DIKUNCI itu.
 */
class Borang14KunciTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $parlimen;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);
    }

    /** @var array<string, User> Satu akaun setiap peranan — dicipta sekali. */
    private array $akaun = [];

    private function pengguna(string $role, ?int $bandarId, string $telefon, ?int $kadunId = null): User
    {
        // Memo pada nombor telefon: lajur itu UNIK, jadi memanggil superAdmin()
        // dua kali dalam satu ujian akan meletup tanpa ini.
        if (isset($this->akaun[$telefon])) {
            return $this->akaun[$telefon];
        }

        return $this->akaun[$telefon] = User::create([
            'name' => "Ujian {$telefon}",
            'email' => "b14kunci{$telefon}@example.test",
            'telephone' => $telefon,
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => 'approved',
            'bandar_id' => $bandarId,
            'kadun_id' => $kadunId,
        ]);
    }

    private function superAdmin(): User
    {
        return $this->pengguna('super_admin', null, '0123470101');
    }

    /** Admin JEMPOL — memiliki kerusi GEMAS. */
    private function admin(): User
    {
        return $this->pengguna('admin', $this->parlimen->id, '0123470102');
    }

    /** Admin Parlimen LAIN — tidak memiliki GEMAS. */
    private function adminLuar(): User
    {
        $lain = Bandar::create(['nama' => 'SEREMBAN', 'kod_parlimen' => 'P128', 'negeri_id' => $this->parlimen->negeri_id]);

        return $this->pengguna('admin', $lain->id, '0123470103');
    }

    private function superUser(): User
    {
        return $this->pengguna('super_user', $this->parlimen->id, '0123470104', $this->dun->id);
    }

    private function borang(array $atribut = []): Borang14Form
    {
        return Borang14Form::create(array_merge([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'status' => 'draft',
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PH']],
            'structure' => ['rows' => [
                ['row_id' => 'r1', 'dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran' => '1'],
            ]],
        ], $atribut));
    }

    private function borangDikunci(array $atribut = []): Borang14Form
    {
        return $this->borang(array_merge(['locked_at' => now()], $atribut));
    }

    /** Muatan penuh laluan undi — dikongsi supaya ujian hanya berbeza pada niatnya. */
    private function muatanUndi(): array
    {
        return [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'contest' => 'dun', 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 123,
        ];
    }

    /* ------------------------------ kebenaran ------------------------------ */

    public function test_super_admin_boleh_kunci_dan_buka_kunci(): void
    {
        $form = $this->borang();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.kunci'), ['form_id' => $form->id])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertNotNull($form->fresh()->locked_at);

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.buka-kunci'), ['form_id' => $form->id])
            ->assertOk()->assertJson(['ok' => true, 'locked_at' => null]);

        $this->assertNull($form->fresh()->locked_at);
        $this->assertNull($form->fresh()->locked_by);
    }

    public function test_admin_pemilik_kerusi_boleh_kunci(): void
    {
        $form = $this->borang();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('pilihanraya.borang-14.kunci'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertNotNull($form->fresh()->locked_at);
        // Jejak audit: siapa membekukan rekod ini.
        $this->assertSame($admin->id, $form->fresh()->locked_by);
    }

    public function test_admin_parlimen_lain_tidak_boleh_kunci(): void
    {
        $form = $this->borang();

        $this->actingAs($this->adminLuar())
            ->postJson(route('pilihanraya.borang-14.kunci'), ['form_id' => $form->id])
            ->assertForbidden();

        $this->assertNull($form->fresh()->locked_at);
    }

    /**
     * super_user MEMILIKI kerusi ini (SeatScope membenarkannya menulis undi),
     * tetapi kunci ialah kawalan PENYELIAAN: orang yang mengisi borang tidak
     * boleh membuka kunci kerjanya sendiri.
     */
    public function test_super_user_tidak_boleh_kunci_walaupun_memiliki_kerusi(): void
    {
        $form = $this->borang();

        $this->actingAs($this->superUser())
            ->postJson(route('pilihanraya.borang-14.kunci'), ['form_id' => $form->id])
            ->assertForbidden();

        $this->assertNull($form->fresh()->locked_at);
    }

    public function test_super_user_tidak_boleh_buka_kunci(): void
    {
        $form = $this->borangDikunci();

        $this->actingAs($this->superUser())
            ->postJson(route('pilihanraya.borang-14.buka-kunci'), ['form_id' => $form->id])
            ->assertForbidden();

        $this->assertNotNull($form->fresh()->locked_at);
    }

    /** Kunci berulang tidak menulis ganti pengunci ASAL — itu jejak auditnya. */
    public function test_kunci_idempoten_mengekalkan_pengunci_asal(): void
    {
        $form = $this->borang();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('pilihanraya.borang-14.kunci'), ['form_id' => $form->id])->assertOk();
        $capMasaAsal = $form->fresh()->locked_at;

        $this->actingAs($this->superAdmin())->postJson(route('pilihanraya.borang-14.kunci'), ['form_id' => $form->id])->assertOk();

        $this->assertSame($admin->id, $form->fresh()->locked_by);
        $this->assertEquals($capMasaAsal, $form->fresh()->locked_at);
    }

    /* --------------------------- laluan tulis disekat ---------------------- */

    public function test_borang_dikunci_menolak_undi(): void
    {
        $form = $this->borangDikunci();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanUndi())
            ->assertStatus(422);

        $this->assertSame(0, $form->votes()->count());
    }

    public function test_borang_terbuka_masih_menerima_undi(): void
    {
        // Garis dasar — tanpa ini, ujian di atas boleh lulus atas sebab lain.
        $form = $this->borang();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanUndi())
            ->assertOk();

        $this->assertSame(123, (int) $form->votes()->first()->undi);
    }

    public function test_borang_dikunci_menolak_reset(): void
    {
        $form = $this->borangDikunci();
        Borang14Vote::create([
            'borang14_form_id' => $form->id, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 50,
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.reset'), [
                'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
                'jenis_pr' => 'prn', 'tahun' => 2027, 'contest' => 'dun',
            ])
            ->assertStatus(422);

        $this->assertSame(1, $form->votes()->count());
    }

    public function test_borang_dikunci_menolak_penetapan_calon(): void
    {
        $form = $this->borangDikunci();
        $parti = KeahlianParti::create(['nama' => 'PN']);

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.parties'), [
                'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
                'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
                'parties' => [
                    ['slot' => 1, 'keahlian_parti_id' => $parti->id, 'nama' => 'PN'],
                    ['slot' => 2, 'keahlian_parti_id' => null, 'nama' => 'PH'],
                ],
            ])
            // 422 dan BUKAN 403: pengguna memang berhak menyentuh kerusi ini,
            // rekod itu yang dibekukan. 403 di sini akan menghantar pengendali
            // memburu masalah kebenaran yang tidak wujud.
            ->assertStatus(422);

        $this->assertSame('BN', $form->fresh()->parties[0]['nama']);
    }

    public function test_borang_dikunci_menolak_sunting_struktur(): void
    {
        $form = $this->borangDikunci();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.struktur'), [
                'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
                'jenis_pr' => 'prn', 'tahun' => 2027,
                'pusat' => [['row_id' => 'r1', 'dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS BARU', 'saluran_count' => 2]],
            ])
            ->assertStatus(403);

        $this->assertSame('SK GEMAS', $form->fresh()->structure['rows'][0]['pusat']);
    }

    public function test_borang_dikunci_menolak_revert(): void
    {
        $form = $this->borangDikunci();
        Borang14Snapshot::create([
            'borang14_form_id' => $form->id,
            'structure' => $form->structure,
            'votes' => [],
            'parties' => $form->parties,
            'reason' => 'ujian',
        ]);
        Borang14Vote::create([
            'borang14_form_id' => $form->id, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 77,
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.revert'), ['form_id' => $form->id])
            ->assertStatus(422);

        // Undi kekal — revert akan memadamnya.
        $this->assertSame(1, $form->votes()->count());
    }

    public function test_borang_dikunci_menolak_padam(): void
    {
        $form = $this->borangDikunci();

        $this->actingAs($this->superAdmin())
            ->deleteJson(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertStatus(422);

        $this->assertDatabaseHas('borang14_forms', ['id' => $form->id]);
    }

    public function test_borang_dikunci_menolak_terbit(): void
    {
        $form = $this->borangDikunci();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.publish'), ['form_id' => $form->id])
            ->assertStatus(422);

        $this->assertSame('draft', $form->fresh()->status);
    }

    public function test_borang_dikunci_menolak_nyahterbit(): void
    {
        $form = $this->borangDikunci(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.unpublish'), ['form_id' => $form->id])
            ->assertStatus(422);

        $this->assertSame('published', $form->fresh()->status);
    }

    /* ----------------------------- muatan skrin ---------------------------- */

    public function test_data_melaporkan_keadaan_kunci_dan_kelayakan(): void
    {
        $form = $this->borangDikunci();

        $this->actingAs($this->superAdmin())
            ->getJson(route('pilihanraya.borang-14.data', [
                'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
                'jenis_pr' => 'prn', 'tahun' => 2027,
            ]))
            ->assertOk()
            ->assertJsonPath('form.locked', true)
            ->assertJsonPath('boleh_kunci', true)
            // Kunci mesti turut menutup panel Sunting Struktur — jika tidak,
            // butangnya kekal dan hanya gagal apabila ditekan.
            ->assertJsonPath('boleh_sunting_struktur', false);

        $form->update(['locked_at' => null]);

        $this->actingAs($this->superAdmin())
            ->getJson(route('pilihanraya.borang-14.data', [
                'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
                'jenis_pr' => 'prn', 'tahun' => 2027,
            ]))
            ->assertOk()
            ->assertJsonPath('form.locked', false);
    }

    public function test_senarai_membawa_locked_at_dan_kelayakan_setiap_baris(): void
    {
        $this->borangDikunci();

        // Admin Parlimen LAIN nampak baris itu (penapis senarai ikut negeri)
        // tetapi tidak boleh menguruskan kuncinya.
        $this->actingAs($this->adminLuar())
            ->getJson(route('pilihanraya.borang-14.senarai', ['negeri_id' => $this->parlimen->negeri_id]))
            ->assertOk()
            ->assertJsonPath('rows.0.boleh_kunci', false);

        $respons = $this->actingAs($this->admin())
            ->getJson(route('pilihanraya.borang-14.senarai', ['negeri_id' => $this->parlimen->negeri_id]))
            ->assertOk()
            ->assertJsonPath('rows.0.boleh_kunci', true);

        // Cap masa kunci mesti benar-benar sampai ke senarai — tanpanya
        // lencana DIKUNCI tidak boleh dipapar langsung.
        $this->assertNotNull($respons->json('rows.0.locked_at'));
    }

    /** Buka kunci memulihkan laluan tulis sepenuhnya — bukan sekadar lencana. */
    public function test_buka_kunci_memulihkan_laluan_tulis(): void
    {
        $form = $this->borangDikunci();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.buka-kunci'), ['form_id' => $form->id])
            ->assertOk();

        $this->actingAs($this->superAdmin())
            ->postJson(route('pilihanraya.borang-14.vote'), $this->muatanUndi())
            ->assertOk();

        $this->assertSame(123, (int) $form->votes()->first()->undi);
    }
}
