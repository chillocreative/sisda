<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Snapshot;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * revert() ialah laluan paling tinggi akibatnya dalam cawangan ini: ia
 * MEMADAM kedua-dua pertandingan pada satu borang serentak ($form->votes()
 * tanpa penapis contest — betul, kerana snapshot merakam kedua-duanya) lalu
 * membina semula daripada snapshot.
 *
 * Kalau `contest` tercicir di mana-mana dalam kitaran rakam→pulih, akibatnya
 * SENYAP: baris pulih akan jatuh ke pertandingan lalai borang (DUN), jadi
 * undi PRU muncul semula sebagai undi PRN — jumlah DUN membengkak, jumlah
 * Parlimen jatuh ke sifar, dan tiada apa-apa ralat dilaporkan.
 */
class Borang14SerentakRevertTest extends TestCase
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

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'revert@example.test'], [
            'name' => 'Penyelia', 'telephone' => '0123450002', 'password' => bcrypt('rahsia'),
            'role' => 'super_admin', 'status' => 'approved',
        ]);
    }

    /** ['dun|SK GEMAS|1|1' => 224, ...] — kunci sel LENGKAP termasuk pertandingan. */
    private function petaUndi(Borang14Form $form): array
    {
        return $form->votes()->get(['contest', 'pusat', 'saluran', 'slot', 'undi'])
            ->mapWithKeys(fn ($v) => ["{$v->contest}|{$v->pusat}|{$v->saluran}|{$v->slot}" => (int) $v->undi])
            ->sortKeys()->all();
    }

    public function test_revert_restores_both_contests_exactly_with_no_cross_contest_bleed(): void
    {
        $takrifan = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'structure' => null,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PN'], ['slot' => 3, 'nama' => 'PH']],
        ]);

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PH']],
            'structure' => ['rows' => [['dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran' => '1']]],
            'borang14_form_parlimen_id' => $takrifan->id,
        ]);

        // Keadaan ASAL: kedua-dua pertandingan pada saluran FIZIKAL yang sama,
        // termasuk slot khas 90/91 supaya baris bukan-calon turut diuji.
        $asal = [
            [Borang14Vote::CONTEST_DUN, '1', 1, 224],
            [Borang14Vote::CONTEST_DUN, '1', 2, 118],
            [Borang14Vote::CONTEST_DUN, '1', 90, 7],
            [Borang14Vote::CONTEST_DUN, '2', 1, 301],
            [Borang14Vote::CONTEST_PARLIMEN, '1', 1, 93],
            [Borang14Vote::CONTEST_PARLIMEN, '1', 2, 88],
            [Borang14Vote::CONTEST_PARLIMEN, '1', 3, 45],
            [Borang14Vote::CONTEST_PARLIMEN, '1', 91, 2],
            [Borang14Vote::CONTEST_PARLIMEN, '2', 1, 150],
        ];
        foreach ($asal as [$contest, $saluran, $slot, $undi]) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id, 'contest' => $contest,
                'pusat' => 'SK GEMAS', 'saluran' => $saluran, 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        $sebelum = $this->petaUndi($form);
        $this->assertCount(9, $sebelum);

        Borang14Snapshot::create([
            'borang14_form_id' => $form->id,
            'structure' => $form->structure,
            'votes' => $form->votes()->get(['contest', 'pusat', 'saluran', 'slot', 'undi'])->toArray(),
            'parties' => $form->parties,
            'reason' => 'before_structure_edit',
            'created_by' => $this->admin()->id,
        ]);

        // Ubah KEDUA-DUA pertandingan selepas snapshot: satu diubah nilainya,
        // satu dipadam, satu ditambah baharu — pada kedua-dua jalur.
        $form->votesFor(Borang14Vote::CONTEST_DUN)->where('saluran', '1')->where('slot', 1)->update(['undi' => 999]);
        $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->where('saluran', '1')->where('slot', 1)->update(['undi' => 888]);
        $form->votesFor(Borang14Vote::CONTEST_DUN)->where('saluran', '2')->delete();
        $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->where('saluran', '2')->delete();
        foreach ([Borang14Vote::CONTEST_DUN, Borang14Vote::CONTEST_PARLIMEN] as $contest) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id, 'contest' => $contest,
                'pusat' => 'SK GEMAS', 'saluran' => '9', 'slot' => 1, 'undi' => 77,
            ]);
        }
        $form->update(['structure' => ['rows' => [['dm' => 'LAIN', 'pusat' => 'DEWAN LAIN', 'saluran' => '1']]]]);

        $this->assertNotSame($sebelum, $this->petaUndi($form));

        $this->actingAs($this->admin())
            ->postJson(route('pilihanraya.borang-14.revert'), ['form_id' => $form->id])
            ->assertSuccessful();

        $selepas = $this->petaUndi($form->fresh());

        $this->assertSame($sebelum, $selepas,
            'Revert mesti memulihkan KEDUA-DUA pertandingan tepat seperti asal — tiada baris hilang, '.
            'tiada baris tambahan, dan tiada undi yang berpindah pertandingan.');

        // Silang-semak eksplisit: jumlah setiap pertandingan diasingkan.
        $this->assertSame(650, (int) $form->votesFor(Borang14Vote::CONTEST_DUN)->sum('undi'));
        $this->assertSame(378, (int) $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->sum('undi'));
        $this->assertSame(4, $form->votesFor(Borang14Vote::CONTEST_DUN)->count());
        $this->assertSame(5, $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->count());

        // Struktur dan pemetaan parti turut pulih.
        $this->assertSame('SK GEMAS', $form->fresh()->structure['rows'][0]['pusat']);
    }

    /**
     * Pusingan PENUH melalui penulis snapshot SEBENAR: simpanStruktur()
     * merakam snapshot, membuang Pusat Mengundi (yang memadam undi KEDUA-DUA
     * pertandingan pada saluran itu), dan revert() memulihkannya.
     *
     * Ujian di atas membina snapshot dengan tangan, jadi ia hanya memaku
     * bahagian PULIH. Ini memaku bahagian RAKAM juga — jika penulis snapshot
     * berhenti merakam `contest`, undi PRU akan pulih sebagai undi PRN.
     */
    public function test_a_structure_edit_then_revert_round_trip_keeps_both_contests_apart(): void
    {
        $takrifan = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'structure' => null, 'parties' => [],
        ]);

        $pusat = [['row_id' => 'r1', 'dm' => 'PEKAN GEMAS', 'pusat' => 'SK GEMAS', 'saluran_count' => 1]];

        // Cipta borang + struktur melalui endpoint sebenar, dan paut serentak.
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => $pusat,
            'parlimen_id' => $this->parlimen->id,
        ])->assertSuccessful();

        $form = Borang14Form::where('kawasan_type', 'dun')->where('kawasan_id', $this->dun->id)->firstOrFail();
        $this->assertSame($takrifan->id, (int) $form->borang14_form_parlimen_id);

        foreach ([[Borang14Vote::CONTEST_DUN, 1, 224], [Borang14Vote::CONTEST_PARLIMEN, 1, 93], [Borang14Vote::CONTEST_PARLIMEN, 2, 88]] as [$contest, $slot, $undi]) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id, 'contest' => $contest,
                'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
            ]);
        }
        $sebelum = $this->petaUndi($form);

        // Buang Pusat Mengundi itu — undi KEDUA-DUA pertandingan di bawahnya
        // dipadam (struktur ialah pokok FIZIKAL, dikongsi dua kertas undi).
        $this->actingAs($this->admin())->postJson(route('pilihanraya.borang-14.struktur'), [
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027,
            'pusat' => [],
        ])->assertSuccessful();

        $this->assertSame(0, $form->votes()->count(), 'Membuang pusat mesti membuang undi kedua-dua jalur.');

        $this->actingAs($this->admin())
            ->postJson(route('pilihanraya.borang-14.revert'), ['form_id' => $form->id])
            ->assertSuccessful();

        $this->assertSame($sebelum, $this->petaUndi($form->fresh()));
        $this->assertSame(224, (int) $form->votesFor(Borang14Vote::CONTEST_DUN)->sum('undi'));
        $this->assertSame(181, (int) $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->sum('undi'));
    }

    /**
     * Snapshot LAMA (dirakam sebelum lajur `contest` wujud) tiada kunci itu
     * langsung. Baris begitu mesti pulih kepada pertandingan borang itu
     * sendiri — satu-satunya yang wujud ketika snapshot dirakam — dan BUKAN
     * ditolak atau dijatuhkan.
     */
    public function test_a_pre_contest_snapshot_restores_into_the_forms_own_contest(): void
    {
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
        ]);

        Borang14Snapshot::create([
            'borang14_form_id' => $form->id,
            'structure' => null,
            'votes' => [
                ['pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 500],
                ['pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 2, 'undi' => 400],
            ],
            'parties' => [],
            'reason' => 'before_structure_edit',
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('pilihanraya.borang-14.revert'), ['form_id' => $form->id])
            ->assertSuccessful();

        $this->assertSame(2, $form->votesFor(Borang14Vote::CONTEST_DUN)->count());
        $this->assertSame(0, $form->votesFor(Borang14Vote::CONTEST_PARLIMEN)->count());
        $this->assertSame(900, (int) $form->votesFor(Borang14Vote::CONTEST_DUN)->sum('undi'));
    }
}
