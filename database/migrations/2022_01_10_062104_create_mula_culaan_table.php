<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMulaCulaanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mula_culaan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->text('alamat');
            $table->string('kadun');
            $table->string('mpkk');
            $table->bigInteger('bilangan_isi_rumah');
            $table->bigInteger('jumlah_pendapatan_isi_rumah');
            $table->text('jenis_sumbangan');
            $table->string('tujuan_sumbangan');
            $table->text('bantuan_lain');
            $table->text('keahlian_partai');    
            $table->text('kecenderungan_politik');
            $table->bigInteger('nota');
            $table->dateTime('tarikh_dan_masa');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mula_culaan');
    }
}
