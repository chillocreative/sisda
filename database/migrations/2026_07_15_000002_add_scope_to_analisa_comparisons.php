<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make comparisons work Malaysia-wide for any Parlimen or DUN. A comparison is
 * now scoped by `level` ('dun' | 'parlimen') to a real Bandar (Parlimen) and,
 * for DUN-level, a Kadun (DUN) — instead of the single hardcoded Buloh Kasap
 * kawasan_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisa_comparisons', function (Blueprint $table) {
            $table->string('level', 12)->default('dun')->after('kawasan_id');
            $table->string('negeri', 120)->nullable()->after('level');
            $table->unsignedBigInteger('bandar_id')->nullable()->after('negeri');
            $table->unsignedBigInteger('kadun_id')->nullable()->after('bandar_id');
            $table->string('dun', 120)->nullable()->change();
            $table->string('kawasan_id', 10)->nullable()->change();

            $table->index('bandar_id');
            $table->index('kadun_id');
        });
    }

    public function down(): void
    {
        Schema::table('analisa_comparisons', function (Blueprint $table) {
            $table->dropIndex(['bandar_id']);
            $table->dropIndex(['kadun_id']);
            $table->dropColumn(['level', 'negeri', 'bandar_id', 'kadun_id']);
        });
    }
};
