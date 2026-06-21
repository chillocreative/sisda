<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keanggotaan_settings', function (Blueprint $table) {
            $table->id();
            // Penggal Pemilihan Parti — the active party-election term, by year.
            // Drives wing (AMK/Srikandi/Wanita) eligibility: members who pass 35
            // stay valid (flagged) until the term's end year passes.
            $table->unsignedSmallInteger('tahun_mula')->nullable();
            $table->unsignedSmallInteger('tahun_tamat')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keanggotaan_settings');
    }
};
