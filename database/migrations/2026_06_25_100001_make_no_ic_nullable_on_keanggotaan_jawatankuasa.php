<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Committee structure files (JPRC/JPRD) identify members by name + jawatan,
    // often with no IC at all — so no_ic can no longer be required.
    public function up(): void
    {
        Schema::table('keanggotaan_jawatankuasa', function (Blueprint $table) {
            $table->string('no_ic', 12)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('keanggotaan_jawatankuasa', function (Blueprint $table) {
            $table->string('no_ic', 12)->nullable(false)->change();
        });
    }
};
