<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Traceability for the AI fallback extractor: when the fast parser reads
        // 0 rows and Claude takes over (messy headers / junk rows / freeform PDF),
        // record that it happened and what it detected. Strictly additive.
        Schema::table('upload_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('upload_batches', 'ai_used')) {
                $table->boolean('ai_used')->default(false)->after('is_oku');
            }
            if (! Schema::hasColumn('upload_batches', 'ai_detail')) {
                // {path, mapping, chunks, skipped, error} per AI-extracted file.
                $table->json('ai_detail')->nullable()->after('ai_used');
            }
        });
    }

    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            foreach (['ai_detail', 'ai_used'] as $col) {
                if (Schema::hasColumn('upload_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
