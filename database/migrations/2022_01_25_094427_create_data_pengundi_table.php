<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataPengundiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_pengundi', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('no_kad');
            $table->bigInteger('umur');
            $table->string('phone');
            $table->string('bangsa');
            $table->string('alamat');
            $table->string('alamat2')->nullable();
            $table->string('poskod');
            $table->string('negeri');
            $table->string('bandar');
            $table->string('parlimen');
            $table->string('kadun');
            $table->bigInteger('user_id');
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
        Schema::dropIfExists('data_pengundi');
    }
}
