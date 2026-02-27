<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pangkalan_data_pengundi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_batch_id')->constrained('upload_batches')->cascadeOnDelete();
            $table->string('no_ic', 12);
            $table->string('nama');
            $table->string('lokaliti')->nullable();
            $table->string('daerah_mengundi')->nullable();
            $table->string('kadun')->nullable();
            $table->string('parlimen')->nullable();
            $table->string('negeri')->nullable();
            $table->string('bangsa')->nullable();
            $table->timestamps();

            $table->index('no_ic');
            $table->index('upload_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pangkalan_data_pengundi');
    }
};
