<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claude_settings', function (Blueprint $table) {
            // Optional stronger model used only for reading documents/images
            // (scoresheet & PDF extraction). Null = follow the main `model`.
            $table->string('document_model')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('claude_settings', function (Blueprint $table) {
            $table->dropColumn('document_model');
        });
    }
};
