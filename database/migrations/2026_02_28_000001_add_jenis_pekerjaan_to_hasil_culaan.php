<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->string('jenis_pekerjaan')->nullable()->after('pekerjaan');
            $table->string('jenis_pekerjaan_lain')->nullable()->after('jenis_pekerjaan');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropColumn(['jenis_pekerjaan', 'jenis_pekerjaan_lain']);
        });
    }
};
