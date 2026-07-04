<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // EKYC-verified batch: ticking it marks every member in the batch as an
        // active anggota and shows a green EKYC tick on the Senarai.
        Schema::table('keanggotaan_batches', function (Blueprint $table) {
            $table->boolean('is_ekyc')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('keanggotaan_batches', function (Blueprint $table) {
            $table->dropColumn('is_ekyc');
        });
    }
};
