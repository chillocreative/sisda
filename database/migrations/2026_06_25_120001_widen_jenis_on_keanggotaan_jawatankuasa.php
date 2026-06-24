<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The committee-type list keeps growing (JPRC, JPRD, wings, MPKK, JBPP,
    // JPWK, ...). Switch the enum to a plain string so new types are an
    // app-level change only — no further enum migrations.
    public function up(): void
    {
        DB::statement("ALTER TABLE keanggotaan_jawatankuasa MODIFY COLUMN jenis VARCHAR(30) NOT NULL DEFAULT 'JPRC'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE keanggotaan_jawatankuasa MODIFY COLUMN jenis ENUM('JPRC','JPRD','AJK_CABANG','WANITA','AMK') NOT NULL");
    }
};
