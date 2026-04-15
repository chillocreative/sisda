<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_pengundi_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_pengundi_id')
                ->constrained('data_pengundi')
                ->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->text('nota')->nullable();
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['data_pengundi_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_pengundi_documents');
    }
};
