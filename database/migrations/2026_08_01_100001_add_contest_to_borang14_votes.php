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
        // (ibu kepada BEBERAPA jadual). Pada SQLite, ->constrained() di sini
        // dilaksanakan dengan MEMBINA SEMULA seluruh jadual borang14_forms
        // (salin -> DROP jadual asal -> namakan semula) — perangkap yang SAMA
        // seperti didokumenkan dalam 2026_07_16_100001 untuk kadun_id. Semasa
        // DROP jadual borang14_forms yang asal itu, SETIAP tindakan ON DELETE
        // anak yang masih merujuknya akan menyala — walaupun id yang sama
        // muncul semula sesaat kemudian selepas jadual dinamakan semula.
        // PRAGMA foreign_keys=OFF tidak membantu (ia no-op di dalam transaksi,
        // dan baik `artisan migrate` mahupun RefreshDatabase ujian berjalan di
        // dalam satu transaksi). MySQL tidak pernah melalui laluan rebuild ini
        // (ADD COLUMN + ADD CONSTRAINT adalah operasi in-place sebenar di
        // sana), jadi seluruh pengawal ini sqlite sahaja dan jujukan statement
        // MySQL kekal tidak berubah.
        //
        // Draf awal cawangan ini hanya melindungi borang14_votes. Semakan akhir
        // membuktikan (dengan menjalankan up() pada pangkalan data SQLite yang
        // berisi) bahawa borang14_snapshots turut lenyap 1 -> 0 dan
        // scoreboards.borang14_form_id turut menjadi NULL. Anak ditemui secara
        // DINAMIK di bawah — bukan disenaraikan dengan tangan — supaya jadual
        // anak yang ditambah kemudian tidak boleh terlepas secara senyap.
        //
        // DUA rawatan, dan perbezaannya BUKAN gaya:
        //
        //  * cascade (baris DIPADAM) -> gugurkan FK sementara, pasang semula.
        //    dropForeign() pada SQLite juga membina semula jadual ANAK itu,
        //    jadi ia hanya selamat untuk jadual DAUN. Disahkan pada masa
        //    larian; jika bukan daun, migrasi ini BERHENTI dan bukan meneka.
        //
        //  * set null (pautan HILANG) -> JANGAN sentuh FK-nya. Menggugurkan FK
        //    pada paca_forms membina semula paca_forms, dan DROP itu
        //    cascade-memadam SELURUH roster PACA di bawahnya (paca_pusat ->
        //    paca_saluran -> paca_slot, paca_snapshots) — kerosakan yang jauh
        //    LEBIH TERUK daripada yang cuba dielakkan. Sebaliknya kita rakam
        //    peta id -> borang id, biarkan ia dinullkan, dan tulis semula.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $fkDigugur = [];
        $pautanDisimpan = [];

        if ($sqlite) {
            $semuaFk = $this->semuaFk();

            foreach ($semuaFk as $fk) {
                if ($fk['foreign_table'] !== 'borang14_forms' || $fk['table'] === 'borang14_forms') {
                    continue;
                }

                $tindakan = strtolower((string) ($fk['on_delete'] ?? ''));

                if ($tindakan === 'cascade') {
                    $adaAnak = collect($semuaFk)->contains(fn ($lain) => $lain['foreign_table'] === $fk['table']);
                    if ($adaAnak) {
                        throw new \RuntimeException(
                            "Jadual '{$fk['table']}' merujuk borang14_forms dengan ON DELETE CASCADE, tetapi ia ".
                            'BUKAN jadual daun — jadual lain merujuknya. Menggugurkan FK-nya pada SQLite akan '.
                            'membina semula jadual itu dan cascade-memadam anaknya sendiri. Tangani kes ini '.
                            'secara eksplisit sebelum menjalankan migrasi ini pada SQLite.'
                        );
                    }
                    $fkDigugur[] = $fk;
                } elseif ($tindakan === 'set null') {
                    $lajur = $fk['columns'][0];
                    if (! Schema::hasColumn($fk['table'], 'id')) {
                        throw new \RuntimeException(
                            "Jadual '{$fk['table']}' merujuk borang14_forms dengan ON DELETE SET NULL tetapi tiada ".
                            'kunci utama `id`, jadi pautannya tidak boleh dirakam dan ditulis semula selepas '.
                            'pembinaan semula SQLite. Tangani kes ini secara eksplisit.'
                        );
                    }
                    $pautanDisimpan[] = [
                        'table' => $fk['table'],
                        'column' => $lajur,
                        'nilai' => DB::table($fk['table'])->whereNotNull($lajur)->pluck($lajur, 'id')->all(),
                    ];
                } else {
                    throw new \RuntimeException(
                        "Jadual '{$fk['table']}' merujuk borang14_forms dengan ON DELETE '{$tindakan}' yang tidak ".
                        'dikendalikan oleh pengawal SQLite migrasi ini. Tambah pengendalian untuknya — JANGAN '.
                        'jalankan migrasi ini pada SQLite berisi data sehingga itu.'
                    );
                }
            }

            foreach ($fkDigugur as $fk) {
                Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk['columns']);
                });
            }
        }

        // Dicipta TERAKHIR — ia ialah pengawal larian-ulang di atas.
        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->foreignId('borang14_form_parlimen_id')->nullable()->after('tahun')
                ->constrained('borang14_forms')->nullOnDelete();
        });

        foreach ($fkDigugur as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                $table->foreign($fk['columns'])->references($fk['foreign_columns'])
                    ->on('borang14_forms')->cascadeOnDelete();
            });
        }

        // Tulis semula pautan yang dinullkan oleh rebuild. Dikumpul mengikut
        // nilai supaya satu UPDATE menampung banyak baris.
        foreach ($pautanDisimpan as $p) {
            // preserveKeys = true WAJIB: tanpanya groupBy() mengindeks semula
            // dan keys() akan memulangkan 0,1,2... bukan id baris sebenar.
            foreach (collect($p['nilai'])->groupBy(fn ($v) => (int) $v, true) as $borangId => $baris) {
                DB::table($p['table'])->whereIn('id', $baris->keys()->all())
                    ->update([$p['column'] => (int) $borangId]);
            }
        }
    }

    /**
     * Setiap FK dalam pangkalan data, dilekatkan dengan jadual pemiliknya.
     *
     * @return array<int,array{table:string,columns:array,foreign_table:string,foreign_columns:array,on_delete:?string}>
     */
    private function semuaFk(): array
    {
        $senarai = [];

        foreach (Schema::getTableListing() as $jadual) {
            // SQLite memulangkan nama berkelayakan skema ('main.users').
            $jadual = str_contains($jadual, '.') ? substr($jadual, strrpos($jadual, '.') + 1) : $jadual;

            foreach (Schema::getForeignKeys($jadual) as $fk) {
                $senarai[] = [
                    'table' => $jadual,
                    'columns' => $fk['columns'],
                    'foreign_table' => $fk['foreign_table'],
                    'foreign_columns' => $fk['foreign_columns'],
                    'on_delete' => $fk['on_delete'] ?? null,
                ];
            }
        }

        return $senarai;
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
