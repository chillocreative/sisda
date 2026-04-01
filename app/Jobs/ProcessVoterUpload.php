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

        // Sync unique lokaliti values to master table with daerah_mengundi linkage
        $batchLokaliti = PangkalanDataPengundi::where('upload_batch_id', $this->batchId)
            ->whereNotNull('lokaliti')
            ->where('lokaliti', '!=', '')
            ->select('lokaliti', 'daerah_mengundi', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('lokaliti', 'daerah_mengundi')
            ->orderByDesc('cnt')
            ->get();

        // Build map: lokaliti_name => daerah_mengundi_name (most common)
        $lokalitiToDm = [];
        foreach ($batchLokaliti as $row) {
            if (!isset($lokalitiToDm[$row->lokaliti]) && $row->daerah_mengundi) {
                $lokalitiToDm[$row->lokaliti] = $row->daerah_mengundi;
            }
        }

        $dmRecords = \App\Models\DaerahMengundi::pluck('id', 'nama');
        $existingNames = Lokaliti::pluck('nama')
            ->map(fn($n) => strtoupper(trim($n)))
            ->toArray();

        $newNames = array_values(array_diff(array_keys($lokalitiToDm), $existingNames));

        if (!empty($newNames)) {
            $now = now();
            Lokaliti::insert(array_map(fn($nama) => [
                'nama'              => $nama,
                'daerah_mengundi_id' => $dmRecords[$lokalitiToDm[$nama]] ?? null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ], $newNames));
        }

        // Update existing lokaliti records that have no daerah_mengundi_id
        foreach ($lokalitiToDm as $lokalitiName => $dmName) {
            $dmId = $dmRecords[$dmName] ?? null;
            if ($dmId) {
                Lokaliti::where('nama', $lokalitiName)
                    ->whereNull('daerah_mengundi_id')
                    ->update(['daerah_mengundi_id' => $dmId]);
            }
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
