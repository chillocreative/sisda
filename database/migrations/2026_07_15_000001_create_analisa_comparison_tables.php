<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saveable AI comparison scenarios for the Analisa Keputusan page.
 *
 * A comparison holds 1–3 scenarios (each = one election: an uploaded
 * scoresheet + label + date). fact_payload is the server-computed
 * ground truth sent to the AI; ai_result caches the sanitized AI output
 * so re-opening a comparison never re-bills the (web-search) analysis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analisa_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('kawasan_id', 10);          // e.g. 'N01'
            $table->string('dun', 120);                // 'N01 BULOH KASAP'
            $table->string('parlimen', 120);           // 'P140 SEGAMAT'
            $table->string('status', 20)->default('draft'); // draft | analyzed
            $table->json('fact_payload')->nullable();  // ground truth fed to the AI
            $table->json('ai_result')->nullable();     // sanitized AI output
            $table->string('ai_status', 20)->nullable(); // ok | fallback
            $table->string('ai_model', 80)->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->unsignedSmallInteger('web_search_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });

        Schema::create('analisa_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analisa_comparison_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');   // 1–3
            $table->string('label', 120);              // 'PRN Johor 2022'
            $table->date('election_date');
            $table->string('source_filename', 255)->nullable();
            $table->json('parsed_rows');               // normalized scoresheet rows
            $table->json('parsed_totals');             // totals map from the parser
            $table->unsignedSmallInteger('row_count')->default(0);
            $table->timestamps();

            $table->unique(['analisa_comparison_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisa_scenarios');
        Schema::dropIfExists('analisa_comparisons');
    }
};
