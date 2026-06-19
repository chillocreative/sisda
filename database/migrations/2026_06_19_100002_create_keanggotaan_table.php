<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keanggotaan', function (Blueprint $table) {
            $table->id();
            // Null batch_id = a manually-entered member (not from an upload).
            $table->foreignId('batch_id')->nullable()->constrained('keanggotaan_batches')->cascadeOnDelete();
            $table->string('no_ic', 12);
            $table->string('nama');
            $table->string('no_tel')->nullable();

            // Cached SISDA match (see MemberMatchService). Recomputed on
            // import and via "Sync semula" when active voter batches change.
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
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keanggotaan');
    }
};
