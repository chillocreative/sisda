<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // alamat was first added as VARCHAR(255); some uploaded addresses are longer
    // (error 1406 "Data too long"). Widen it to TEXT.
    //
    // The raw "MODIFY" syntax below is MySQL-only. The test suite runs against
    // sqlite (RefreshDatabase), where that statement is a syntax error and
    // aborts every migration after it — so this widening is done via the
    // portable Schema Blueprint on sqlite instead.
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('keanggotaan', function (Blueprint $table) {
                $table->text('alamat')->nullable()->change();
            });

            return;
        }

        DB::statement('ALTER TABLE keanggotaan MODIFY alamat TEXT NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('keanggotaan', function (Blueprint $table) {
                $table->string('alamat', 255)->nullable()->change();
            });

            return;
        }

        DB::statement('ALTER TABLE keanggotaan MODIFY alamat VARCHAR(255) NULL');
    }
};
