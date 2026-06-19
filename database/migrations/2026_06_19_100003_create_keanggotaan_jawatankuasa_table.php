<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keanggotaan_jawatankuasa', function (Blueprint $table) {
            $table->id();
            $table->string('no_ic', 12);
            $table->string('nama');
            // JPRC | JPRD | AJK_CABANG | WANITA | AMK
            $table->enum('jenis', ['JPRC', 'JPRD', 'AJK_CABANG', 'WANITA', 'AMK']);
            $table->string('jawatan')->nullable();
            $table->string('cabang')->nullable();
            $table->string('dun')->nullable();
            $table->string('no_tel')->nullable();

            // Cached SISDA match (shared shape with the keanggotaan table).
            $table->string('matched_kadun')->nullable();
            $table->string('matched_parlimen')->nullable();
            $table->string('matched_negeri')->nullable();
            $table->string('tahun_lahir')->nullable();
            $table->integer('umur')->nullable();
            $table->string('voter_color')->nullable();
            $table->boolean('is_dicula')->default(false);
            $table->boolean('is_pendaftaran_baru')->default(false);
            $table->string('status_kawasan')->default('luar_kawasan');
            $table->timestamps();

            $table->index('no_ic');
            $table->index('jenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keanggotaan_jawatankuasa');
    }
};
