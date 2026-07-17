<?php
// tests/Feature/Borang14MigrationGuardTest.php
//
// The 2026-07-16 reshape migration used to drop-and-recreate borang14_forms /
// borang14_votes / scoreboards on the assumption all three were empty. That
// assumption expired: production accumulated 1 form, 114 votes, and 1
// scoreboard before this shipped. The migration was rewritten to ALTER the
// tables in place and backfill the new columns instead of dropping them.
//
// These tests prove: (1) pre-existing rows survive the migration with their
// ids intact (so votes never orphan from their form) and get correctly
// reshaped into the new polymorphic schema; (2) a fresh/empty database still
// migrates cleanly end-to-end; (3) down() is honest — it either fully
// restores the prior schema or refuses with a clear error, never silently
// destroying data it cannot faithfully represent in the old (DUN-only,
// non-polymorphic) schema.
namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
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

    /** Seed negeri->bandar->kadun(id=41) so the OLD schema's kadun_id FK inserts succeed. */
    private function seedKadun41(): void
    {
        $negeriId = DB::table('negeri')->insertGetId(['nama' => 'Johor', 'created_at' => now(), 'updated_at' => now()]);
        $bandarId = DB::table('bandar')->insertGetId(['nama' => 'Segamat', 'negeri_id' => $negeriId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('kadun')->insert(['id' => 41, 'nama' => 'Buloh Kasap', 'bandar_id' => $bandarId, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Rebuilds the three tables in the exact OLD shape (mirrors
     * 2026_07_10_000001_create_borang14_tables.php +
     * 2026_07_10_000002_create_scoreboards_table.php combined), independent
     * of this migration's own down() so this test does not depend on down()
     * being correct.
     */
    private function resetToOldSchema(): void
    {
        Schema::dropIfExists('scoreboards');
        Schema::dropIfExists('borang14_votes');
        Schema::dropIfExists('borang14_forms');

        Schema::create('borang14_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kadun_id')->constrained('kadun')->cascadeOnDelete();
            $table->unsignedTinyInteger('penjuru');
            $table->json('parties')->nullable();
            $table->timestamps();

            $table->unique(['kadun_id', 'penjuru']);
        });

        Schema::create('borang14_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->string('pusat')->default('');
            $table->string('saluran');
            $table->unsignedTinyInteger('slot');
            $table->unsignedInteger('undi')->default(0);
            $table->timestamps();

            $table->unique(['borang14_form_id', 'pusat', 'saluran', 'slot'], 'borang14_votes_cell_unique');
        });

        Schema::create('scoreboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kadun_id')->constrained('kadun')->cascadeOnDelete();
            $table->unsignedTinyInteger('penjuru');
            $table->string('title')->default('SCOREBOARD');
            $table->unsignedInteger('minima')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('candidates')->nullable();
            $table->timestamps();

            $table->unique(['kadun_id', 'penjuru']);
        });
    }

    private function seedOldShapeData(): void
    {
        $this->seedKadun41();
        $this->resetToOldSchema();

        DB::table('borang14_forms')->insert([
            'id' => 1, 'kadun_id' => 41, 'penjuru' => 2,
            'parties' => json_encode([
                ['slot' => 1, 'keahlian_parti_id' => 16, 'nama' => 'PAKATAN HARAPAN'],
                ['slot' => 2, 'keahlian_parti_id' => 20, 'nama' => 'PERIKATAN NASIONAL'],
            ]),
            'created_at' => now()->subDays(6), 'updated_at' => now()->subDays(5),
        ]);

        $votes = [];
        foreach (range(1, 114) as $i) {
            $votes[] = [
                'borang14_form_id' => 1,
                'pusat' => 'SK '.$i,
                'saluran' => (string) (($i % 5) + 1),
                'slot' => ($i % 2) + 1,
                'undi' => $i * 3,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table('borang14_votes')->insert($votes);

        DB::table('scoreboards')->insert([
            'kadun_id' => 41, 'penjuru' => 2, 'title' => 'SCOREBOARD',
            'candidates' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_migration_preserves_pre_existing_rows_and_reshapes_them(): void
    {
        $this->seedOldShapeData();

        $this->migration()->up();

        $form = DB::table('borang14_forms')->where('id', 1)->first();
        $this->assertNotNull($form, 'The form row must survive the migration with the same id.');
        $this->assertSame('dun', $form->kawasan_type);
        $this->assertSame(41, (int) $form->kawasan_id);
        $this->assertSame('prn', $form->jenis_pr);
        $this->assertSame(2026, (int) $form->tahun);
        $this->assertSame(2, (int) $form->penjuru);
        $this->assertSame('published', $form->status);
        $this->assertSame('manual', $form->source);
        $this->assertFalse((bool) $form->needs_review);
        $this->assertNotNull($form->published_at);
        $this->assertNotEmpty($form->parties);
        $this->assertFalse(Schema::hasColumn('borang14_forms', 'kadun_id'));

        $this->assertSame(
            114,
            DB::table('borang14_votes')->where('borang14_form_id', 1)->count(),
            'All 114 vote rows must remain attached to form id 1.'
        );

        $board = DB::table('scoreboards')->first();
        $this->assertNotNull($board);
        $this->assertSame(1, (int) $board->borang14_form_id, 'The scoreboard must now point at the form via borang14_form_id.');
        $this->assertFalse(Schema::hasColumn('scoreboards', 'kadun_id'));
        $this->assertFalse(Schema::hasColumn('scoreboards', 'penjuru'));
    }

    public function test_fresh_empty_database_migrates_cleanly(): void
    {
        // RefreshDatabase already ran every migration (including this one) against
        // an empty database to set up this test. Getting here without an exception
        // already proves the fresh path works; assert the resulting shape too.
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'kawasan_type'));
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'kawasan_id'));
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'jenis_pr'));
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'tahun'));
        $this->assertFalse(Schema::hasColumn('borang14_forms', 'kadun_id'));
        $this->assertTrue(Schema::hasColumn('scoreboards', 'borang14_form_id'));
        $this->assertFalse(Schema::hasColumn('scoreboards', 'kadun_id'));
        $this->assertSame(0, DB::table('borang14_forms')->count());
    }

    public function test_down_restores_prior_schema_when_tables_are_empty(): void
    {
        // Fresh migrated state (via RefreshDatabase) has zero rows — safe to roll back.
        $this->migration()->down();

        $this->assertTrue(Schema::hasTable('scoreboards'), 'down() must not leave the app with no scoreboards table.');
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'kadun_id'), 'down() must restore the prior kadun_id-based schema.');
        $this->assertFalse(Schema::hasColumn('borang14_forms', 'kawasan_type'), 'down() must actually revert, not leave the new schema in place.');
        $this->assertTrue(Schema::hasColumn('scoreboards', 'kadun_id'));

        // Restore the new schema again so nothing else in the suite is affected.
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'kawasan_type'));
    }

    public function test_down_round_trips_real_backfilled_data(): void
    {
        $this->seedOldShapeData();
        $this->migration()->up();

        $this->migration()->down();

        $form = DB::table('borang14_forms')->where('id', 1)->first();
        $this->assertSame(41, (int) $form->kadun_id);
        $this->assertSame(2, (int) $form->penjuru);
        $this->assertSame(114, DB::table('borang14_votes')->where('borang14_form_id', 1)->count());

        $board = DB::table('scoreboards')->first();
        $this->assertSame(41, (int) $board->kadun_id);
        $this->assertSame(2, (int) $board->penjuru);
    }

    public function test_down_refuses_to_destroy_parlimen_rows_it_cannot_represent(): void
    {
        DB::table('borang14_forms')->insert([
            'kawasan_type' => 'parlimen', 'kawasan_id' => 5, 'jenis_pr' => 'prn',
            'tahun' => 2026, 'penjuru' => 2, 'status' => 'draft', 'source' => 'manual',
            'needs_review' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->migration()->down();

        $this->assertSame(1, DB::table('borang14_forms')->count(), 'A refused rollback must leave the existing data untouched.');
    }
}
