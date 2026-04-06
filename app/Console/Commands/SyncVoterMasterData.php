<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVoterUpload;
use App\Models\UploadBatch;
use Illuminate\Console\Command;

class SyncVoterMasterData extends Command
{
    protected $signature = 'voter:sync-master-data {--batch= : Specific batch ID (default: active batch)}';
    protected $description = 'Sync master data (Negeri, Parlimen, KADUN, DM, Lokaliti) from voter database';

    public function handle(): int
    {
        $batchId = $this->option('batch');

        if ($batchId) {
            $batch = UploadBatch::find($batchId);
        } else {
            $batch = UploadBatch::where('is_active', true)->first();
        }

        if (!$batch) {
            $this->error('No batch found.');
            return 1;
        }

        $this->info("Syncing master data from batch: {$batch->nama_fail} (ID: {$batch->id})...");
        ProcessVoterUpload::syncMasterData($batch->id);
        $this->info('Master data synced successfully.');

        return 0;
    }
}
