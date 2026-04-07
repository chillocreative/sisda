<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->index('no_ic');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropIndex(['no_ic']);
        });
    }
};
