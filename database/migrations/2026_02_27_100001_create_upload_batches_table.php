<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_batches', function (Blueprint $table) {
            $table->id();
            $table->string('nama_fail');
            $table->string('fail_path');
            $table->integer('jumlah_rekod')->default(0);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->boolean('is_active')->default(false);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_batches');
    }
};
