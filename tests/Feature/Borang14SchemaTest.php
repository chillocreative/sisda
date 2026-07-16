<?php
// tests/Feature/Borang14SchemaTest.php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class Borang14SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_borang14_forms_unique_key_is_kawasan_jenis_tahun(): void
    {
        $row = [
            'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn',
            'tahun' => 2022, 'penjuru' => 3, 'status' => 'draft',
            'source' => 'manual', 'needs_review' => false,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('borang14_forms')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('borang14_forms')->insert($row);
    }

    public function test_penjuru_is_not_part_of_the_key(): void
    {
        $base = [
            'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn',
            'tahun' => 2022, 'status' => 'draft', 'source' => 'manual',
            'needs_review' => false, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('borang14_forms')->insert($base + ['penjuru' => 3]);

        // penjuru berbeza TIDAK boleh mencipta rekod kedua bagi pilihanraya sama
        $this->expectException(QueryException::class);
        DB::table('borang14_forms')->insert($base + ['penjuru' => 2]);
    }

    public function test_parlimen_and_dun_with_same_id_coexist(): void
    {
        $base = [
            'jenis_pr' => 'pru', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'manual', 'needs_review' => false,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('borang14_forms')->insert($base + ['kawasan_type' => 'parlimen', 'kawasan_id' => 1]);
        DB::table('borang14_forms')->insert($base + ['kawasan_type' => 'dun', 'kawasan_id' => 1]);

        $this->assertSame(2, DB::table('borang14_forms')->count());
    }

    public function test_snapshot_cascades_when_form_deleted(): void
    {
        $formId = DB::table('borang14_forms')->insertGetId([
            'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn',
            'tahun' => 2022, 'penjuru' => 3, 'status' => 'draft', 'source' => 'manual',
            'needs_review' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('borang14_snapshots')->insert([
            'borang14_form_id' => $formId,
            'structure' => json_encode(['a' => 1]),
            'votes' => json_encode([]),
            'reason' => 'before_scoresheet_overwrite',
            'created_at' => now(),
        ]);

        DB::table('borang14_forms')->where('id', $formId)->delete();

        $this->assertSame(0, DB::table('borang14_snapshots')->count());
    }
}
