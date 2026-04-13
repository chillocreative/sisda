<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->integer('bil_isi_rumah')->nullable()->change();
            $table->decimal('pendapatan_isi_rumah', 10, 2)->nullable()->change();
            $table->string('pekerjaan')->nullable()->change();
            $table->string('pemilik_rumah')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->integer('bil_isi_rumah')->nullable(false)->change();
            $table->decimal('pendapatan_isi_rumah', 10, 2)->nullable(false)->change();
            $table->string('pekerjaan')->nullable(false)->change();
            $table->string('pemilik_rumah')->nullable(false)->change();
        });
    }
};
