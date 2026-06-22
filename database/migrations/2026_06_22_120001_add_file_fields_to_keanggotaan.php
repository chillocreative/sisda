<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keanggotaan', function (Blueprint $table) {
            // Fields read straight from the uploaded membership file (no SISDA
            // cross-check). cabang/negeri are the party branch & state per file.
            $table->string('no_anggota')->nullable()->after('batch_id');
            $table->string('cabang')->nullable()->after('matched_negeri');
            $table->string('negeri')->nullable()->after('cabang');
        });

        // status_kawasan starts blank until a DPT/DPPR sync is run.
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->string('status_kawasan')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->dropColumn(['no_anggota', 'cabang', 'negeri']);
        });
    }
};
