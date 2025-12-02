<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Territory fields
            $table->unsignedBigInteger('negeri_id')->nullable()->after('role');
            $table->unsignedBigInteger('bandar_id')->nullable()->after('negeri_id');
            $table->unsignedBigInteger('kadun_id')->nullable()->after('bandar_id');
            
            // Approval fields
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('kadun_id');
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            
            // Foreign keys
            $table->foreign('negeri_id')->references('id')->on('negeri')->onDelete('set null');
            $table->foreign('bandar_id')->references('id')->on('bandar')->onDelete('set null');
            $table->foreign('kadun_id')->references('id')->on('kadun')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['negeri_id']);
            $table->dropForeign(['bandar_id']);
            $table->dropForeign(['kadun_id']);
            $table->dropForeign(['approved_by']);
            
            // Drop columns
            $table->dropColumn([
                'negeri_id',
                'bandar_id',
                'kadun_id',
                'status',
                'approved_by',
                'approved_at'
            ]);
        });
    }
};
