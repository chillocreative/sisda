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
        Schema::create('bandar', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kod_parlimen')->nullable();
            $table->foreignId('negeri_id')->constrained('negeri')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bandar');
    }
};
