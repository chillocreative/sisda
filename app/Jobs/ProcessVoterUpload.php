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

        // Sync master data tables from voter database
        self::syncMasterData($this->batchId);
    }

    /**
     * Sync master data tables (Negeri, Bandar/Parlimen, Kadun, DaerahMengundi, Lokaliti)
     * from the voter database records.
     */
    public static function syncMasterData(int $batchId): void
    {
        $now = now();

        // Helper: find or create/update with consistent naming from voter DB
        $findOrSync = function ($model, string $voterName, array $extraAttrs = [], string $kodField = null) {
            $existing = $model::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($voterName))])->first();
            if ($existing) {
                // Update name to match voter DB casing and fill missing parent links
                $updates = ['nama' => $voterName];
                foreach ($extraAttrs as $k => $v) {
                    if ($v !== null && (empty($existing->$k) || $existing->$k === null)) {
                        $updates[$k] = $v;
                    }
                }
                $existing->update($updates);
                return $existing;
            }
            $attrs = array_merge(['nama' => $voterName], $extraAttrs);
            if ($kodField) {
                $attrs[$kodField] = '';
            }
            return $model::create($attrs);
        };

        // 1. Sync Negeri
        $voterNegeri = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('negeri')->where('negeri', '!=', '')
            ->distinct()->pluck('negeri');
        foreach ($voterNegeri as $nama) {
            $findOrSync(\App\Models\Negeri::class, $nama);
        }

        // 2. Sync Bandar (Parlimen) - linked to Negeri
        $voterParlimen = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('parlimen')->where('parlimen', '!=', '')
            ->select('parlimen', 'negeri')
            ->distinct()->get();
        foreach ($voterParlimen as $row) {
            $negeri = \App\Models\Negeri::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($row->negeri ?? ''))])->first();
            $findOrSync(\App\Models\Bandar::class, $row->parlimen, ['negeri_id' => $negeri?->id]);
        }

        // 3. Sync Kadun - linked to Bandar (Parlimen)
        $voterKadun = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('kadun')->where('kadun', '!=', '')
            ->select('kadun', 'parlimen')
            ->distinct()->get();
        foreach ($voterKadun as $row) {
            $bandar = \App\Models\Bandar::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($row->parlimen ?? ''))])->first();
            $findOrSync(\App\Models\Kadun::class, $row->kadun, ['bandar_id' => $bandar?->id]);
        }

        // 4. Sync DaerahMengundi - linked to Bandar (Parlimen)
        $voterDM = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('daerah_mengundi')->where('daerah_mengundi', '!=', '')
            ->select('daerah_mengundi', 'parlimen')
            ->distinct()->get();
        foreach ($voterDM as $row) {
            $bandar = \App\Models\Bandar::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($row->parlimen ?? ''))])->first();
            $findOrSync(\App\Models\DaerahMengundi::class, $row->daerah_mengundi, ['bandar_id' => $bandar?->id], 'kod_dm');
        }

        // 5. Sync Lokaliti - linked to DaerahMengundi
        $voterLokaliti = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('lokaliti')->where('lokaliti', '!=', '')
            ->select('lokaliti', 'daerah_mengundi')
            ->distinct()->get();

        foreach ($voterLokaliti as $row) {
            $dm = \App\Models\DaerahMengundi::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($row->daerah_mengundi ?? ''))])->first();
            $findOrSync(Lokaliti::class, $row->lokaliti, ['daerah_mengundi_id' => $dm?->id]);
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
