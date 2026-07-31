<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
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

    /**
     * RefreshDatabase already ran this migration (and everything before it)
     * against an empty database before each test above, so those tests only
     * ever see the FINISHED schema. To exercise up()'s own backfill/collapse/
     * unlink logic — the code that actually touches production rows and
     * files — we need it to run again against a realistic BEFORE state.
     *
     * Revert scoreboards to the shape it had going into this migration
     * (borang14_form_id NOT NULL + UNIQUE + FK cascadeOnDelete, none of the
     * new kawasan_type/kod/pihak_kami/updated_by columns) by undoing exactly
     * what addSeatColumnsIfMissing()/addUpdatedByForeignKeyIfMissing()/
     * addNewBorangFormIdConstraintsIfMissing() add — the same technique
     * Borang14MigrationGuardTest::undoNewerScoreboardsMigration() uses to
     * solve the identical problem one migration up.
     */
    private function resetScoreboardsToPreMigrationShape(): void
    {
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropForeign(['borang14_form_id']);
            $table->dropForeign(['updated_by']);
        });

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropUnique('scoreboards_kerusi_unique');
            $table->dropUnique('scoreboards_kod_unique');
        });

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->unsignedBigInteger('borang14_form_id')->nullable(false)->change();
        });

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropColumn(['kawasan_type', 'kawasan_id', 'status', 'kod', 'pihak_kami', 'updated_by']);
        });

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->cascadeOnDelete();
            $table->unique('borang14_form_id');
        });
    }

    /** Minimal valid borang14_forms row (current/live column shape). */
    private function seedBorangForm(array $overrides = []): int
    {
        return DB::table('borang14_forms')->insertGetId(array_merge([
            'kawasan_type' => 'dun',
            'kawasan_id' => 41,
            'jenis_pr' => 'prn',
            'tahun' => 2026,
            'penjuru' => 2,
            'parties' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function migrationInstance(): object
    {
        return require database_path('migrations/2026_07_31_100001_reshape_scoreboards_per_kerusi.php');
    }

    public function test_collapse_keeps_the_newer_board_and_drops_the_older(): void
    {
        $this->resetScoreboardsToPreMigrationShape();

        // Two borang14_forms for the SAME seat (dun 41) but different
        // elections — mirrors production, where each old scoreboard was
        // 1:1 with a single borang14_form_id, so a seat could accumulate
        // more than one board over time.
        $formLama = $this->seedBorangForm(['tahun' => 2022]);
        $formBaharu = $this->seedBorangForm(['tahun' => 2026]);

        $loserId = DB::table('scoreboards')->insertGetId([
            'borang14_form_id' => $formLama,
            'title' => 'PAPAN LAMA',
            'candidates' => json_encode([['nama' => 'CALON LAMA']]),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        $winnerId = DB::table('scoreboards')->insertGetId([
            'borang14_form_id' => $formBaharu,
            'title' => 'PAPAN BAHARU',
            'candidates' => json_encode([['nama' => 'CALON BAHARU']]),
            'created_at' => now()->subDay(),
            'updated_at' => now(), // paling terkini — mesti terselamat
        ]);

        $this->migrationInstance()->up();

        $this->assertNull(DB::table('scoreboards')->find($loserId), 'Papan lebih lama mesti dipadam.');

        $survivor = DB::table('scoreboards')->find($winnerId);
        $this->assertNotNull($survivor, 'Papan paling terkini mesti terselamat.');
        $this->assertSame('PAPAN BAHARU', $survivor->title, 'Baris terselamat mesti kekalkan title sendiri.');
        $this->assertSame(
            [['nama' => 'CALON BAHARU']],
            json_decode($survivor->candidates, true),
            'Baris terselamat mesti kekalkan candidates sendiri.'
        );
        $this->assertSame(
            1,
            DB::table('scoreboards')->where('kawasan_type', 'dun')->where('kawasan_id', 41)->count(),
            'Hanya satu papan boleh tinggal bagi satu kerusi selepas runtuh.'
        );
    }

    public function test_backfill_seats_inherits_kawasan_from_the_owning_form(): void
    {
        $this->resetScoreboardsToPreMigrationShape();

        $formId = $this->seedBorangForm([
            'kawasan_type' => 'parlimen', 'kawasan_id' => 7, 'jenis_pr' => 'pru', 'tahun' => 2027,
        ]);
        $boardId = DB::table('scoreboards')->insertGetId([
            'borang14_form_id' => $formId,
            'title' => 'PAPAN P7',
            'candidates' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migrationInstance()->up();

        $board = DB::table('scoreboards')->find($boardId);
        $this->assertSame('parlimen', $board->kawasan_type, 'kawasan_type mesti diwarisi daripada borang14_forms.');
        $this->assertSame(7, (int) $board->kawasan_id, 'kawasan_id mesti diwarisi daripada borang14_forms.');
    }

    /**
     * up() mencipta scoreboards_kerusi_unique dan scoreboards_kod_unique dalam
     * DUA ALTER berasingan. Jika sambungan putus di antaranya, skema kekal
     * dengan kerusi_unique tetapi TANPA kod_unique — dan `migrate --force`
     * deploy seterusnya akan merekod migrasi sebagai berjaya.
     *
     * Ujian ini mereka keadaan itu dengan tepat (gugurkan kod_unique sahaja,
     * biarkan segalanya yang lain lengkap) lalu menjalankan up() sekali lagi:
     * pengawal pulang-awal MESTI melihat kerja itu belum selesai dan
     * menyambungnya. Dengan pengawal lama (hanya menyemak kerusi_unique) up()
     * pulang serta-merta dan pengesahan di bawah gagal.
     */
    public function test_a_run_interrupted_before_the_kod_index_still_completes_on_rerun(): void
    {
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropUnique('scoreboards_kod_unique');
        });

        // Prasyarat: hanya kod_unique yang hilang; kerusi_unique masih ada.
        $this->assertTrue(Schema::hasIndex('scoreboards', 'scoreboards_kerusi_unique', 'unique'));
        $this->assertFalse(Schema::hasIndex('scoreboards', 'scoreboards_kod_unique', 'unique'));

        // Tanpa index itu, dua kerusi boleh memegang kod awam yang sama —
        // tepat kelemahan yang dilaporkan.
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => 8001, 'title' => 'A', 'kod' => 'N27',
            'status' => 'tersiar', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'parlimen', 'kawasan_id' => 8002, 'title' => 'B', 'kod' => 'N27',
            'status' => 'tersiar', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(2, DB::table('scoreboards')->where('kod', 'N27')->count(),
            'Tanpa scoreboards_kod_unique, dua kerusi boleh memegang kod awam yang sama.');

        // Bersihkan pendua supaya index boleh dibina semula — kegagalan
        // membina index atas data berpendua ialah masalah berasingan (dan
        // memang WAJAR menggagalkan deploy dengan lantang).
        DB::table('scoreboards')->where('kawasan_id', 8002)->delete();

        $this->migrationInstance()->up();

        $this->assertTrue(
            Schema::hasIndex('scoreboards', 'scoreboards_kod_unique', 'unique'),
            'Larian ulangan mesti MENYAMBUNG dan mencipta scoreboards_kod_unique, bukan melangkauinya.'
        );

        // Dan kekangan itu benar-benar berkuat kuasa selepas sambungan.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'parlimen', 'kawasan_id' => 8003, 'title' => 'C', 'kod' => 'N27',
            'status' => 'tersiar', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Larian sambungan tidak boleh memijak pilihan pemilik. backfillPihakKami()
     * hanya mengisi baris yang pihak_kami-nya MASIH NULL — papan yang sudah
     * disimpan pemilik (cth. hanya slot 2) mesti kekal seperti adanya walaupun
     * up() berjalan semula.
     */
    public function test_a_rerun_does_not_overwrite_pihak_kami_already_chosen_by_the_owner(): void
    {
        $formId = $this->seedBorangForm([
            'parties' => json_encode([['nama' => 'KEADILAN'], ['nama' => 'BERSATU']]),
        ]);

        $boardId = DB::table('scoreboards')->insertGetId([
            'kawasan_type' => 'dun', 'kawasan_id' => 41,
            'borang14_form_id' => $formId,
            'title' => 'PAPAN PEMILIK',
            'pihak_kami' => json_encode([2]), // pilihan pemilik — BUKAN tekaan PH
            'status' => 'draf',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->dropUnique('scoreboards_kod_unique');
        });

        $this->migrationInstance()->up();

        $this->assertSame(
            [2],
            json_decode(DB::table('scoreboards')->find($boardId)->pihak_kami, true),
            'Larian ulangan tidak boleh menulis semula pihak_kami yang sudah dipilih pemilik.'
        );
    }

    public function test_orphan_image_is_unlinked_but_image_shared_with_survivor_stays(): void
    {
        $this->resetScoreboardsToPreMigrationShape();

        $dir = public_path('uploads/scoreboard/logo');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // "shared": dirujuk oleh KEDUA-DUA papan (candidates.gambar pada
        // kedua-duanya) — mesti kekal. "loserOnly": dirujuk HANYA oleh
        // papan yang akan dipadam (logo_path) — mesti dinyahpaut.
        $shared = 'uploads/scoreboard/logo/test_shared_'.uniqid().'.png';
        $loserOnly = 'uploads/scoreboard/logo/test_loser_only_'.uniqid().'.png';
        file_put_contents(public_path($shared), 'fake-png-bytes');
        file_put_contents(public_path($loserOnly), 'fake-png-bytes');

        try {
            $formLama = $this->seedBorangForm(['tahun' => 2022]);
            $formBaharu = $this->seedBorangForm(['tahun' => 2026]);

            DB::table('scoreboards')->insert([
                'borang14_form_id' => $formLama,
                'title' => 'PAPAN LAMA',
                'logo_path' => $loserOnly,
                'candidates' => json_encode([['nama' => 'X', 'gambar' => $shared]]),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
            DB::table('scoreboards')->insert([
                'borang14_form_id' => $formBaharu,
                'title' => 'PAPAN BAHARU',
                'logo_path' => null,
                'candidates' => json_encode([['nama' => 'Y', 'gambar' => $shared]]),
                'created_at' => now()->subDay(),
                'updated_at' => now(),
            ]);

            $this->migrationInstance()->up();

            $this->assertFalse(
                is_file(public_path($loserOnly)),
                'Fail yang dirujuk HANYA oleh papan yang dipadam mesti dinyahpaut.'
            );
            $this->assertTrue(
                is_file(public_path($shared)),
                'Fail yang masih dirujuk oleh papan yang terselamat TIDAK BOLEH dinyahpaut.'
            );
        } finally {
            @unlink(public_path($shared));
            @unlink(public_path($loserOnly));
        }
    }
}
