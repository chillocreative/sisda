<?php

namespace App\Jobs;

use App\Imports\KeanggotaanImport;
use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Services\Keanggotaan\MemberMatchService;
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

/**
 * Background processor for a membership upload. Accepts a ZIP (of
 * spreadsheets), a single .xlsx/.csv, or a .pdf, inserts the member rows,
 * then runs the SISDA match in one set-based pass.
 */
class ProcessKeanggotaanUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        private int $batchId,
        private string $filePath,
    ) {}

    public function handle(MemberMatchService $matcher): void
    {
        $batch = KeanggotaanBatch::findOrFail($this->batchId);
        $absolute = Storage::disk('private')->path($this->filePath);
        $ext = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));

        if ($ext === 'zip') {
            $this->importZip($absolute);
        } elseif ($ext === 'pdf') {
            $this->importPdf($absolute);
        } else {
            Excel::import(new KeanggotaanImport($this->batchId), $absolute);
        }

        // Resolve kawasan / DUN / age / voter_color for the new rows.
        $matcher->syncTable('keanggotaan', $this->batchId);

        $batch->update([
            'jumlah_rekod' => Keanggotaan::where('batch_id', $this->batchId)->count(),
            'status' => 'completed',
            'is_active' => true,
        ]);
    }

    private function importZip(string $zipFilePath): void
    {
        $tempDir = Storage::disk('private')->path("keanggotaan-uploads/temp_{$this->batchId}");

        $zip = new \ZipArchive;
        if ($zip->open($zipFilePath) !== true) {
            throw new \Exception('Tidak dapat membuka fail ZIP.');
        }
        $zip->extractTo($tempDir);
        $zip->close();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if (str_starts_with($file->getFilename(), '._')) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                Excel::import(new KeanggotaanImport($this->batchId), $file->getPathname());
            } elseif ($ext === 'pdf') {
                $this->importPdf($file->getPathname());
            }
        }

        $this->deleteDirectory($tempDir);
    }

    /**
     * Best-effort PDF text extraction: pull every 12-digit IC and the text
     * that follows it on the same line as the member name.
     *
     * NOTE: membership PDFs have no standard layout (unlike the SPR DPT
     * roll). This handles the common "IC then name" line shape — supply a
     * sample PDF to harden the pattern. Excel remains the reliable format.
     */
    private function importPdf(string $pdfPath): void
    {
        $parser = new Parser;
        $text = $parser->parseFile($pdfPath)->getText();

        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (! preg_match('/(\d{12})\s*(.*)/', trim($line), $m)) {
                continue;
            }
            $ic = $m[1];
            $nama = strtoupper(trim(preg_replace('/\s+/', ' ', $m[2])));

            $records[] = [
                'batch_id' => $this->batchId,
                'no_ic' => $ic,
                'nama' => $nama ?: '-',
                'status_kawasan' => 'luar_kawasan',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($records) >= 500) {
                Keanggotaan::insert($records);
                $records = [];
            }
        }

        if (! empty($records)) {
            Keanggotaan::insert($records);
        }
    }

    public function failed(Throwable $exception): void
    {
        KeanggotaanBatch::where('id', $this->batchId)->update(['status' => 'failed']);
        $tempDir = Storage::disk('private')->path("keanggotaan-uploads/temp_{$this->batchId}");
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
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
