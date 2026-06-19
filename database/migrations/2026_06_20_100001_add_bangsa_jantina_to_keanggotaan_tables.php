<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['keanggotaan', 'keanggotaan_jawatankuasa'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('bangsa')->nullable()->after('umur');
                $t->string('jantina')->nullable()->after('bangsa');
            });
        }
    }

    public function down(): void
    {
        foreach (['keanggotaan', 'keanggotaan_jawatankuasa'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['bangsa', 'jantina']);
            });
        }
    }
};
