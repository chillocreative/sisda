<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // alamat was first added as VARCHAR(255); some uploaded addresses are longer
    // (error 1406 "Data too long"). Widen it to TEXT.
    public function up(): void
    {
        DB::statement('ALTER TABLE keanggotaan MODIFY alamat TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE keanggotaan MODIFY alamat VARCHAR(255) NULL');
    }
};
