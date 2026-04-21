<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rule_code', 64);
            $table->string('rule_hash', 64);
            $table->string('severity', 16)->default('low');
            $table->string('verdict')->nullable();
            $table->text('summary')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->string('whatsapp_status', 16)->default('skipped');
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'rule_hash', 'window_start'], 'user_activity_alerts_dedupe_unique');
            $table->index(['severity', 'created_at']);
            $table->index('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_alerts');
    }
};
