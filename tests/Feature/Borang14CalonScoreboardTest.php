<?php
// tests/Feature/Borang14CalonScoreboardTest.php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\PangkalanDataPengundi;
use App\Models\Scoreboard;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser as PdfParser;
use Tests\TestCase;

/**
 * Lajur "Calon" pada muka ringkasan Borang 14 mengambil nama daripada papan
 * markah (scoreboards.candidates) apabila borang itu sendiri tidak menamakan
 * calon — Borang 14 menyimpan undi mengikut SLOT dan tidak mewajibkan nama.
 *
 * PAGAR YANG DIUJI DI SINI ialah ikatan borang: satu kerusi memegang SATU
 * papan sahaja tetapi banyak borang merentas tahun dan jenis PR. Memadankan
 * mengikut kerusi semata-mata akan mencetak calon PRN 2026 pada Borang 14
 * PRU 2023 kerusi yang sama, di bawah undi orang lain — nama yang salah pada
 * cetakan rasmi lebih teruk daripada '—'.
 */
class Borang14CalonScoreboardTest extends TestCase
{
    use RefreshDatabase;

    private Negeri $negeri;

    private Bandar $bandar;

    private Kadun $kadun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->bandar = Bandar::create(['nama' => 'TAMPIN', 'kod_parlimen' => 'P132', 'negeri_id' => $this->negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->bandar->id]);

        $pengguna = User::create([
            'name' => 'Penyemai', 'email' => 'semai@example.test', 'telephone' => '0123470001',
            'password' => bcrypt('rahsia'), 'role' => 'admin', 'status' => 'approved',
        ]);
        $batch = UploadBatch::create([
            'nama_fail' => 'ujian.csv', 'fail_path' => 'ujian.csv',
            'jumlah_rekod' => 1, 'status' => 'completed', 'is_active' => true,
            'uploaded_by' => $pengguna->id,
        ]);
        PangkalanDataPengundi::create([
            'upload_batch_id' => $batch->id,
            'no_ic' => '900101011234', 'nama' => 'Pengundi Ujian',
            'lokaliti' => 'KG GEMAS', 'daerah_mengundi' => 'PEKAN GEMAS',
            'kadun' => 'GEMAS', 'parlimen' => 'TAMPIN', 'negeri' => 'NEGERI SEMBILAN',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Ujian', 'email' => 'admin@example.test', 'telephone' => '0123470002',
            'password' => bcrypt('rahsia'), 'role' => 'super_admin', 'status' => 'approved',
        ]);
    }

    private function borang(int $tahun = 2026, string $jenis = 'prn', array $parties = []): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => $jenis, 'tahun' => $tahun, 'penjuru' => 2, 'status' => 'published',
            'parties' => $parties ?: [['slot' => 1, 'nama' => 'PERIKATAN NASIONAL'], ['slot' => 2, 'nama' => 'PAKATAN HARAPAN']],
        ]);
    }

    private function papan(?Borang14Form $terikatPada, array $candidates): Scoreboard
    {
        return Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'borang14_form_id' => $terikatPada?->id,
            'title' => 'SCOREBOARD', 'status' => Scoreboard::STATUS_TERSIAR,
            'candidates' => $candidates,
        ]);
    }

    private function teksPdf(Borang14Form $form): string
    {
        $res = $this->actingAs($this->admin())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => $form->jenis_pr, 'tahun' => $form->tahun, 'penjuru' => 2,
        ]));
        $res->assertOk();

        return (new PdfParser())->parseContent($res->getContent())->getText();
    }

    public function test_nama_calon_diambil_daripada_papan_markah_kerusi_yang_sama(): void
    {
        $form = $this->borang();
        $this->papan($form, [
            ['slot' => 1, 'nama' => 'Ahmad bin Ismail'],
            ['slot' => 2, 'nama' => 'Chan Wei Ling'],
        ]);

        $teks = $this->teksPdf($form);

        $this->assertStringContainsString('Ahmad bin Ismail', $teks);
        $this->assertStringContainsString('Chan Wei Ling', $teks);
        // Asal nama dinyatakan pada cetakan.
        $this->assertStringContainsString('papan markah', $teks);
    }

    /**
     * Papan terikat pada borang LAIN (tahun/jenis berbeza) tidak boleh
     * menamakan calon borang ini. Ini kegagalan yang paling merosakkan.
     */
    public function test_papan_terikat_pada_borang_lain_tidak_menamakan_calon(): void
    {
        $lama = $this->borang(2023, 'pru');
        $semasa = $this->borang(2026, 'prn');
        $this->papan($semasa, [
            ['slot' => 1, 'nama' => 'Ahmad bin Ismail'],
            ['slot' => 2, 'nama' => 'Chan Wei Ling'],
        ]);

        // Cetakan bagi borang LAMA — papan menunjuk borang 2026, bukan ini.
        $teks = $this->teksPdf($lama);

        $this->assertStringNotContainsString('Ahmad bin Ismail', $teks);
        $this->assertStringNotContainsString('Chan Wei Ling', $teks);
        $this->assertStringNotContainsString('papan markah', $teks);
    }

    /** Papan yang belum memilih sumber Borang 14 langsung tidak dipercayai. */
    public function test_papan_tanpa_ikatan_borang_tidak_menamakan_calon(): void
    {
        $form = $this->borang();
        $this->papan(null, [['slot' => 1, 'nama' => 'Ahmad bin Ismail']]);

        $this->assertStringNotContainsString('Ahmad bin Ismail', $this->teksPdf($form));
    }

    /** Borang ialah rekod utama: nama yang sudah ada padanya tidak ditimpa. */
    public function test_nama_pada_borang_tidak_ditimpa_oleh_papan(): void
    {
        $form = $this->borang(2026, 'prn', [
            ['slot' => 1, 'nama' => 'PERIKATAN NASIONAL', 'calon' => 'Nama Pada Borang'],
            ['slot' => 2, 'nama' => 'PAKATAN HARAPAN'],
        ]);
        $this->papan($form, [
            ['slot' => 1, 'nama' => 'Nama Pada Papan'],
            ['slot' => 2, 'nama' => 'Chan Wei Ling'],
        ]);

        $teks = $this->teksPdf($form);

        $this->assertStringContainsString('Nama Pada Borang', $teks);
        $this->assertStringNotContainsString('Nama Pada Papan', $teks);
        // Slot 2 yang memang kosong tetap diisi.
        $this->assertStringContainsString('Chan Wei Ling', $teks);
    }

    /** Slot yang tiada nama pada papan kekal '—', bukan nama slot jiran. */
    public function test_slot_tanpa_nama_pada_papan_kekal_kosong(): void
    {
        $form = $this->borang();
        $this->papan($form, [
            ['slot' => 1, 'nama' => 'Ahmad bin Ismail'],
            ['slot' => 2, 'nama' => '   '],
        ]);

        $teks = $this->teksPdf($form);

        $this->assertStringContainsString('Ahmad bin Ismail', $teks);
        $this->assertStringContainsString('—', $teks);
    }

    /** Tiada papan langsung: tingkah laku lama, tiada ralat. */
    public function test_tiada_papan_markah_tetap_mencetak_dengan_sengkang(): void
    {
        $form = $this->borang();

        $teks = $this->teksPdf($form);

        $this->assertStringContainsString('RINGKASAN KEPUTUSAN', $teks);
        $this->assertStringNotContainsString('papan markah', $teks);
    }
}
