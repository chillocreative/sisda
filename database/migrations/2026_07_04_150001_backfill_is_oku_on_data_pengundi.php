<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Persist the OKU flag for voters already identified via an OKU upload
        // batch, so the Data Pengundi edit tickbox reflects their OKU status.
        $okuBatchIds = DB::table('upload_batches')->where('is_oku', true)->pluck('id')->all();
        if ($okuBatchIds === []) {
            return;
        }

        DB::table('data_pengundi')
            ->whereIn('no_ic', function ($q) use ($okuBatchIds) {
                $q->select('no_ic')->from('pangkalan_data_pengundi')->whereIn('upload_batch_id', $okuBatchIds);
            })
            ->update(['is_oku' => true]);
    }

    public function down(): void
    {
        // Backfill only — cannot distinguish backfilled from manually-set flags.
    }
};
