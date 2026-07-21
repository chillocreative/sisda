<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arkib bagi rekod Borang 14 yang dipadam.
 *
 * Borang14Snapshot TIDAK boleh digunakan untuk ini: lajur borang14_form_id-nya
 * NOT NULL dengan cascadeOnDelete, jadi snapshot "sebelum padam" akan dipadam
 * oleh padaman yang sepatutnya ia lindungi. Menjadikannya nullable bermakna
 * membentuk semula jadual hidup yang memegang data undi produksi (lihat nota
 * ralat MySQL 1553 dalam CLAUDE.md) — jadual TAMBAHAN ini jauh lebih selamat.
 *
 * Undi disimpan sebagai JSON persis seperti yang dibaca daripada borang, jadi
 * satu padaman tersilap masih boleh dipulihkan secara manual daripada DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borang14_deleted_forms', function (Blueprint $table) {
            $table->id();

            // Identiti kawasan disimpan sebagai NILAI, bukan kunci asing: borang
            // asal sudah tiada, dan kawasan itu sendiri boleh dipadam kemudian.
            $table->string('kawasan_type', 32)->nullable();
            $table->unsignedBigInteger('kawasan_id')->nullable();
            $table->string('kawasan_nama')->nullable();
            $table->string('jenis_pr', 8)->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('status', 24)->nullable();

            $table->json('structure')->nullable();
            $table->json('votes')->nullable();
            $table->json('parties')->nullable();

            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kawasan_type', 'kawasan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borang14_deleted_forms');
    }
};
