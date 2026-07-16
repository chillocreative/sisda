<?php
// tests/Feature/Borang14MigrationGuardTest.php
//
// Final pre-merge review finding 1 (BLOCKER): the reshape migration
// unconditionally dropped scoreboards/borang14_votes/borang14_forms on the
// assumption they held 0 rows AT DESIGN TIME. This repo auto-deploys to
// cPanel on push, so if anyone keyed a form since, a redeploy would destroy
// it silently. These tests prove the migration refuses to drop non-empty
// tables, and that down() is honest: it either fully restores the prior
// schema (when safe/empty) or throws rather than leaving the app with a
// half-destroyed schema (no scoreboards table at all).
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class Borang14MigrationGuardTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_07_16_100001_reshape_borang14_forms.php');
    }

    private function baseFormRow(): array
    {
        return [
            'kawasan_type' => 'dun', 'kawasan_id' => 1, 'jenis_pr' => 'prn',
            'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft', 'source' => 'manual',
            'needs_review' => false, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    public function test_up_refuses_to_drop_tables_that_already_hold_rows(): void
    {
        DB::table('borang14_forms')->insert($this->baseFormRow());

        $this->expectException(RuntimeException::class);
        $this->migration()->up();

        $this->assertGreaterThan(0, DB::table('borang14_forms')->count(), 'A blocked migration must leave the existing data untouched.');
    }

    public function test_down_refuses_to_destroy_rows_in_the_new_schema(): void
    {
        DB::table('borang14_forms')->insert($this->baseFormRow());

        $this->expectException(RuntimeException::class);
        $this->migration()->down();
    }

    public function test_down_restores_prior_schema_when_tables_are_empty(): void
    {
        // Fresh migrated state (via RefreshDatabase) has zero rows — safe to roll back.
        $this->migration()->down();

        // down() must be HONEST: either fully restore the prior schema, or throw.
        // It must NEVER silently leave the app with no scoreboards table at all.
        $this->assertTrue(Schema::hasTable('scoreboards'), 'down() must not leave the app with no scoreboards table.');
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'kadun_id'), 'down() must restore the prior kadun_id-based schema.');
        $this->assertFalse(Schema::hasColumn('borang14_forms', 'kawasan_type'), 'down() must actually revert, not leave the new schema in place.');
        $this->assertTrue(Schema::hasColumn('scoreboards', 'kadun_id'));

        // Restore the new schema again so nothing else in the suite is affected.
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'kawasan_type'));
    }
}
