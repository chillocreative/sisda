<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lokaliti', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('daerah_mengundi_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lokaliti');
    }
};
