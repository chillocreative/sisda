<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('route_name')->nullable();
            $table->text('url');
            $table->string('method', 10);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('params')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('route_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_page_views');
    }
};
