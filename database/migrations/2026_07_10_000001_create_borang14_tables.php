<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One scenario per (DUN, penjuru): holds the chosen party names per slot.
        Schema::create('borang14_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kadun_id')->constrained('kadun')->cascadeOnDelete();
            $table->unsignedTinyInteger('penjuru'); // 2 (1 vs 1) .. 6
            $table->json('parties')->nullable();     // [{slot, keahlian_parti_id, nama}]
            $table->timestamps();

            $table->unique(['kadun_id', 'penjuru']);
        });

        // One row per editable cell (party votes for a saluran within a pusat).
        // Special DUN-level rows use pusat = '' and saluran = 'UNDI AWAL' / 'UNDI POS'.
        Schema::create('borang14_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->string('pusat')->default('');
            $table->string('saluran');
            $table->unsignedTinyInteger('slot'); // party slot 1..6
            $table->unsignedInteger('undi')->default(0);
            $table->timestamps();

            $table->unique(['borang14_form_id', 'pusat', 'saluran', 'slot'], 'borang14_votes_cell_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borang14_votes');
        Schema::dropIfExists('borang14_forms');
    }
};
