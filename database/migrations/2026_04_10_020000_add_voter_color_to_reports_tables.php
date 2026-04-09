<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->string('voter_color')->nullable()->after('status_pengundi');
        });

        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->string('voter_color')->nullable()->after('status_pengundi');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropColumn('voter_color');
        });
        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->dropColumn('voter_color');
        });
    }
};
