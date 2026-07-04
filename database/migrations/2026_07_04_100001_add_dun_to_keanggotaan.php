<?php

use App\Imports\KeanggotaanImport;
use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Party branch DUN, read from the upload's file name (files carry no DUN
        // column and unmatched members have no voter-roll matched_kadun). Kept
        // separate from matched_kadun, which is where a member is registered to
        // vote and is reset on every SISDA sync.
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->string('dun')->nullable()->after('cabang');
        });

        // Backfill existing uploads from their file name so branches uploaded
        // before this change surface in the Parlimen/DUN dropdowns.
        foreach (KeanggotaanBatch::all() as $batch) {
            $labels = KeanggotaanImport::labelsFromFilename($batch->nama_fail);

            if ($labels['dun'] !== null) {
                Keanggotaan::where('batch_id', $batch->id)->update(['dun' => $labels['dun']]);
            }
            if ($labels['cabang'] !== null) {
                // Only fill where the file had no cabang column — never clobber file values.
                Keanggotaan::where('batch_id', $batch->id)
                    ->where(fn ($q) => $q->whereNull('cabang')->orWhere('cabang', ''))
                    ->update(['cabang' => $labels['cabang']]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('keanggotaan', function (Blueprint $table) {
            $table->dropColumn('dun');
        });
    }
};
