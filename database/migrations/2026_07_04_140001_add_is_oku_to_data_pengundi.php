<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual per-voter OKU flag, set from the Data Pengundi edit form. This
        // is in addition to the OKU-batch derivation (a voter shows OKU if this
        // flag is set OR their IC is in an OKU upload batch).
        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->boolean('is_oku')->default(false)->after('is_deceased');
        });
    }

    public function down(): void
    {
        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->dropColumn('is_oku');
        });
    }
};
