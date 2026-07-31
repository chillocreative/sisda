<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Apabila PRU dan PRN diadakan serentak, SATU saluran menghasilkan DUA set
 * undi. Slot 1 pada saluran yang sama ialah calon BN dalam KEDUA-DUA
 * pertandingan, jadi kunci sel lama (form, pusat, saluran, slot) membuatkan
 * kedua-duanya berlanggar. Ujian ini memaku pembetulan kunci itu.
 */
class Borang14SerentakMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function form(string $kawasanType, int $kawasanId, string $jenisPr): int
    {
        return DB::table('borang14_forms')->insertGetId([
            'kawasan_type' => $kawasanType,
            'kawasan_id' => $kawasanId,
            'jenis_pr' => $jenisPr,
            'tahun' => 2027,
            'penjuru' => 3,
            'parties' => json_encode([]),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_votes_table_has_the_contest_column(): void
    {
        $this->assertTrue(Schema::hasColumn('borang14_votes', 'contest'));
    }

    public function test_forms_table_can_link_to_a_parlimen_form(): void
    {
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'borang14_form_parlimen_id'));
    }

    public function test_both_contests_can_hold_the_same_saluran_and_slot(): void
    {
        $form = $this->form('dun', 34, 'prn');

        // Slot 1 = BN dalam kedua-dua pertandingan pada saluran yang SAMA.
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 224,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'parlimen',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 93,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, DB::table('borang14_votes')->where('borang14_form_id', $form)->count());
        $this->assertSame(224, (int) DB::table('borang14_votes')
            ->where(['borang14_form_id' => $form, 'contest' => 'dun', 'saluran' => '3', 'slot' => 1])->value('undi'));
        $this->assertSame(93, (int) DB::table('borang14_votes')
            ->where(['borang14_form_id' => $form, 'contest' => 'parlimen', 'saluran' => '3', 'slot' => 1])->value('undi'));
    }

    public function test_the_same_cell_within_one_contest_is_still_unique(): void
    {
        $form = $this->form('dun', 34, 'prn');

        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 224,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 999,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_down_refuses_rather_than_lose_data(): void
    {
        $migration = require database_path('migrations/2026_08_01_100001_add_contest_to_borang14_votes.php');

        $form = $this->form('dun', 34, 'prn');
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'parlimen',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 93,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
