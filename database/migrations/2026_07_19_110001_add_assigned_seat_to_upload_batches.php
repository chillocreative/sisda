<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional seat assignment for uploads whose file carries NO geography
        // columns (e.g. a single-seat "TERAS" supporter list). When set, the
        // importer stamps these names onto any row it left blank, so the roll
        // is findable in the War Room seat filters. Strictly additive.
        Schema::table('upload_batches', function (Blueprint $table) {
            foreach (['assign_negeri', 'assign_parlimen', 'assign_kadun'] as $col) {
                if (! Schema::hasColumn('upload_batches', $col)) {
                    $table->string($col)->nullable()->after('ai_detail');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            foreach (['assign_kadun', 'assign_parlimen', 'assign_negeri'] as $col) {
                if (Schema::hasColumn('upload_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
