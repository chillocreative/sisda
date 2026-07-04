<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aid-application details on hasil_culaan (sourced from PKR Segamat P140
 * "Permohonan Bantuan" records):
 *  - status_sumbangan : application workflow status (Baru / Lulus / Dibayar / Ditolak)
 *  - no_rujukan       : source reference no. (e.g. SGMT/PB/2026/00886) — dedup key
 *  - tarikh_sumbangan : real application/aid date (for "bila terima" in history)
 *  - jumlah_dipohon / jumlah_dilulus / jumlah_dibayar : the three PDF amount columns
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->string('status_sumbangan')->nullable()->after('tujuan_sumbangan');
            $table->string('no_rujukan')->nullable()->after('status_sumbangan');
            $table->date('tarikh_sumbangan')->nullable()->after('no_rujukan');
            $table->decimal('jumlah_dipohon', 12, 2)->nullable()->after('tarikh_sumbangan');
            $table->decimal('jumlah_dilulus', 12, 2)->nullable()->after('jumlah_dipohon');
            $table->decimal('jumlah_dibayar', 12, 2)->nullable()->after('jumlah_dilulus');
            $table->index('no_rujukan');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropIndex(['no_rujukan']);
            $table->dropColumn([
                'status_sumbangan',
                'no_rujukan',
                'tarikh_sumbangan',
                'jumlah_dipohon',
                'jumlah_dilulus',
                'jumlah_dibayar',
            ]);
        });
    }
};
