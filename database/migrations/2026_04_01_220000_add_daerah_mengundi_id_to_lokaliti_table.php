<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lokaliti', function (Blueprint $table) {
            $table->foreignId('daerah_mengundi_id')->nullable()->after('nama')->constrained('daerah_mengundi')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('lokaliti', function (Blueprint $table) {
            $table->dropForeign(['daerah_mengundi_id']);
            $table->dropColumn('daerah_mengundi_id');
        });
    }
};
