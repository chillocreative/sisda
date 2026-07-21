<?php
// tests/Feature/Borang14UploadHistoryTest.php
//
// Sebelum ini muat naik scoresheet tidak meninggalkan sebarang jejak: hasil
// ekstrak hanya hidup dalam Cache selama beberapa minit dan fail asal tidak
// pernah ditulis ke cakera. Ujian ini mengunci tingkah laku baharu — setiap
// commit merekod satu baris sejarah, fail asal disimpan pada disk PERSENDIRIAN,
// dan muat turun disemak semula terhadap skop pengguna.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Upload;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use App\Services\Pilihanraya\ScoresheetExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Borang14UploadHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Negeri::create(['nama' => 'Negeri Sembilan']);
        Storage::fake('private');
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/scoresheet-juasseh-2023.json')), true);
    }

    private function user(string $role = 'super_admin', array $over = []): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia
        // ada) — ditetapkan di sini seperti ujian lain dalam suite ini.
        return User::factory()->create(array_merge([
            'role' => $role,
            'telephone' => '01234'.random_int(10000, 99999),
        ], $over));
    }

    /** Jalankan aliran dua langkah penuh dan pulangkan baris sejarah yang terhasil. */
    private function commit(User $user, ?array $data = null): Borang14Upload
    {
        $this->mock(ScoresheetExtractor::class, function ($mock) use ($data) {
            $mock->shouldReceive('extractDetailed')->once()
                ->andReturn(['ok' => true, 'data' => ($data ?? $this->fixture()) + ['source' => 'deterministic'], 'error' => null]);
        });

        $token = $this->actingAs($user)->post(route('pilihanraya.borang-14.upload'), [
            'dry_run' => 1,
            'fail' => UploadedFile::fake()->create('Score Sheet Juasseh - PRN N9 - 2023.pdf', 10, 'application/pdf'),
            'jenis_pr' => 'prn',
            'tahun' => 2023,
        ])->assertOk()->json('token');

        $this->actingAs($user)
            ->post(route('pilihanraya.borang-14.upload'), ['token' => $token])
            ->assertOk();

        return Borang14Upload::latest('id')->firstOrFail();
    }

    public function test_commit_records_history_and_stores_the_file(): void
    {
        $user = $this->user();

        $upload = $this->commit($user);

        $this->assertSame('Score Sheet Juasseh - PRN N9 - 2023.pdf', $upload->nama_fail);
        $this->assertSame('JUASSEH', $upload->dun);
        $this->assertSame('Negeri Sembilan', $upload->negeri);
        $this->assertSame('deterministic', $upload->source);
        $this->assertSame('prn', $upload->jenis_pr);
        $this->assertSame(2023, $upload->tahun);
        $this->assertSame($user->id, $upload->uploaded_by);
        $this->assertNotNull($upload->borang14_form_id);

        // Fail asal mesti berada pada disk PERSENDIRIAN — tidak boleh dicapai
        // melalui URL awam.
        $this->assertNotNull($upload->fail_path);
        Storage::disk('private')->assertExists($upload->fail_path);
        $this->assertStringStartsWith('borang14-scoresheets/', $upload->fail_path);
    }

    /**
     * Sejarah menyimpan KEDUA-DUA jumlah — yang dicetak pada sheet dan yang
     * dicampur daripada baris. Perbandingan itulah rekod auditnya: kegagalan
     * produksi (98 lawan 4,471 bercetak) akan kelihatan terus di sini.
     */
    public function test_history_keeps_printed_and_computed_totals_side_by_side(): void
    {
        $upload = $this->commit($this->user());

        $this->assertSame([4471, 4549], $upload->totals['dicetak']['undi']);
        $this->assertSame(9020, $upload->totals['dicetak']['keluar']);
        $this->assertSame(13408, $upload->totals['pemilih']);
        $this->assertArrayHasKey('dikira', $upload->totals);
        $this->assertSame(count($this->fixture()['rows']), $upload->row_count);
    }

    /** Angka yang tidak dicetak kekal null — bukan 0, yang akan dibaca sebagai "sifar pemilih". */
    public function test_absent_registered_voters_stay_null(): void
    {
        $data = $this->fixture();
        unset($data['jumlah_pemilih']);

        $upload = $this->commit($this->user(), $data);

        $this->assertNull($upload->totals['pemilih']);
    }

    public function test_history_listing_returns_the_upload(): void
    {
        $user = $this->user();
        $this->commit($user);

        $rows = $this->actingAs($user)
            ->getJson(route('pilihanraya.borang-14.upload.sejarah'))
            ->assertOk()->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('Score Sheet Juasseh - PRN N9 - 2023.pdf', $rows[0]['nama_fail']);
        $this->assertSame($user->name, $rows[0]['oleh']);
        $this->assertTrue($rows[0]['boleh_muat_turun']);
    }

    public function test_owner_can_download_the_stored_file(): void
    {
        $user = $this->user();
        $upload = $this->commit($user);

        $this->actingAs($user)
            ->get(route('pilihanraya.borang-14.upload.fail', $upload))
            ->assertOk()
            ->assertDownload('Score Sheet Juasseh - PRN N9 - 2023.pdf');
    }

    /**
     * Semakan skop yang penting: admin bagi Parlimen LAIN tidak boleh menstrim
     * scoresheet kerusi ini hanya dengan meneka id muat naik. (Laluan pilihanraya
     * berada di bawah middleware 'admin', jadi admin ialah peranan terendah yang
     * boleh sampai ke sini langsung.)
     */
    public function test_an_admin_from_another_parlimen_cannot_download(): void
    {
        $upload = $this->commit($this->user());

        $lain = Bandar::create(['nama' => 'PARLIMEN LAIN', 'negeri_id' => Negeri::first()->id]);
        $penceroboh = $this->user('admin', ['bandar_id' => $lain->id]);

        $this->actingAs($penceroboh)
            ->get(route('pilihanraya.borang-14.upload.fail', $upload))
            ->assertForbidden();
    }

    public function test_the_admin_of_the_parlimen_can_download(): void
    {
        $upload = $this->commit($this->user());
        $pemilik = $this->user('admin', ['bandar_id' => Kadun::find($upload->kawasan_id)->bandar_id]);

        $this->actingAs($pemilik)
            ->get(route('pilihanraya.borang-14.upload.fail', $upload))
            ->assertOk();
    }

    /** Fail yang sudah tiada pada cakera tidak boleh menyebabkan ralat pelayan. */
    public function test_missing_file_returns_not_found(): void
    {
        $user = $this->user();
        $upload = $this->commit($user);
        Storage::disk('private')->delete($upload->fail_path);

        $this->actingAs($user)
            ->get(route('pilihanraya.borang-14.upload.fail', $upload))
            ->assertNotFound();
    }
}
