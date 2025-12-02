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
        Schema::create('hasil_culaan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('no_ic');
            $table->integer('umur');
            $table->string('no_tel');
            $table->string('bangsa');
            $table->text('alamat');
            $table->string('poskod');
            $table->string('negeri');
            $table->string('bandar');
            $table->string('kadun');
            $table->integer('bil_isi_rumah');
            $table->decimal('pendapatan_isi_rumah', 10, 2);
            $table->string('pekerjaan');
            $table->string('pemilik_rumah');
            $table->string('jenis_sumbangan')->nullable();
            $table->string('tujuan_sumbangan')->nullable();
            $table->string('bantuan_lain')->nullable();
            $table->string('keahlian_parti')->nullable();
            $table->string('kecenderungan_politik')->nullable();
            $table->string('kad_pengenalan')->nullable(); // File path
            $table->text('nota')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade');
            $table->timestamps(); // created_at will be the submission date/time
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_culaan');
    }
};
