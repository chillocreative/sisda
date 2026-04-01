<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('mula_culaan', function (Blueprint $table) {
            $table->string('parlimen')->nullable()->after('bandar');
            $table->string('daerah_mengundi')->nullable()->after('mpkk');
            $table->string('lokaliti')->nullable()->after('daerah_mengundi');
        });

        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->string('mpkk')->nullable()->after('kadun');
            $table->string('daerah_mengundi')->nullable()->after('mpkk');
            $table->string('lokaliti')->nullable()->after('daerah_mengundi');
        });
    }

    public function down()
    {
        Schema::table('mula_culaan', function (Blueprint $table) {
            $table->dropColumn(['parlimen', 'daerah_mengundi', 'lokaliti']);
        });

        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->dropColumn(['mpkk', 'daerah_mengundi', 'lokaliti']);
        });
    }
};
