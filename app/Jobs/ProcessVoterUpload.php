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
use Smalot\PdfParser\Parser;
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
        $absPath = Storage::disk('private')->path($this->zipPath);
        $ext = strtolower(pathinfo($this->zipPath, PATHINFO_EXTENSION));

        // The upload may be a ZIP of files, or a single spreadsheet/PDF. Read
        // whichever it is — the importer is column-agnostic.
        if ($ext === 'zip') {
            $this->importZip($absPath);
        } elseif ($ext === 'pdf') {
            $this->importPdf($absPath);
        } else {
            Excel::import(new VoterDatabaseImport($this->batchId), $absPath);
        }

        $totalRecords = PangkalanDataPengundi::where('upload_batch_id', $this->batchId)->count();
        // Additive activation: multiple batches can be active at once
        // (e.g. one roll per parliament), so completing an upload no
        // longer deactivates the others.
        $batch->update([
            'jumlah_rekod' => $totalRecords,
            'status'       => 'completed',
            'is_active'    => true,
        ]);
        \Illuminate\Support\Facades\Cache::forget('pilihanraya:active_batches');

        // Sync master data tables from the voter database. This is best-effort
        // enrichment — the voter rows are already imported and active, so a sync
        // hiccup must not mark the whole upload as failed.
        try {
            self::syncMasterData($this->batchId);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Sync master data gagal untuk batch {$this->batchId}: ".$e->getMessage());
        }
    }

    /**
     * Extract a ZIP (any folder structure) and import every spreadsheet/PDF
     * inside it. The column-agnostic importer handles varied layouts.
     */
    private function importZip(string $zipFilePath): void
    {
        $tempDir = Storage::disk('private')->path("voter-uploads/temp_{$this->batchId}");

        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new \Exception('Tidak dapat membuka fail ZIP.');
        }

        // Guard against ZIP slip (path traversal in entry names)
        for ($i = 0; $i < $zip->count(); $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) continue;
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                $zip->close();
                throw new \Exception('ZIP fail mengandungi laluan tidak sah.');
            }
        }
        $zip->extractTo($tempDir);
        $zip->close();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) continue;
            // Skip macOS metadata duplicates that some zip tools include.
            if (str_starts_with($file->getFilename(), '._')) continue;
            $e = strtolower($file->getExtension());
            if (in_array($e, ['xlsx', 'xls', 'csv'], true)) {
                Excel::import(new VoterDatabaseImport($this->batchId), $file->getPathname());
            } elseif ($e === 'pdf') {
                $this->importPdf($file->getPathname());
            }
        }

        $this->deleteDirectory($tempDir);
    }

    /**
     * Best-effort PDF extraction: pull every MyKad-shaped IC and the trailing
     * text as the name. PDFs have no reliable column structure, so only IC +
     * name are captured; spreadsheets remain the complete format.
     */
    private function importPdf(string $pdfPath): void
    {
        $text = (new Parser)->parseFile($pdfPath)->getText();
        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (! preg_match('/(\d{6}[\s-]?\d{2}[\s-]?\d{4})(.*)/', trim($line), $m)) {
                continue;
            }
            $ic = VoterDatabaseImport::normaliseIc($m[1], true);
            if ($ic === null) {
                continue;
            }
            $nama = strtoupper(trim(preg_replace('/[^\p{L}\s]+/u', ' ', $m[2])));
            $records[] = [
                'upload_batch_id' => $this->batchId,
                'no_ic' => $ic,
                'nama' => preg_replace('/\s+/', ' ', $nama),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($records) >= 500) {
                PangkalanDataPengundi::insert($records);
                $records = [];
            }
        }
        if (! empty($records)) {
            PangkalanDataPengundi::insert($records);
        }
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
            // Creating a new master row: bail if a required parent FK is
            // unresolved (null) — the *_id columns are NOT NULL foreign keys, so
            // inserting null throws. Skipping avoids the crash and orphan rows;
            // the row can sync later once its parent exists.
            foreach ($extraAttrs as $k => $v) {
                if (str_ends_with($k, '_id') && $v === null) {
                    return null;
                }
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
