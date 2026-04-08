<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pangkalan_data_pengundi', function (Blueprint $table) {
            $table->string('kod_lokaliti')->nullable()->after('lokaliti');
            $table->string('jantina')->nullable()->after('bangsa');
            $table->string('tahun_lahir')->nullable()->after('jantina');
            $table->boolean('is_deceased')->default(false)->after('tahun_lahir');
            $table->unsignedBigInteger('dpt_upload_id')->nullable()->after('upload_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('pangkalan_data_pengundi', function (Blueprint $table) {
            $table->dropColumn(['kod_lokaliti', 'jantina', 'tahun_lahir', 'is_deceased', 'dpt_upload_id']);
        });
    }
};
