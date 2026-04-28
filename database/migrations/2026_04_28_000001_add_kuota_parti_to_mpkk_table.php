<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpkk', function (Blueprint $table) {
            $table->string('kuota_parti', 50)->nullable()->after('kadun_id');
        });
    }

    public function down(): void
    {
        Schema::table('mpkk', function (Blueprint $table) {
            $table->dropColumn('kuota_parti');
        });
    }
};
