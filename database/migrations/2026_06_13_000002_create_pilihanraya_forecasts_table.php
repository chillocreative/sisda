<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilihanraya_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // forecast | war_game | resources | briefing
            $table->string('scope_level')->default('national'); // national | negeri | parlimen | kadun
            $table->string('scope_name')->nullable();
            $table->json('payload');
            $table->json('result')->nullable();
            $table->string('status'); // ok | fallback | failed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilihanraya_forecasts');
    }
};
