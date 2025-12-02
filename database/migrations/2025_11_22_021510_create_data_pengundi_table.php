<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_pengundi', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('no_ic');
            $table->integer('umur');
            $table->string('no_tel');
            $table->string('bangsa');
            $table->string('hubungan')->nullable();
            $table->text('alamat');
            $table->string('poskod');
            $table->string('negeri');
            $table->string('bandar');
            $table->string('parlimen');
            $table->string('kadun');
            $table->string('keahlian_parti')->nullable();
            $table->string('kecenderungan_politik')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_pengundi');
    }
};
