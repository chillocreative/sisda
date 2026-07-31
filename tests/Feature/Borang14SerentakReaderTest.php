<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pembaca undi sedia ada menyoal borang14_votes TANPA penapis pertandingan.
 * Pada borang serentak itu menjumlahkan PRU dan PRN bersama — kira-kira dua
 * kali ganda. Ujian ini memaku setiap pembaca kepada SATU pertandingan.
 */
class Borang14SerentakReaderTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;
    private Borang14Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $bandar->id]);

        // Roll DPT supaya Borang14Reference memulangkan struktur.
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '990101010101', 'nama' => 'PENGUNDI', 'kadun' => 'GEMAS',
            'daerah_mengundi' => 'PEKAN GEMAS', 'lokaliti' => 'SK GEMAS',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'status' => 'published',
            'parties' => [['slot' => 1, 'nama' => 'PN'], ['slot' => 2, 'nama' => 'BN']],
        ]);

        // PRN: 63 + 224 = 287.  PRU: 93 + 27 = 120.  Bercampur salah: 407.
        foreach ([[Borang14Vote::CONTEST_DUN, 1, 63], [Borang14Vote::CONTEST_DUN, 2, 224],
                  [Borang14Vote::CONTEST_PARLIMEN, 1, 93], [Borang14Vote::CONTEST_PARLIMEN, 2, 27]] as [$c, $slot, $undi]) {
            Borang14Vote::create([
                'borang14_form_id' => $this->form->id, 'contest' => $c,
                'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => $slot, 'undi' => $undi,
            ]);
        }
    }

    public function test_the_dun_scoreboard_counts_only_the_dun_contest(): void
    {
        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $this->form->id,
            'title' => 'GEMAS', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $payload = ScoreboardPayload::forSeat('dun', $this->dun->id);

        $this->assertSame(287, $payload['total_keluar'], 'Undi PRU tidak boleh dicampur ke dalam papan DUN.');
        $this->assertNotSame(407, $payload['total_keluar']);
        $this->assertSame([63, 224], array_column($payload['rows'], 'undi'));
    }
}
