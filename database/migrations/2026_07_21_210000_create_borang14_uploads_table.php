<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sejarah muat naik scoresheet Borang 14.
 *
 * Sebelum ini muat naik tidak meninggalkan sebarang jejak: hasil ekstrak
 * disimpan dalam Cache selama beberapa minit dan fail asal tidak pernah
 * ditulis ke cakera. Tiada senarai apa yang dimuat naik, oleh siapa, untuk
 * kerusi mana — dan tiada cara membaca semula fail asal apabila angka
 * dipertikaikan.
 *
 * Jadual ini TAMBAHAN semata-mata (tiada jadual sedia ada disentuh), jadi
 * `migrate --force` semasa deploy tidak boleh menyentuh data undi Borang 14
 * yang sedang hidup di produksi.
 *
 * Setiap lajur angka NULLABLE dengan sengaja: sheet yang tidak dapat dibaca
 * mesti merekod "tidak diketahui", bukan 0. Sifar di sini akan dibaca sebagai
 * "sifar undi diekstrak" dan menghasilkan dakwaan penurunan palsu di hiliran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borang14_uploads', function (Blueprint $table) {
            $table->id();

            // Nullable: baris direkod walaupun borang gagal dicipta, supaya
            // percubaan yang gagal pun meninggalkan jejak audit.
            $table->foreignId('borang14_form_id')->nullable()->constrained('borang14_forms')->nullOnDelete();

            $table->string('kawasan_type', 32)->nullable();
            $table->unsignedBigInteger('kawasan_id')->nullable();
            $table->string('negeri')->nullable();
            $table->string('parlimen')->nullable();
            $table->string('dun')->nullable();

            $table->string('nama_fail');
            $table->string('fail_path')->nullable();     // null jika fail gagal disimpan
            $table->string('jenis_pr', 8)->nullable();
            $table->unsignedSmallInteger('tahun')->nullable();

            // 'deterministic' (Spr760Parser) atau 'ai' (Claude) — membezakan
            // bacaan yang boleh dibuktikan daripada bacaan yang dianggarkan.
            $table->string('source', 24)->nullable();

            $table->unsignedInteger('row_count')->nullable();
            $table->unsignedInteger('saluran_count')->nullable();
            $table->json('totals')->nullable();          // jumlah bercetak + jumlah dikira
            $table->boolean('needs_review')->default(false);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kawasan_type', 'kawasan_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borang14_uploads');
    }
};
