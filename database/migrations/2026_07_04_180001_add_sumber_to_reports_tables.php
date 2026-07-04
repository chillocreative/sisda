<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sumber` marks how a record entered the system: 'manual' (hand-entered by a
 * petugas) vs 'import' (loaded by a batch importer such as sumbangan:import-p140).
 * Lets the UI distinguish imported rows from real canvassing.
 *
 * Backfill uses each importer's signature so records already imported on prod
 * are marked correctly:
 *  - hasil_culaan  : no_rujukan like 'SGMT/%' (only the P140 importer sets it)
 *  - data_pengundi : the createVoterFromRoll placeholders no_tel/alamat/poskod = '-'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->string('sumber')->default('manual')->after('submitted_by');
        });
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->string('sumber')->default('manual')->after('submitted_by');
        });

        DB::table('hasil_culaan')
            ->where('no_rujukan', 'like', 'SGMT/%')
            ->update(['sumber' => 'import']);

        DB::table('data_pengundi')
            ->where('no_tel', '-')->where('alamat', '-')->where('poskod', '-')
            ->update(['sumber' => 'import']);
    }

    public function down(): void
    {
        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
