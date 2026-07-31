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
        DB::table('borang14_votes')->whereNull('contest')->update([
            'contest' => DB::raw('(SELECT f.kawasan_type FROM borang14_forms f WHERE f.id = borang14_votes.borang14_form_id)'),
        ]);

        // Baris yatim (borang sudah tiada, jadi tiada nilai boleh diterbitkan)
        // SEPATUTNYA MUSTAHIL: borang14_form_id ialah NOT NULL dengan FK
        // cascadeOnDelete, dan backfill di atas berjalan SEBELUM FK itu digugurkan
        // (lihat di bawah), jadi setiap baris undi sedia ada masih terikat kepada
        // borang yang wujud pada ketika ini. Tetapi jika andaian itu pernah
        // tersasar (cth. FK dilangkau/dimatikan secara manual pada produksi), kita
        // ENGGAN memadam baris undi sebenar secara senyap — down() migrasi ini
        // menolak untuk kehilangan data atas prinsip yang sama; up() tidak
        // sepatutnya bersikap kurang berhati-hati berbanding down().
        $yatim = DB::table('borang14_votes')->whereNull('contest')->count();
        if ($yatim > 0) {
            throw new \RuntimeException(
                "Backfill borang14_votes.contest gagal: {$yatim} baris undi tidak dapat ".
                'dipadankan dengan mana-mana borang14_forms (borang14_form_id merujuk borang yang '.
                'tiada). Ini sepatutnya mustahil kerana borang14_form_id ialah NOT NULL dengan FK '.
                'cascadeOnDelete — SIASAT puncanya sebelum meneruskan. JANGAN jalankan semula '.
                'migrasi ini secara membuta tuli: larian semula tidak akan mengubah baris yatim ini.'
            );
        }

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

        // Langkah TERAKHIR ini menambah lajur+FK BAHARU pada borang14_forms
        // (ibu bagi borang14_votes). Pada SQLite, ->constrained() di sini
        // dilaksanakan dengan MEMBINA SEMULA seluruh jadual borang14_forms
        // (salin -> DROP jadual asal -> namakan semula) — perangkap yang SAMA
        // seperti didokumenkan dalam 2026_07_16_100001 untuk kadun_id. Semasa
        // DROP jadual borang14_forms yang asal itu, jika FK borang14_votes ->
        // borang14_forms masih aktif, SQLite akan CASCADE DELETE setiap baris
        // borang14_votes yang merujuknya — walaupun id yang sama muncul semula
        // sesaat kemudian selepas jadual dinamakan semula. PRAGMA
        // foreign_keys=OFF tidak membantu (ia no-op di dalam transaksi, dan
        // baik `artisan migrate` mahupun RefreshDatabase ujian berjalan di
        // dalam satu transaksi). Gugurkan FK borang14_votes -> borang14_forms
        // buat sementara SEBELUM langkah ini pada sqlite sahaja, dan pasang
        // semula selepasnya — MySQL tidak pernah melalui laluan rebuild ini
        // (ADD COLUMN + ADD CONSTRAINT adalah operasi in-place sebenar di
        // sana), jadi cawangan ini sqlite sahaja.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($sqlite) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->dropForeign(['borang14_form_id']);
            });
        }

        // Dicipta TERAKHIR — ia ialah pengawal larian-ulang di atas.
        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->foreignId('borang14_form_parlimen_id')->nullable()->after('tahun')
                ->constrained('borang14_forms')->nullOnDelete();
        });

        if ($sqlite) {
            Schema::table('borang14_votes', function (Blueprint $table) {
                $table->foreign('borang14_form_id')->references('id')->on('borang14_forms')->cascadeOnDelete();
            });
        }
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

        // TIDAK bersyarat pada bilangan baris dengan sengaja: skema lama (kunci sel
        // tanpa `contest`) TIADA ruang untuk dimensi pertandingan sama sekali, walau
        // berapa banyak baris undi Parlimen wujud pada borang DUN pada SAAT down()
        // ini dijalankan. Menyebut satu bilangan di sini (cth. "terdapat 0 baris...")
        // akan mencadangkan secara palsu bahawa sifar baris bermakna rollback
        // selamat — ia TIDAK: kunci lama kehilangan keupayaan membezakan PRU
        // daripada PRN buat selama-lamanya sebaik sahaja lajur `contest` digugurkan,
        // tidak kira apa nilai baris SEKARANG.
        throw new \RuntimeException(
            'Migrasi ini TIDAK BOLEH diterbalikkan (not reversible): kunci sel lama '.
            '(borang14_form_id, pusat, saluran, slot) TIADA ruang untuk dimensi `contest`. '.
            'Menggugurkan lajur itu akan memusnahkan maklumat pertandingan (PRU lawan PRN) bagi '.
            'sebarang baris yang memilikinya buat selama-lamanya, dan pada saluran serentak ia '.
            'akan melanggar kunci unik lama sebaik sahaja dua baris (satu bagi setiap pertandingan) '.
            'berkongsi (pusat, saluran, slot) yang sama. SANDARKAN data dahulu dan tangani migrasi '.
            'data secara manual jika rollback benar-benar diperlukan.'
        );
    }
};
