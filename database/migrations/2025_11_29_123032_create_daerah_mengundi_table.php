<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daerah_mengundi', function (Blueprint $table) {
            $table->id();
            $table->string('kod_dm'); // e.g., 041/01/02
            $table->string('nama'); // Name of Daerah Mengundi
            $table->foreignId('bandar_id')->constrained('bandar')->onDelete('cascade'); // Parlimen
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daerah_mengundi');
    }
};
