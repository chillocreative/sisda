<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tetapan API electiondata.my.
 *
 * Kunci API disimpan dalam pangkalan data (disulitkan), BUKAN dalam .env —
 * mengikut konvensyen ClaudeSetting sedia ada, supaya ia boleh diurus melalui
 * skrin Tetapan tanpa deploy semula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_data_settings', function (Blueprint $table) {
            $table->id();
            $table->text('api_key')->nullable();      // cast 'encrypted' — teks, bukan string
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_data_settings');
    }
};
