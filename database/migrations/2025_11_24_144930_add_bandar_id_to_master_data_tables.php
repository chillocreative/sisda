<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'tujuan_sumbangan',
            'jenis_sumbangan',
            'bantuan_lain',
            'keahlian_parti',
            'kecenderungan_politik',
            'hubungan'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->foreignId('bandar_id')->nullable()->after('id')->constrained('bandar')->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'tujuan_sumbangan',
            'jenis_sumbangan',
            'bantuan_lain',
            'keahlian_parti',
            'kecenderungan_politik',
            'hubungan'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['bandar_id']);
                    $table->dropColumn('bandar_id');
                });
            }
        }
    }
};
