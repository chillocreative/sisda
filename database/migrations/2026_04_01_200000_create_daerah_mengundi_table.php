<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daerah_mengundi', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('mpkk_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('daerah_mengundi');
    }
};
