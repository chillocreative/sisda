<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-upload record + report for the "Upload Culaan" feature (field-canvassing
 * sentiment CSVs). Unlike upload_batches (the DPPR roll union), this is a
 * one-off enrichment log — it does not become an active roll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('culaan_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('nama_fail');
            $table->string('fail_path');
            $table->string('file_hash')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('error')->nullable();
            $table->integer('jumlah_baris')->default(0);
            $table->integer('matched')->default(0);
            $table->integer('dicipta')->default(0);       // created from roll
            $table->integer('dikemaskini')->default(0);   // updated existing
            $table->integer('tidak_dijumpai')->default(0);// name not in roll
            $table->integer('taksah')->default(0);        // ambiguous (>1 in scope)
            $table->integer('tiada_sentimen')->default(0);// blank -> TIDAK PASTI
            $table->json('report')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('culaan_uploads');
    }
};
