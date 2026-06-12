<?php

namespace App\Console\Commands;

use App\Jobs\ProcessVoterUpload;
use App\Models\UploadBatch;
use Illuminate\Console\Command;

class SyncVoterMasterData extends Command
{
    protected $signature = 'voter:sync-master-data {--batch= : Specific batch ID (default: all active batches)}';

    protected $description = 'Sync master data (Negeri, Parlimen, KADUN, DM, Lokaliti) from voter database';

    public function handle(): int
    {
        $batchId = $this->option('batch');

        $batches = $batchId
            ? UploadBatch::whereKey($batchId)->get()
            : UploadBatch::active()->get();

        if ($batches->isEmpty()) {
            $this->error('No batch found.');

            return 1;
        }

        foreach ($batches as $batch) {
            $this->info("Syncing master data from batch: {$batch->nama_fail} (ID: {$batch->id})...");
            ProcessVoterUpload::syncMasterData($batch->id);
        }
        $this->info('Master data synced successfully.');

        return 0;
    }
}
