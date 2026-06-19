<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pangkalan_data_pengundi', function (Blueprint $table) {
            // Set when a row was added under a DPT "PENDAFTARAN BARU" section.
            // Only DPT PDFs imported after this migration carry the flag —
            // pre-existing roll rows stay false (treated as "pengundi lama").
            $table->boolean('pendaftaran_baru')->default(false)->after('is_deceased');
        });
    }

    public function down(): void
    {
        Schema::table('pangkalan_data_pengundi', function (Blueprint $table) {
            $table->dropColumn('pendaftaran_baru');
        });
    }
};
