<?php
// tests/Feature/Borang14PdfTest.php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser as PdfParser;
use Tests\TestCase;

/**
 * Task 11 gap 1: a Parlimen-type Borang 14 form must be able to export PDF,
 * not just DUN. Covers: parlimen PDF, DUN PDF, wrong-table id rejection, and
 * the Buloh Kasap Undi Awal/Pos merge staying DUN-only even when a Parlimen
 * happens to share id 41.
 */
class Borang14PdfTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123456789']);
    }

    /** Seeds pangkalan_data_pengundi so Borang14Reference::forBandar()/forKadun() resolve non-null. */
    private function seedDpt(string $negeriNama, string $parlimenNama, ?string $kadunNama, string $dm, string $lokaliti): void
    {
        $user = User::factory()->create(['telephone' => '0123456781']);
        $batch = UploadBatch::create([
            'nama_fail' => 'ujian.csv', 'fail_path' => 'ujian.csv',
            'jumlah_rekod' => 1, 'status' => 'completed', 'is_active' => true,
            'uploaded_by' => $user->id,
        ]);

        PangkalanDataPengundi::create([
            'upload_batch_id' => $batch->id,
            'no_ic' => '900101011234',
            'nama' => 'Pengundi Ujian',
            'lokaliti' => $lokaliti,
            'daerah_mengundi' => $dm,
            'kadun' => $kadunNama,
            'parlimen' => $parlimenNama,
            'negeri' => $negeriNama,
        ]);
    }

    public function test_pdf_works_for_dun_type_form(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);
        $this->seedDpt('Negeri Ujian', 'Parlimen Ujian', 'Dun Ujian', 'DM Ujian', 'Kg Ujian');

        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2,
        ]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_pdf_works_for_parlimen_type_form(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $this->seedDpt('Negeri Ujian', 'Parlimen Ujian', null, 'DM Ujian', 'Kg Ujian');

        Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2,
        ]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_kawasan_id_from_wrong_table_is_rejected(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        // Sengaja TIDAK cipta sebarang kadun — bandar->id tidak wujud dalam jadual kadun.

        $res = $this->actingAs($this->adminUser())->getJson(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2,
        ]));

        $res->assertStatus(422);
    }

    public function test_buloh_kasap_merge_does_not_apply_to_parlimen_with_same_id(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        // Cipta bandar (Parlimen) dengan ID 41 secara eksplisit — sama seperti
        // BULOH_KASAP_KADUN_ID — untuk buktikan flag tersebut DUN-sahaja.
        $bandar = Bandar::create(['nama' => 'Parlimen 41', 'negeri_id' => $negeri->id]);
        \DB::table('bandar')->where('id', $bandar->id)->update(['id' => 41]);
        $bandar = Bandar::find(41);
        $this->seedDpt('Negeri Ujian', 'Parlimen 41', null, 'DM Ujian', 'Kg Ujian');

        Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => 41,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'parlimen', 'kawasan_id' => 41,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2,
        ]));

        $res->assertOk();

        $parser = new PdfParser();
        $text = $parser->parseContent($res->getContent())->getText();

        $this->assertStringNotContainsString('UNDI AWAL & POS', $text, 'Parlimen ID 41 TIDAK sepatutnya menggabungkan Undi Awal & Pos — itu kekecualian khas DUN Buloh Kasap sahaja.');
        $this->assertStringContainsString('UNDI AWAL', $text);
        $this->assertStringContainsString('UNDI POS', $text);
    }

    /**
     * Finding 4 (Important): pdf() still aborted 404 whenever
     * Borang14Reference::forKadun()/forBandar() returned null — but data()
     * already falls back to referenceFromStructure() for scoresheet-only
     * seats (no curated reference, no DPT roll uploaded). Every seat this
     * feature creates via upload is exactly that case, so "Muat Turun PDF"
     * always failed for them. pdf() must get the same fallback; the 404
     * should only remain for the genuinely-no-data case (no reference AND no
     * saved structure).
     */
    public function test_pdf_falls_back_to_structure_reference_for_scoresheet_only_seat(): void
    {
        // Deliberately NO seedDpt() call — this seat has no DPT roll and no
        // curated reference JSON, exactly like every seat this feature creates.
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Juasseh Ujian', 'bandar_id' => $bandar->id]);

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'A'], ['nama' => 'B']],
                'rows' => [[
                    'pusat' => 'PM Ujian', 'dm' => 'DM Ujian', 'saluran' => '1',
                    'a' => 10, 'undi' => [6, 4], 'jumlah_undian' => 10, 'ditolak' => 0, 'tidak_dimasukkan' => 0,
                ]],
            ],
        ]);
        $form->votes()->create(['pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 1, 'undi' => 6]);
        $form->votes()->create(['pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 2, 'undi' => 4]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
        ]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_pdf_still_404s_when_there_is_genuinely_no_data(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);
        // No DPT, no curated reference, no form/structure at all.

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
        ]));

        $res->assertStatus(404);
    }

    public function test_senarai_returns_kawasan_and_geography_ids(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);

        Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $res = $this->actingAs($this->adminUser())->getJson(route('pilihanraya.borang-14.senarai', [
            'negeri_id' => $negeri->id,
        ]));

        $res->assertOk()->assertJsonCount(2, 'rows');

        $rows = collect($res->json('rows'));
        $parlimenRow = $rows->firstWhere('kawasan_type', 'parlimen');
        $dunRow = $rows->firstWhere('kawasan_type', 'dun');

        $this->assertSame($bandar->id, $parlimenRow['kawasan_id']);
        $this->assertSame($negeri->id, $parlimenRow['negeri_id']);
        $this->assertSame($bandar->id, $parlimenRow['bandar_id']);

        $this->assertSame($kadun->id, $dunRow['kawasan_id']);
        $this->assertSame($negeri->id, $dunRow['negeri_id']);
        $this->assertSame($bandar->id, $dunRow['bandar_id']);
    }
}
