<?php
// tests/Feature/Borang14HapusTest.php
//
// Memadam satu rekod Borang 14 membuang undi SEBENAR. Ujian ini mengunci dua
// perlindungan: arkib ditulis SEBELUM padaman (jadi silap klik boleh dipulihkan)
// dan rekod DITERBITKAN hanya boleh dipadam oleh super_admin.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14DeletedForm;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14HapusTest extends TestCase
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

    private function user(string $role, array $over = []): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia ada).
        // `status` DILULUSKAN kerana peraturan kerusi (SeatScope) menolak akaun
        // yang masih menunggu kelulusan — lalai lajur itu ialah 'pending'.
        return User::factory()->create(array_merge([
            'role' => $role,
            'telephone' => '01277'.random_int(10000, 99999),
            'status' => 'approved',
        ], $over));
    }

    private function form(string $status = 'draft'): Borang14Form
    {
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
            'status' => $status, 'source' => 'scoresheet',
            'parties' => [['slot' => 1, 'nama' => 'PN'], ['slot' => 2, 'nama' => 'BN']],
            'structure' => ['rows' => []],
        ]);

        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => '', 'saluran' => 'UNDI POS', 'slot' => 1, 'undi' => 98]);
        Borang14Vote::create(['borang14_form_id' => $form->id, 'pusat' => 'SK TENGKEK', 'saluran' => '1', 'slot' => 1, 'undi' => 48]);

        return $form;
    }

    public function test_super_admin_can_delete_and_the_votes_go_with_it(): void
    {
        $form = $this->form();

        $this->actingAs($this->user('super_admin'))
            ->delete(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertDatabaseMissing('borang14_forms', ['id' => $form->id]);
        $this->assertSame(0, Borang14Vote::where('borang14_form_id', $form->id)->count());
    }

    /**
     * Perlindungan yang paling penting: undi diarkibkan SEBELUM dipadam, jadi
     * satu klik pada baris yang salah tidak memusnahkan keputusan sebenar.
     */
    public function test_the_record_is_archived_before_deletion(): void
    {
        $form = $this->form('published');

        $this->actingAs($this->user('super_admin'))
            ->delete(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertOk();

        $arkib = Borang14DeletedForm::firstOrFail();
        $this->assertSame('Juasseh', $arkib->kawasan_nama);
        $this->assertSame('prn', $arkib->jenis_pr);
        $this->assertSame(2023, $arkib->tahun);
        $this->assertSame('published', $arkib->status);
        $this->assertCount(2, $arkib->votes);
        $this->assertSame(98, $arkib->votes[0]['undi']);
        $this->assertCount(2, $arkib->parties);
    }

    public function test_an_admin_may_delete_a_draft_in_their_own_parlimen(): void
    {
        $form = $this->form('draft');
        $admin = $this->user('admin', ['bandar_id' => $this->kadun->bandar_id]);

        $this->actingAs($admin)
            ->delete(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertOk();

        $this->assertDatabaseMissing('borang14_forms', ['id' => $form->id]);
    }

    /** Rekod DITERBITKAN ialah keputusan rasmi — admin tidak boleh membuangnya. */
    public function test_an_admin_may_not_delete_a_published_record(): void
    {
        $form = $this->form('published');
        $admin = $this->user('admin', ['bandar_id' => $this->kadun->bandar_id]);

        $this->actingAs($admin)
            ->delete(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertForbidden();

        $this->assertDatabaseHas('borang14_forms', ['id' => $form->id]);
        $this->assertSame(0, Borang14DeletedForm::count());
    }

    public function test_an_admin_may_not_delete_another_parlimens_record(): void
    {
        $form = $this->form('draft');
        $lain = Bandar::create(['nama' => 'PARLIMEN LAIN', 'negeri_id' => Negeri::first()->id]);

        $this->actingAs($this->user('admin', ['bandar_id' => $lain->id]))
            ->delete(route('pilihanraya.borang-14.hapus'), ['form_id' => $form->id])
            ->assertForbidden();

        $this->assertDatabaseHas('borang14_forms', ['id' => $form->id]);
    }
}
