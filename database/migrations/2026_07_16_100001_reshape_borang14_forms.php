<?php
// database/migrations/2026_07_16_100001_reshape_borang14_forms.php
//
// Reka bentuk ASAL migrasi ini menggugurkan & mencipta semula ketiga-tiga
// jadual (borang14_forms, borang14_votes, scoreboards) atas andaian ia
// kosong. Andaian itu telah luput — produksi kini mempunyai 1 borang
// (Buloh Kasap, PRN Johor 2026), 114 baris undi, dan 1 scoreboard. Repo ini
// AUTO-DEPLOY ke cPanel setiap kali push (php artisan migrate --force), jadi
// menggugurkan jadual akan MEMUSNAHKAN data sebenar secara senyap.
//
// Migrasi ini ditulis semula supaya ia mengUBAH (ALTER) jadual sedia ada di
// tempat (in place) dan mem-backfill lajur baharu daripada lajur lama —
// tiada baris digugur/dicipta semula, jadi ID borang (dan justeru
// borang14_form_id pada setiap baris undi/scoreboard) kekal sama.
//
// Backfill untuk baris SEDIA ADA (skema lama hanya mengenali DUN PRN):
//   kawasan_type = 'dun', kawasan_id = kadun_id lama, jenis_pr = 'prn',
//   tahun = 2026, status = 'published' (data sebenar, sudah dibaca oleh
//   scoreboard), source = 'manual' (dikeyin tangan, bukan scoresheet),
//   needs_review = false, published_at = updated_at lama, penjuru/parties
//   tidak berubah.
//
// borang14_votes TIDAK disentuh langsung — ia hanya bergantung kepada
// borang14_form_id, dan ID borang itu tidak pernah berubah kerana baris
// borang tidak pernah digugur/dicipta semula.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->reshapeBorang14Forms();
        $this->reshapeScoreboards();
    }

    /**
     * borang14_forms: tambah lajur baharu (nullable dahulu), backfill
     * daripada kadun_id/updated_at sedia ada, KEMUDIAN jadikan lajur baharu
     * NOT NULL, dan HANYA SELEPAS ITU gugurkan kadun_id (perlu gugur unique
     * index & FK constraint dahulu sebelum gugur lajurnya di MySQL).
     */
    private function reshapeBorang14Forms(): void
    {
        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->string('kawasan_type', 10)->nullable();
            $table->unsignedBigInteger('kawasan_id')->nullable();
            $table->string('jenis_pr', 4)->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->json('structure')->nullable();
            $table->string('status', 10)->default('draft');
            $table->string('source', 12)->default('manual');
            $table->string('source_filename')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->timestamp('published_at')->nullable();
        });

        // Backfill — no-op bila jadual kosong (pemasangan baharu/ujian).
        DB::table('borang14_forms')->update([
            'kawasan_type' => 'dun',
            'kawasan_id' => DB::raw('kadun_id'),
            'jenis_pr' => 'prn',
            'tahun' => 2026,
            'status' => 'published',
            'source' => 'manual',
            'needs_review' => false,
            'published_at' => DB::raw('updated_at'),
        ]);

        $sqlite = DB::connection()->getDriverName() === 'sqlite';

        // SQLite has no native "MODIFY COLUMN" / "DROP FOREIGN KEY": Laravel
        // implements ->change() and dropForeign() there by rebuilding the whole
        // table (create temp -> copy rows -> DROP the original -> rename temp).
        // It wraps that in "PRAGMA foreign_keys = OFF/ON" to protect child rows,
        // but that pragma is a documented no-op while a transaction is active —
        // and both `artisan migrate` and the test RefreshDatabase trait run
        // migrations inside one. Left unguarded, the instant the original
        // borang14_forms table is DROPped mid-rebuild, SQLite would CASCADE
        // DELETE every borang14_votes row still pointing at it (even though the
        // same ids reappear moments later). MySQL never touches this path at all
        // (MODIFY COLUMN / DROP FOREIGN KEY are truly in place, the table is
        // never dropped), so only sqlite needs the temporary FK removal below.
        if ($sqlite) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->dropForeign(['borang14_form_id']);
            });
        }

        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->string('kawasan_type', 10)->nullable(false)->change();
            $table->unsignedBigInteger('kawasan_id')->nullable(false)->change();
            $table->string('jenis_pr', 4)->nullable(false)->change();
            $table->unsignedSmallInteger('tahun')->nullable(false)->change();
            $table->unsignedTinyInteger('penjuru')->default(2)->change();
        });

        Schema::table('borang14_forms', function (Blueprint $table) {
            // MySQL refuses to drop an index that a foreign key still relies on
            // (error 1553) — the FK must go first, then the unique index, then
            // the column itself.
            $table->dropForeign(['kadun_id']);
            $table->dropUnique(['kadun_id', 'penjuru']);
            $table->dropColumn('kadun_id');
        });

        if ($sqlite) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->cascadeOnDelete();
            });
        }

        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->unique(['kawasan_type', 'kawasan_id', 'jenis_pr', 'tahun'], 'borang14_forms_election_unique');
            $table->index(['kawasan_type', 'kawasan_id']);
            $table->index(['status', 'tahun']);
        });
    }

    /**
     * scoreboards: tambah borang14_form_id (nullable dahulu), padankan
     * dengan borang14_forms melalui (kawasan_id, penjuru) — kawasan_type
     * mesti 'dun' kerana skema lama scoreboards juga hanya kenal kadun_id —
     * KEMUDIAN gugurkan kadun_id/penjuru lama.
     */
    private function reshapeScoreboards(): void
    {
        Schema::table('scoreboards', function (Blueprint $table) {
            $table->unsignedBigInteger('borang14_form_id')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE scoreboards
            SET borang14_form_id = (
                SELECT f.id FROM borang14_forms f
                WHERE f.kawasan_type = 'dun'
                  AND f.kawasan_id = scoreboards.kadun_id
                  AND f.penjuru = scoreboards.penjuru
                ORDER BY f.id ASC
                LIMIT 1
            )
        SQL);

        $unmatched = DB::table('scoreboards')->whereNull('borang14_form_id')->count();
        if ($unmatched > 0) {
            throw new \RuntimeException(
                "Backfill scoreboards.borang14_form_id gagal: {$unmatched} baris scoreboards tidak dapat ".
                'dipadankan dengan mana-mana borang14_forms (kombinasi kadun_id+penjuru tiada padanan borang). '.
                'SANDARKAN pangkalan data dan padankan rekod ini secara manual sebelum meneruskan migrasi ini.'
            );
        }

        Schema::table('scoreboards', function (Blueprint $table) {
            // Same MySQL ordering constraint as borang14_forms above: FK before
            // the unique index it depends on, before the column itself.
            $table->dropForeign(['kadun_id']);
            $table->dropUnique(['kadun_id', 'penjuru']);
            $table->dropColumn(['kadun_id', 'penjuru']);
        });

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->unsignedBigInteger('borang14_form_id')->nullable(false)->change();
        });

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->cascadeOnDelete();
            $table->unique('borang14_form_id');
        });
    }

    /**
     * down() mesti JUJUR: jika data BAHARU tidak boleh diwakili sepenuhnya
     * oleh skema LAMA (kadun_id sahaja, tiada konsep Parlimen, satu borang
     * setiap (kadun_id, penjuru)), kita ENGGAN dan lemparkan mesej jelas —
     * bukan pulihkan skema lama secara separuh/senyap kehilangan data.
     */
    public function down(): void
    {
        $this->guardDownIsSafe();

        $this->revertScoreboards();
        $this->revertBorang14Forms();
    }

    private function guardDownIsSafe(): void
    {
        if (! Schema::hasTable('borang14_forms') || ! Schema::hasColumn('borang14_forms', 'kawasan_type')) {
            return; // Skema baharu belum wujud — tiada apa untuk diterbalikkan.
        }

        $bukanDun = DB::table('borang14_forms')->where('kawasan_type', '!=', 'dun')->count();
        if ($bukanDun > 0) {
            throw new \RuntimeException(
                "Migrasi ini TIDAK BOLEH diterbalikkan (not reversible): terdapat {$bukanDun} baris borang14_forms ".
                "berjenis kawasan bukan 'dun' (cth. 'parlimen'). Skema lama (kadun_id sahaja) TIADA konsep ".
                'Parlimen langsung — menukar balik akan memusnahkan maklumat kawasan bagi rekod tersebut. '.
                'SANDARKAN data dahulu dan tangani migrasi data secara manual jika rollback benar-benar diperlukan.'
            );
        }

        $pendua = DB::table('borang14_forms')
            ->select('kawasan_id', 'penjuru')
            ->where('kawasan_type', 'dun')
            ->groupBy('kawasan_id', 'penjuru')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        if ($pendua->isNotEmpty()) {
            throw new \RuntimeException(
                'Migrasi ini TIDAK BOLEH diterbalikkan (not reversible): terdapat lebih daripada satu borang bagi '.
                'kombinasi (kawasan_id, penjuru) yang sama (cth. pilihan raya berbeza tahun/jenis pada DUN yang '.
                'sama). Skema lama hanya membenarkan SATU borang bagi setiap (kadun_id, penjuru) — menukar balik '.
                'akan melanggar kekangan unik itu. SANDARKAN data dahulu dan tangani migrasi data secara manual '.
                'jika rollback benar-benar diperlukan.'
            );
        }
    }

    private function revertScoreboards(): void
    {
        if (! Schema::hasTable('scoreboards') || ! Schema::hasColumn('scoreboards', 'borang14_form_id')) {
            return;
        }

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->unsignedBigInteger('kadun_id')->nullable();
            $table->unsignedTinyInteger('penjuru')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE scoreboards
            SET kadun_id = (SELECT f.kawasan_id FROM borang14_forms f WHERE f.id = scoreboards.borang14_form_id),
                penjuru  = (SELECT f.penjuru     FROM borang14_forms f WHERE f.id = scoreboards.borang14_form_id)
        SQL);

        Schema::table('scoreboards', function (Blueprint $table) {
            // FK before the unique index it depends on — see the comment in
            // reshapeBorang14Forms() (MySQL error 1553 otherwise).
            $table->dropForeign(['borang14_form_id']);
            $table->dropUnique(['borang14_form_id']);
            $table->dropColumn('borang14_form_id');
        });

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->unsignedBigInteger('kadun_id')->nullable(false)->change();
            $table->unsignedTinyInteger('penjuru')->nullable(false)->change();
        });

        Schema::table('scoreboards', function (Blueprint $table) {
            $table->foreign('kadun_id')->references('id')->on('kadun')->cascadeOnDelete();
            $table->unique(['kadun_id', 'penjuru']);
        });
    }

    private function revertBorang14Forms(): void
    {
        if (! Schema::hasColumn('borang14_forms', 'kawasan_type')) {
            return;
        }

        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->dropUnique('borang14_forms_election_unique');
            $table->dropIndex(['kawasan_type', 'kawasan_id']);
            $table->dropIndex(['status', 'tahun']);
        });

        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('kadun_id')->nullable();
        });

        DB::table('borang14_forms')->update(['kadun_id' => DB::raw('kawasan_id')]);

        // See the matching comment in reshapeBorang14Forms(): ->change() and
        // foreign() force sqlite to rebuild the whole table, which would
        // cascade-delete borang14_votes if its FK to borang14_forms is still
        // active. Guard the same way, sqlite only.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($sqlite) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->dropForeign(['borang14_form_id']);
            });
        }

        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('kadun_id')->nullable(false)->change();
        });

        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->foreign('kadun_id')->references('id')->on('kadun')->cascadeOnDelete();
            $table->unique(['kadun_id', 'penjuru']);
        });

        if ($sqlite) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->cascadeOnDelete();
            });
        }

        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->dropColumn([
                'kawasan_type', 'kawasan_id', 'jenis_pr', 'tahun', 'structure',
                'status', 'source', 'source_filename', 'needs_review', 'published_at',
            ]);
        });
    }
};
