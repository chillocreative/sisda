<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Produksi memegang papan markah sebenar. Migrasi ini meruntuhkan berbilang
 * papan per kerusi kepada satu, jadi ujian ini memaku: bentuk skema baharu,
 * papan mana yang terselamat, dan bahawa down() enggan berjalan.
 */
class ScoreboardMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_has_the_seat_shape(): void
    {
        foreach (['kawasan_type', 'kawasan_id', 'status', 'kod', 'pihak_kami', 'updated_by'] as $col) {
            $this->assertTrue(Schema::hasColumn('scoreboards', $col), "Lajur {$col} tiada.");
        }
    }

    public function test_borang14_form_id_is_nullable_so_a_board_can_exist_before_its_form(): void
    {
        $id = DB::table('scoreboards')->insertGetId([
            'kawasan_type' => 'dun',
            'kawasan_id' => 4242,
            'borang14_form_id' => null,
            'title' => 'SCOREBOARD',
            'status' => 'draf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotNull(DB::table('scoreboards')->find($id));
    }

    public function test_one_board_per_seat_is_enforced(): void
    {
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 5150, 'title' => 'A',
            'status' => 'draf', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 5150, 'title' => 'B',
            'status' => 'draf', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_public_code_is_unique_across_all_boards(): void
    {
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 6001, 'title' => 'A', 'kod' => 'N27',
            'status' => 'tersiar', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'parlimen', 'kawasan_id' => 6002, 'title' => 'B', 'kod' => 'N27',
            'status' => 'tersiar', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_down_refuses_rather_than_lose_data(): void
    {
        $migration = require database_path('migrations/2026_07_31_100001_reshape_scoreboards_per_kerusi.php');

        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 7001, 'title' => 'A',
            'status' => 'draf', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
