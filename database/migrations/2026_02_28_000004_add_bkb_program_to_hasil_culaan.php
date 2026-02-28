<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->string('bkb_program')->nullable()->after('isejahtera_program');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropColumn('bkb_program');
        });
    }
};
