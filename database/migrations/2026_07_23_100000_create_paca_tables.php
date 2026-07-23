<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Borang PACABA: struktur petugas (Pusat Mengundi → Saluran → slot PA1/PA2/PA3/CA) bagi satu kawasan.
        Schema::create('paca_forms', function (Blueprint $t) {
            $t->id();
            $t->string('kawasan_type', 10);
            $t->unsignedBigInteger('kawasan_id');
            $t->string('jenis_pr', 4);
            $t->unsignedSmallInteger('tahun');
            $t->foreignId('borang14_form_id')->nullable()->constrained('borang14_forms')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['kawasan_type', 'kawasan_id', 'jenis_pr', 'tahun'], 'paca_forms_kawasan_unique');
        });

        Schema::create('paca_pusat', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paca_form_id')->constrained('paca_forms')->cascadeOnDelete();
            $t->string('dm')->default('');
            $t->string('pusat');
            $t->string('ketua_nama')->nullable();
            $t->string('ketua_tel')->nullable();
            $t->string('public_token', 64)->unique();
            $t->unsignedInteger('urutan')->default(0);
            $t->timestamps();
        });

        Schema::create('paca_saluran', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paca_pusat_id')->constrained('paca_pusat')->cascadeOnDelete();
            $t->string('label');
            $t->unsignedInteger('urutan')->default(0);
            $t->timestamps();
        });

        Schema::create('paca_slot', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paca_saluran_id')->constrained('paca_saluran')->cascadeOnDelete();
            $t->string('jawatan', 8);            // 'PA1'..'PAn' | 'CA'
            $t->string('masa_mula', 5)->nullable();  // 'HH:MM'
            $t->string('masa_tamat', 5)->nullable(); // null = 'selesai'
            $t->unsignedInteger('urutan')->default(0);
            $t->string('petugas_nama')->nullable();
            $t->string('petugas_kp')->nullable();
            $t->string('petugas_tel')->nullable();
            $t->string('petugas_parti')->nullable();
            $t->timestamps();
        });

        Schema::create('paca_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paca_form_id')->constrained('paca_forms')->cascadeOnDelete();
            $t->json('data');
            $t->string('reason', 40);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->index(['paca_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Susunan songsang mengikut kekangan foreign key.
        Schema::dropIfExists('paca_snapshots');
        Schema::dropIfExists('paca_slot');
        Schema::dropIfExists('paca_saluran');
        Schema::dropIfExists('paca_pusat');
        Schema::dropIfExists('paca_forms');
    }
};
