<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autosimpan MESTI berkunci pada pertandingan. Tanpa `contest` dalam kunci,
 * satu ketukan kekunci PRU menulis ganti sel PRN pada kedudukan yang sama —
 * kelas pepijat tulis-ganti senyap yang sama seperti key-drift.
 */
class Borang14SerentakWriteTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;
    private Bandar $parlimen;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);
    }

    /** Built by hand, not by factory: UserFactory omits the NOT NULL telephone column. */
    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'serentak@example.test'], [
            'name' => 'Penyelia', 'telephone' => '0123456789', 'password' => bcrypt('rahsia'),
            'role' => 'super_admin', 'status' => 'approved',
        ]);
    }

    private function hantarUndi(string $contest, int $slot, int $undi): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2027,
            'penjuru' => 3,
            'contest' => $contest,
            'pusat' => 'SK GEMAS',
            'saluran' => '3',
            'slot' => $slot,
            'undi' => $undi,
        ]);
    }

    public function test_a_pru_keystroke_does_not_overwrite_the_prn_cell(): void
    {
        $this->hantarUndi('dun', 1, 224)->assertOk();
        $this->hantarUndi('parlimen', 1, 93)->assertOk();

        $form = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();

        $this->assertSame(224, (int) $form->votesFor(Borang14Vote::CONTEST_DUN)
            ->where('saluran', '3')->where('slot', 1)->value('undi'));
        $this->assertSame(93, (int) $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)
            ->where('saluran', '3')->where('slot', 1)->value('undi'));
    }

    public function test_rejected_and_undeposited_ballots_are_counted_per_contest(): void
    {
        // Slot 90 = undi ditolak, 91 = tidak dimasukkan. Kertas undi boleh rosak
        // dalam SATU pertandingan sahaja.
        $this->hantarUndi('dun', 90, 5)->assertOk();
        $this->hantarUndi('parlimen', 90, 8)->assertOk();

        $form = Borang14Form::where('kawasan_id', $this->dun->id)->firstOrFail();

        $this->assertSame(5, (int) $form->votesFor(Borang14Vote::CONTEST_DUN)->where('slot', 90)->value('undi'));
        $this->assertSame(8, (int) $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->where('slot', 90)->value('undi'));
    }

    public function test_contest_is_required(): void
    {
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.vote'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 3,
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 10,
        ])->assertStatus(422);
    }

    public function test_a_form_reports_its_own_contest(): void
    {
        $dunForm = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
        ]);
        $parlimenForm = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'parties' => [],
        ]);

        $this->assertSame('dun', $dunForm->contestSendiri());
        $this->assertSame('parlimen', $parlimenForm->contestSendiri());
    }

    public function test_a_dun_form_can_link_to_its_parlimen_definition(): void
    {
        $parlimenForm = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PN'], ['slot' => 3, 'nama' => 'PH']],
        ]);
        $dunForm = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $parlimenForm->id,
        ]);

        $this->assertSame($parlimenForm->id, $dunForm->formParlimen->id);
        $this->assertCount(3, $dunForm->formParlimen->parties);
    }
}
