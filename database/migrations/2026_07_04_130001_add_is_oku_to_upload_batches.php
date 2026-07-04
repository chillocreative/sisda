<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marks a voter-database upload as an OKU (disabled voters) list — voters
        // whose IC appears in an OKU batch are tagged OKU on Data Pengundi.
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->boolean('is_oku')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropColumn('is_oku');
        });
    }
};
