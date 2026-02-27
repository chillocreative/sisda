<?php

namespace App\Jobs;

use App\Imports\VoterDatabaseImport;
use App\Models\Lokaliti;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class ProcessVoterUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        private int $batchId,
        private string $zipPath,
    ) {}

    public function handle(): void
    {
        $batch = UploadBatch::findOrFail($this->batchId);
        $tempDir = Storage::disk('private')->path("voter-uploads/temp_{$this->batchId}");
        $zipFilePath = Storage::disk('private')->path($this->zipPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new \Exception('Tidak dapat membuka fail ZIP.');
        }
        $zip->extractTo($tempDir);
        $zip->close();

        $xlsxFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xlsx') {
                $parentDir = strtoupper(basename(dirname($file->getPathname())));
                if ($parentDir === 'LOCALITIES') {
                    $xlsxFiles[] = $file->getPathname();
                }
            }
        }

        foreach ($xlsxFiles as $xlsxPath) {
            Excel::import(new VoterDatabaseImport($this->batchId), $xlsxPath);
        }

        $totalRecords = PangkalanDataPengundi::where('upload_batch_id', $this->batchId)->count();
        UploadBatch::where('id', '!=', $this->batchId)->update(['is_active' => false]);
        $batch->update([
            'jumlah_rekod' => $totalRecords,
            'status'       => 'completed',
            'is_active'    => true,
        ]);

        $this->deleteDirectory($tempDir);

        // Sync unique lokaliti values to master table
        $batchLokaliti = PangkalanDataPengundi::where('upload_batch_id', $this->batchId)
            ->whereNotNull('lokaliti')
            ->where('lokaliti', '!=', '')
            ->distinct()
            ->pluck('lokaliti')
            ->toArray();

        $existingNames = Lokaliti::pluck('nama')
            ->map(fn($n) => strtoupper(trim($n)))
            ->toArray();

        $newNames = array_values(array_diff($batchLokaliti, $existingNames));

        if (!empty($newNames)) {
            $now = now();
            Lokaliti::insert(array_map(fn($nama) => [
                'nama'       => $nama,
                'created_at' => $now,
                'updated_at' => $now,
            ], $newNames));
        }
    }

    public function failed(Throwable $exception): void
    {
        UploadBatch::where('id', $this->batchId)->update(['status' => 'failed']);
        $tempDir = Storage::disk('private')->path("voter-uploads/temp_{$this->batchId}");
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
