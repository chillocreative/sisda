<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->decimal('jumlah_bantuan_tunai', 10, 2)->nullable()->after('bkb_program');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropColumn('jumlah_bantuan_tunai');
        });
    }
};
