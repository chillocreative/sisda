<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur Borang 14 SEBENAR daripada muat naik DPPR/DPI.
 *
 * Fail DPI membawa lajur `Pusat Mengundi` dan `Saluran` yang selama ini dibuang
 * oleh importer, memaksa Borang14Reference MENGANGGAR struktur (satu Lokaliti =
 * satu Pusat Mengundi dengan satu Saluran). Dua lajur ini menyimpannya.
 *
 * SENGAJA: nullable, TIADA default, TIADA index, ditambah di HUJUNG baris.
 * pangkalan_data_pengundi ialah jadual terbesar dalam sistem dan migrasi
 * berjalan pada SETIAP deploy terhadap data produksi langsung. `ADD COLUMN`
 * nullable tanpa index memenuhi syarat algoritma INSTANT MySQL 8 — perubahan
 * metadata sahaja, tiada bina semula jadual dan tiada kunci. Menambah index di
 * sini akan membina semula berjuta baris semasa `migrate --force`.
 *
 * Baris sedia ada kekal NULL. NULL bermaksud "TIDAK DIKETAHUI", BUKAN "tiada
 * saluran" — kerusi tersebut terus menggunakan anggaran sehingga failnya
 * dimuat naik semula. Tiada backfill kerana maklumat itu memang tidak pernah
 * disimpan; mereka-reka nilai di sini akan menghasilkan struktur palsu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pangkalan_data_pengundi', function (Blueprint $table) {
            if (! Schema::hasColumn('pangkalan_data_pengundi', 'pusat_mengundi')) {
                $table->string('pusat_mengundi', 255)->nullable();
            }
            if (! Schema::hasColumn('pangkalan_data_pengundi', 'saluran')) {
                $table->string('saluran', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pangkalan_data_pengundi', function (Blueprint $table) {
            $table->dropColumn(['pusat_mengundi', 'saluran']);
        });
    }
};
