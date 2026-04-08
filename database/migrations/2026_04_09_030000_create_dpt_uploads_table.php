<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dpt_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('label');
            $table->string('parlimen')->nullable();
            $table->string('negeri')->nullable();
            $table->string('bulan')->nullable();
            $table->string('tahun')->nullable();
            $table->string('tarikh_warta')->nullable();
            $table->integer('total_records')->default(0);
            $table->integer('total_deceased')->default(0);
            $table->integer('total_new')->default(0);
            $table->integer('total_moved')->default(0);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('error')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dpt_uploads');
    }
};
