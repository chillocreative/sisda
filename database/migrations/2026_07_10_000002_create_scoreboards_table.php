<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scoreboard display settings per (DUN, penjuru) scenario. The live vote
        // figures are read from borang14_votes; this table only holds the
        // presentation config (title, logo, candidates, win threshold).
        Schema::create('scoreboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kadun_id')->constrained('kadun')->cascadeOnDelete();
            $table->unsignedTinyInteger('penjuru');
            $table->string('title')->default('SCOREBOARD');
            $table->unsignedInteger('minima')->nullable(); // minimum votes PH needs to win
            $table->string('logo_path')->nullable();        // custom logo (else the party logo)
            $table->json('candidates')->nullable();          // [{slot, nama, gambar}]
            $table->timestamps();

            $table->unique(['kadun_id', 'penjuru']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoreboards');
    }
};
