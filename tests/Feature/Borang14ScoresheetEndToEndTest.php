<?php
// tests/Feature/Borang14ScoresheetEndToEndTest.php
//
// Aliran penuh dengan fail SEBENAR: muat naik "Score Sheet Juasseh - PRN N9 -
// 2023.pdf" melalui titik akhir muat naik, kemudian baca undi yang tersimpan
// sebagaimana skrin Keyin membacanya.
//
// Ini ialah semakan yang benar-benar gagal di produksi: Keyin memaparkan
// PN 98 / BN 73 (baris UNDI POS) sedangkan sheet mencetak 4,471 / 4,549.
// Tiada mock — laluan deterministik tidak memanggil Claude, jadi keseluruhan
// rantaian (parser -> pengawal -> baris undi) diuji sebagaimana ia berjalan.
namespace Tests\Feature;

use App\Models\Borang14Form;
use App\Models\Borang14Upload;
use App\Models\Borang14Vote;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Borang14ScoresheetEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = __DIR__.'/../Fixtures/Pilihanraya/spr760-juasseh-2023.pdf';

    protected function setUp(): void
    {
        parent::setUp();
        Negeri::create(['nama' => 'Negeri Sembilan']);
        Storage::fake('private');
    }

    private function upload(User $user): array
    {
        // Salinan: UploadedFile dalam mod ujian mengalihkan fail asal, dan
        // fixture itu dikongsi dengan ujian lain.
        $tmp = tempnam(sys_get_temp_dir(), 'spr760').'.pdf';
        copy(self::PDF, $tmp);

        $dry = $this->actingAs($user)->post(route('pilihanraya.borang-14.upload'), [
            'dry_run' => 1,
            'fail' => new UploadedFile($tmp, 'Score Sheet Juasseh - PRN N9 - 2023.pdf', 'application/pdf', null, true),
            'jenis_pr' => 'prn',
            'tahun' => 2023,
        ])->assertOk();

        $commit = $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $dry->json('token')])
            ->assertOk();

        return [$dry->json(), $commit->json()];
    }

    private function user(): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia ada).
        return User::factory()->create(['role' => 'super_admin', 'telephone' => '0123459999']);
    }

    /** Fail sebenar mesti dibaca tanpa AI langsung. */
    public function test_the_real_pdf_is_read_deterministically(): void
    {
        [$dry] = $this->upload($this->user());

        $this->assertSame('deterministic', $dry['source']);
        $this->assertSame('NEGERI SEMBILAN', $dry['negeri']);
        $this->assertSame('JUASSEH', $dry['kawasan_nama']);
        $this->assertSame('dun', $dry['kawasan_type']);

        // Aritmetik sheet ini lengkap dan sepadan — tiada percanggahan langsung.
        $this->assertSame([], $dry['unbalanced']);
    }

    /**
     * ASSERTION UTAMA. Undi yang tersimpan mesti berjumlah kepada baris JUMLAH
     * bercetak, bukan baris UNDI POS.
     */
    public function test_stored_votes_total_the_printed_jumlah(): void
    {
        $this->upload($this->user());

        $form = Borang14Form::firstOrFail();
        $undi = fn (int $slot) => (int) Borang14Vote::where('borang14_form_id', $form->id)
            ->where('slot', $slot)->sum('undi');

        $this->assertSame(4471, $undi(1), 'PN mesti 4,471 — bukan 98 (baris UNDI POS).');
        $this->assertSame(4549, $undi(2), 'BN mesti 4,549 — bukan 73 (baris UNDI POS).');
        $this->assertSame(87, $undi(90), 'Undi ditolak (C).');
        $this->assertSame(15, $undi(91), 'Tidak dimasukkan (D).');
    }

    /** 39 saluran merentas 11 Daerah Mengundi + satu baris UNDI POS peringkat DUN. */
    public function test_every_saluran_is_stored_separately(): void
    {
        $this->upload($this->user());

        $form = Borang14Form::firstOrFail();
        $rows = Borang14Vote::where('borang14_form_id', $form->id)
            ->where('slot', 1)->get(['pusat', 'saluran']);

        $this->assertCount(40, $rows);
        $this->assertSame(1, $rows->where('pusat', '')->count(), 'hanya UNDI POS yang peringkat DUN');
        $this->assertSame(11, $rows->where('pusat', '!=', '')->pluck('pusat')->unique()->count());
    }

    /**
     * Nama calon bergabung menjadi satu item teks pada sheet, jadi slot diberi
     * nama sementara dan draf DITANDA perlu semakan. Draf yang lulus senyap
     * dengan nama tekaan ialah risiko yang lebih besar daripada bendera ini.
     */
    public function test_candidate_slots_are_placeholders_flagged_for_review(): void
    {
        [, $commit] = $this->upload($this->user());

        $this->assertTrue($commit['needs_review']);
        $this->assertSame(
            ['CALON 1', 'CALON 2'],
            array_column(Borang14Form::firstOrFail()->parties, 'calon'),
        );
    }

    public function test_the_upload_is_recorded_with_both_totals(): void
    {
        $this->upload($this->user());

        $upload = Borang14Upload::firstOrFail();

        $this->assertSame('deterministic', $upload->source);
        $this->assertSame(40, $upload->row_count);
        $this->assertSame(40, $upload->saluran_count);
        $this->assertSame([4471, 4549], $upload->totals['dicetak']['undi']);
        $this->assertSame([4471, 4549], $upload->totals['dikira']['undi']);
        $this->assertSame(13408, $upload->totals['pemilih']);
        $this->assertSame([], $upload->totals['percanggahan']);
        Storage::disk('private')->assertExists($upload->fail_path);
    }
}
