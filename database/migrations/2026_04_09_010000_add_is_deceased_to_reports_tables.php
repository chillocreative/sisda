<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->boolean('is_deceased')->default(false)->after('nota');
        });

        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->boolean('is_deceased')->default(false)->after('kecenderungan_politik');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropColumn('is_deceased');
        });

        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->dropColumn('is_deceased');
        });
    }
};
