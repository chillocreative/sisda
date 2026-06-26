<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Member home address, read straight from the uploaded membership file.
    public function up(): void
    {
        Schema::table('keanggotaan', function (Blueprint $table) {
            // TEXT, not string(255) — some uploaded addresses exceed 255 chars.
            $table->text('alamat')->nullable()->after('negeri');
        });
    }

    public function down(): void
    {
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->dropColumn('alamat');
        });
    }
};
