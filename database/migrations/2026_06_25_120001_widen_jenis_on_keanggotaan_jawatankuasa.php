<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The committee-type list keeps growing (JPRC, JPRD, wings, MPKK, JBPP,
    // JPWK, ...). Switch the enum to a plain string so new types are an
    // app-level change only — no further enum migrations.
    //
    // The raw "MODIFY COLUMN" syntax below is MySQL-only. The test suite runs
    // against sqlite (RefreshDatabase), where that statement is a syntax
    // error and aborts every migration after it — so this widening is done
    // via the portable Schema Blueprint on sqlite instead.
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('keanggotaan_jawatankuasa', function (Blueprint $table) {
                $table->string('jenis', 30)->default('JPRC')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE keanggotaan_jawatankuasa MODIFY COLUMN jenis VARCHAR(30) NOT NULL DEFAULT 'JPRC'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('keanggotaan_jawatankuasa', function (Blueprint $table) {
                $table->enum('jenis', ['JPRC', 'JPRD', 'AJK_CABANG', 'WANITA', 'AMK'])->change();
            });

            return;
        }

        DB::statement("ALTER TABLE keanggotaan_jawatankuasa MODIFY COLUMN jenis ENUM('JPRC','JPRD','AJK_CABANG','WANITA','AMK') NOT NULL");
    }
};
