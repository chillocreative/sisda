<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-member EKYC status read straight from the uploaded file's
        // "STATUS EKYC" column: 'completed' or 'pending'. NULL means the file
        // never said — it is NOT "pending" — and the older batch-level rule
        // (status Aktif or an is_ekyc batch) still decides those members.
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->string('status_ekyc', 20)->nullable()->after('status_anggota');
        });
    }

    public function down(): void
    {
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->dropColumn('status_ekyc');
        });
    }
};
