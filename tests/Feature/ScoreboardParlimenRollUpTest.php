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

class ScoreboardParlimenRollUpTest extends TestCase
{
    use RefreshDatabase;

    private Bandar $parlimen;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimen = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri->id]);
        $dun = Kadun::create(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $this->parlimen->id]);

        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '990101010101', 'nama' => 'PENGUNDI', 'kadun' => 'GEMAS',
            'parlimen' => 'JEMPOL', 'daerah_mengundi' => 'PEKAN GEMAS', 'lokaliti' => 'SK GEMAS',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $definisi = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'jenis_pr' => 'pru', 'tahun' => 2027, 'penjuru' => 3, 'structure' => null,
            'parties' => [['slot' => 1, 'nama' => 'BN'], ['slot' => 2, 'nama' => 'PN'], ['slot' => 3, 'nama' => 'PH']],
        ]);
        $dunForm = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $dun->id,
            'jenis_pr' => 'prn', 'tahun' => 2027, 'penjuru' => 2, 'parties' => [],
            'borang14_form_parlimen_id' => $definisi->id,
        ]);
        foreach ([1 => 2282, 2 => 1195, 3 => 412] as $slot => $undi) {
            Borang14Vote::create([
                'borang14_form_id' => $dunForm->id, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
                'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => $slot, 'undi' => $undi,
            ]);
        }

        Scoreboard::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $this->parlimen->id,
            'borang14_form_id' => $definisi->id,
            'title' => 'P133', 'status' => Scoreboard::STATUS_DRAF,
        ]);
    }

    public function test_a_parlimen_board_aggregates_its_dun_forms(): void
    {
        $payload = ScoreboardPayload::forSeat('parlimen', $this->parlimen->id);

        $this->assertTrue($payload['ready']);
        $this->assertSame([2282, 1195, 412], array_column($payload['rows'], 'undi'));
        $this->assertSame(3889, $payload['total_keluar']);
        $this->assertSame(['melapor' => 1, 'jumlah' => 1], $payload['liputan']);
    }

    public function test_a_parlimen_seat_with_no_form_is_not_ready_without_zero_votes(): void
    {
        $negeri = Negeri::create(['nama' => 'PERAK']);
        $bandarTanpaBorang = Bandar::create(['nama' => 'TAIPING', 'kod_parlimen' => 'P060', 'negeri_id' => $negeri->id]);

        // Rujukan sedia ada (roll DPT), tetapi TIADA Borang 14 Parlimen
        // langsung — ini mesti kekal "belum sedia", bukan kiraan sifar.
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '880202020202', 'nama' => 'PENGUNDI TAIPING', 'kadun' => 'TAIPING',
            'parlimen' => 'TAIPING', 'daerah_mengundi' => 'PEKAN TAIPING', 'lokaliti' => 'SK TAIPING',
            'is_deceased' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $payload = ScoreboardPayload::forSeat('parlimen', $bandarTanpaBorang->id);

        $this->assertFalse($payload['ready']);
        $this->assertArrayNotHasKey('rows', $payload);
        $this->assertArrayNotHasKey('total_keluar', $payload);
        $this->assertArrayNotHasKey('undi_kami', $payload);
        $this->assertNull($payload['liputan']);
    }
}
