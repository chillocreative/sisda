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
        // Add sort_order column to all master data tables
        $tables = [
            'tujuan_sumbangan',
            'jenis_sumbangan',
            'bantuan_lain',
            'keahlian_parti',
            'kecenderungan_politik',
            'hubungan',
            'bangsa',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('nama');
            });
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
            'hubungan',
            'bangsa',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
