<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Member status set by admin / call centre (Pusat Panggilan): aktif /
    // tidak_aktif, plus a "registered without their knowledge" flag.
    public function up(): void
    {
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->string('status_anggota')->nullable()->after('status_kawasan');
            $table->boolean('daftar_tanpa_pengetahuan')->default(false)->after('status_anggota');
        });
    }

    public function down(): void
    {
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->dropColumn(['status_anggota', 'daftar_tanpa_pengetahuan']);
        });
    }
};
