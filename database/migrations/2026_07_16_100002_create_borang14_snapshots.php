<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borang14_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->json('structure')->nullable();
            $table->json('votes');
            $table->json('parties')->nullable();
            $table->string('reason', 40);   // 'before_scoresheet_overwrite'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['borang14_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borang14_snapshots');
    }
};
