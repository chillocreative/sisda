<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihanraya serentak: satu saluran menghasilkan DUA set undi (PRU + PRN).
 *
 * Kunci sel lama ialah (borang14_form_id, pusat, saluran, slot). Slot 1 pada
 * saluran yang sama ialah calon BN dalam KEDUA-DUA pertandingan, jadi kedua-dua
 * baris berlanggar. Lajur `contest` mesti MASUK KE DALAM kunci unik itu, bukan
 * sekadar duduk di sebelahnya.
 *
 * Turutan MySQL (ralat 1553): FK pada borang14_form_id bersandar pada index
 * unik itu, jadi gugur FK -> gugur unique -> tambah unique baharu -> pasang
 * semula FK. BACA 2026_07_16_100001_reshape_borang14_forms.php dahulu — ia
 * mendokumenkan perangkap 1553 dan perangkap rebuild SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Berkunci pada artifak TERAKHIR yang dicipta, bukan yang pertama:
        // larian separa mesti MENYAMBUNG, bukan dilangkau dan direkod berjaya.
        if (Schema::hasColumn('borang14_forms', 'borang14_form_parlimen_id')) {
            return;
        }

        if (! Schema::hasColumn('borang14_votes', 'contest')) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->string('contest', 10)->nullable()->after('borang14_form_id');
            });
        }

        // Isian belakang: pertandingan sesuatu baris ialah kawasan borang itu
        // sendiri. Borang DUN sedia ada -> 'dun'; borang Parlimen -> 'parlimen'.
        // Tiada baris sedia ada bermakna apa-apa yang lain.
        DB::table('borang14_votes')->whereNull('contest')->update([
            'contest' => DB::raw('(SELECT f.kawasan_type FROM borang14_forms f WHERE f.id = borang14_votes.borang14_form_id)'),
        ]);
        // Baris yatim (borang sudah tiada) — tiada nilai boleh diterbitkan.
        DB::table('borang14_votes')->whereNull('contest')->delete();

        if ($this->uniqueWujud('borang14_votes_cell_unique')) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->dropForeign(['borang14_form_id']);
            });
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->dropUnique('borang14_votes_cell_unique');
            });
        }

        Schema::table('borang14_votes', function (Blueprint $table) {
            $table->string('contest', 10)->nullable(false)->change();
        });

        Schema::table('borang14_votes', function (Blueprint $table) {
            $table->unique(
                ['borang14_form_id', 'contest', 'pusat', 'saluran', 'slot'],
                'borang14_votes_cell_unique',
            );
            $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->cascadeOnDelete();
        });

        // Dicipta TERAKHIR — ia ialah pengawal larian-ulang di atas.
        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->foreignId('borang14_form_parlimen_id')->nullable()->after('tahun')
                ->constrained('borang14_forms')->nullOnDelete();
        });
    }

    private function uniqueWujud(string $nama): bool
    {
        foreach (Schema::getIndexes('borang14_votes') as $index) {
            if (($index['name'] ?? null) === $nama) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        if (! Schema::hasColumn('borang14_votes', 'contest')) {
            return;
        }

        $serentak = DB::table('borang14_votes')->where('contest', 'parlimen')
            ->whereIn('borang14_form_id', DB::table('borang14_forms')->where('kawasan_type', 'dun')->pluck('id'))
            ->count();

        throw new \RuntimeException(
            "Migrasi ini TIDAK BOLEH diterbalikkan (not reversible): terdapat {$serentak} baris undi ".
            "pertandingan Parlimen yang direkod pada borang DUN. Skema lama (kunci sel tanpa `contest`) ".
            'TIADA tempat untuk baris tersebut — menukar balik akan memusnahkannya, atau melanggar kunci '.
            'unik lama kerana slot yang sama wujud dua kali bagi saluran yang sama. SANDARKAN data dahulu '.
            'dan tangani migrasi data secara manual jika rollback benar-benar diperlukan.'
        );
    }
};
