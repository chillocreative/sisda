<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use App\Support\SeatScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScoreboardPayloadTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $bandar->id]);
    }

    private function form(): Borang14Form
    {
        return Borang14Form::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'jenis_pr' => 'prn',
            'tahun' => 2026,
            'penjuru' => 3,
            'status' => 'published',
            'parties' => [['nama' => 'KEADILAN'], ['nama' => 'BERSATU'], ['nama' => 'PAS']],
        ]);
    }

    /**
     * Borang14Reference::forKadun() memulangkan null bagi DUN ini melainkan
     * ada fail JSON terkurasi ATAU baris pangkalan_data_pengundi. Test ini
     * bukan berkenaan struktur roll DPT sebenar, jadi kita isi baris minimum
     * merentasi DUA daerah mengundi dengan bilangan yang diketahui:
     * AWAT/KAMPUNG A=3, AWAT/KAMPUNG B=2, BALAI/KAMPUNG C=4 — jumlah 9.
     */
    private function seedDpt(): void
    {
        $rows = [];
        $spesifikasi = [
            ['dm' => 'AWAT', 'lokaliti' => 'KAMPUNG A', 'bilangan' => 3],
            ['dm' => 'AWAT', 'lokaliti' => 'KAMPUNG B', 'bilangan' => 2],
            ['dm' => 'BALAI', 'lokaliti' => 'KAMPUNG C', 'bilangan' => 4],
        ];

        $ic = 800101010000;
        foreach ($spesifikasi as $s) {
            for ($i = 0; $i < $s['bilangan']; $i++) {
                $ic++;
                $rows[] = [
                    'no_ic' => (string) $ic,
                    'nama' => 'PENGUNDI '.$ic,
                    'lokaliti' => $s['lokaliti'],
                    'daerah_mengundi' => $s['dm'],
                    'kadun' => 'PILAH',
                    'parlimen' => 'KUALA PILAH',
                    'negeri' => 'NEGERI SEMBILAN',
                    'is_deceased' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('pangkalan_data_pengundi')->insert($rows);
    }

    public function test_board_without_a_chosen_source_reports_not_ready_rather_than_zero(): void
    {
        Scoreboard::create([
            'kawasan_type' => SeatScope::DUN,
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => null,
            'title' => 'SCOREBOARD',
            'status' => Scoreboard::STATUS_DRAF,
        ]);

        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertFalse($payload['ready']);
        $this->assertNull($payload['sumber']);
        // Tiada sumber bukan bermakna sifar undi.
        $this->assertArrayNotHasKey('total_keluar', $payload);
    }

    public function test_rows_follow_the_chosen_form_and_tag_only_the_owners_slots(): void
    {
        $this->seedDpt();
        $form = $this->form();
        Scoreboard::create([
            'kawasan_type' => SeatScope::DUN,
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $form->id,
            'title' => 'PILAH 2026',
            'status' => Scoreboard::STATUS_TERSIAR,
            'kod' => 'N27',
            'pihak_kami' => [1, 3],
        ]);

        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertTrue($payload['ready']);
        $this->assertSame('PILAH 2026', $payload['title']);
        $this->assertSame('N27', $payload['kod']);
        $this->assertSame([true, false, true], array_column($payload['rows'], 'is_kami'));
        $this->assertSame($form->id, $payload['sumber']['id']);
        $this->assertSame('PRN 2026 · 3 Penjuru', $payload['sumber']['label']);
    }

    public function test_undi_kami_sums_only_the_tagged_slots(): void
    {
        $this->seedDpt();
        $form = $this->form();
        Scoreboard::create([
            'kawasan_type' => SeatScope::DUN,
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $form->id,
            'title' => 'SCOREBOARD',
            'status' => Scoreboard::STATUS_DRAF,
            'pihak_kami' => [1],
        ]);

        // Borang14Vote fillable: borang14_form_id, pusat, saluran, slot, undi.
        $form->votes()->createMany([
            ['pusat' => 'PM1', 'saluran' => 1, 'slot' => 1, 'undi' => 120],
            ['pusat' => 'PM1', 'saluran' => 1, 'slot' => 2, 'undi' => 80],
            ['pusat' => 'PM1', 'saluran' => 1, 'slot' => 3, 'undi' => 30],
        ]);

        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertSame(120, $payload['undi_kami']);
        $this->assertSame(230, $payload['total_keluar']);
        $this->assertSame(1, $payload['leader_slot']);
    }

    public function test_a_seat_with_no_board_at_all_is_not_ready(): void
    {
        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertFalse($payload['ready']);
    }

    /**
     * Bentuk anggaran DPT (deriveFromDpt) TIDAK mempunyai 'jumlah_berdaftar'
     * pada peringkat daerah_mengundi — hanya JSON terkurasi ada itu. Ujian
     * ini memaku pengiraan yang menyelam ke
     * daerah_mengundi[].pusat_mengundi[].saluran[].berdaftar sebaliknya;
     * inilah ujian yang akan menangkap regresi "total_berdaftar sentiasa 0"
     * bagi setiap kerusi tanpa fail JSON terkurasi.
     */
    public function test_total_berdaftar_sums_dpt_derived_saluran_when_curated_jumlah_is_absent(): void
    {
        $this->seedDpt();
        $form = $this->form();
        Scoreboard::create([
            'kawasan_type' => SeatScope::DUN,
            'kawasan_id' => $this->dun->id,
            'borang14_form_id' => $form->id,
            'title' => 'SCOREBOARD',
            'status' => Scoreboard::STATUS_DRAF,
            'pihak_kami' => [1],
        ]);

        $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

        $this->assertTrue($payload['ready']);
        // AWAT/KAMPUNG A=3 + AWAT/KAMPUNG B=2 + BALAI/KAMPUNG C=4, ditambah
        // undi_awal.berdaftar=0 dan undi_pos.berdaftar=0 (deriveFromDpt).
        $this->assertSame(9, $payload['total_berdaftar']);
    }

    /**
     * Apabila rujukan wujud (bukan null) tetapi TIADA bentuk (JSON terkurasi
     * mahupun DPT) memberi sebarang angka berdaftar langsung, keputusan
     * mesti null — BUKAN 0 yang direka. Fail JSON terkurasi ditulis terus
     * kerana Borang14Reference tidak boleh distub (peraturan tugasan).
     */
    public function test_total_berdaftar_is_null_not_zero_when_reference_has_no_registered_figures(): void
    {
        $path = resource_path("data/borang14/kadun-{$this->dun->id}.json");
        file_put_contents($path, json_encode([
            'negeri' => 'NEGERI SEMBILAN',
            'parlimen' => 'KUALA PILAH',
            'dun' => 'PILAH',
            'daerah_mengundi' => [],
        ]));

        try {
            $form = $this->form();
            Scoreboard::create([
                'kawasan_type' => SeatScope::DUN,
                'kawasan_id' => $this->dun->id,
                'borang14_form_id' => $form->id,
                'title' => 'SCOREBOARD',
                'status' => Scoreboard::STATUS_DRAF,
                'pihak_kami' => [1],
            ]);

            $payload = ScoreboardPayload::forSeat(SeatScope::DUN, $this->dun->id);

            $this->assertTrue($payload['ready']);
            $this->assertNull($payload['total_berdaftar']);
            $this->assertNotSame(0, $payload['total_berdaftar']);
        } finally {
            @unlink($path);
        }
    }
}
