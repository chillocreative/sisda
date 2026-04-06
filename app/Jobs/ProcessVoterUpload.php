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

        // 1. Sync Negeri
        $voterNegeri = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('negeri')->where('negeri', '!=', '')
            ->distinct()->pluck('negeri');
        $existingNegeri = \App\Models\Negeri::pluck('nama')->map(fn($n) => strtoupper(trim($n)))->toArray();
        foreach ($voterNegeri as $nama) {
            if (!in_array(strtoupper(trim($nama)), $existingNegeri)) {
                \App\Models\Negeri::create(['nama' => $nama]);
                $existingNegeri[] = strtoupper(trim($nama));
            }
        }

        // 2. Sync Bandar (Parlimen) - linked to Negeri
        $voterParlimen = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('parlimen')->where('parlimen', '!=', '')
            ->select('parlimen', 'negeri')
            ->distinct()->get();
        $existingBandar = \App\Models\Bandar::pluck('nama')->map(fn($n) => strtoupper(trim($n)))->toArray();
        $negeriMap = \App\Models\Negeri::pluck('id', 'nama');
        // Build case-insensitive negeri map
        $negeriMapLower = [];
        foreach ($negeriMap as $nama => $id) {
            $negeriMapLower[strtoupper(trim($nama))] = $id;
        }

        foreach ($voterParlimen as $row) {
            if (!in_array(strtoupper(trim($row->parlimen)), $existingBandar)) {
                $negeriId = $negeriMapLower[strtoupper(trim($row->negeri ?? ''))] ?? null;
                \App\Models\Bandar::create([
                    'nama' => $row->parlimen,
                    'negeri_id' => $negeriId,
                ]);
                $existingBandar[] = strtoupper(trim($row->parlimen));
            }
        }

        // 3. Sync Kadun - linked to Bandar (Parlimen)
        $voterKadun = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('kadun')->where('kadun', '!=', '')
            ->select('kadun', 'parlimen')
            ->distinct()->get();
        $existingKadun = \App\Models\Kadun::pluck('nama')->map(fn($n) => strtoupper(trim($n)))->toArray();
        $bandarMap = \App\Models\Bandar::pluck('id', 'nama');
        $bandarMapLower = [];
        foreach ($bandarMap as $nama => $id) {
            $bandarMapLower[strtoupper(trim($nama))] = $id;
        }

        foreach ($voterKadun as $row) {
            if (!in_array(strtoupper(trim($row->kadun)), $existingKadun)) {
                $bandarId = $bandarMapLower[strtoupper(trim($row->parlimen ?? ''))] ?? null;
                \App\Models\Kadun::create([
                    'nama' => $row->kadun,
                    'bandar_id' => $bandarId,
                ]);
                $existingKadun[] = strtoupper(trim($row->kadun));
            }
        }

        // 4. Sync DaerahMengundi - linked to Bandar (Parlimen)
        $voterDM = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('daerah_mengundi')->where('daerah_mengundi', '!=', '')
            ->select('daerah_mengundi', 'parlimen')
            ->distinct()->get();
        $existingDM = \App\Models\DaerahMengundi::pluck('nama')->map(fn($n) => strtoupper(trim($n)))->toArray();
        // Refresh bandar map after inserts
        $bandarMap = \App\Models\Bandar::pluck('id', 'nama');
        $bandarMapLower = [];
        foreach ($bandarMap as $nama => $id) {
            $bandarMapLower[strtoupper(trim($nama))] = $id;
        }

        foreach ($voterDM as $row) {
            if (!in_array(strtoupper(trim($row->daerah_mengundi)), $existingDM)) {
                $bandarId = $bandarMapLower[strtoupper(trim($row->parlimen ?? ''))] ?? null;
                \App\Models\DaerahMengundi::create([
                    'nama' => $row->daerah_mengundi,
                    'bandar_id' => $bandarId,
                ]);
                $existingDM[] = strtoupper(trim($row->daerah_mengundi));
            }
        }

        // 5. Sync Lokaliti - linked to DaerahMengundi
        $batchLokaliti = PangkalanDataPengundi::where('upload_batch_id', $batchId)
            ->whereNotNull('lokaliti')->where('lokaliti', '!=', '')
            ->select('lokaliti', 'daerah_mengundi', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('lokaliti', 'daerah_mengundi')
            ->orderByDesc('cnt')
            ->get();

        $lokalitiToDm = [];
        foreach ($batchLokaliti as $row) {
            if (!isset($lokalitiToDm[$row->lokaliti]) && $row->daerah_mengundi) {
                $lokalitiToDm[$row->lokaliti] = $row->daerah_mengundi;
            }
        }

        $dmRecords = \App\Models\DaerahMengundi::pluck('id', 'nama');
        // Build case-insensitive DM map
        $dmMapLower = [];
        foreach ($dmRecords as $nama => $id) {
            $dmMapLower[strtoupper(trim($nama))] = $id;
        }

        $existingLokaliti = Lokaliti::pluck('nama')->map(fn($n) => strtoupper(trim($n)))->toArray();
        $newNames = array_values(array_diff(array_keys($lokalitiToDm), $existingLokaliti));

        if (!empty($newNames)) {
            Lokaliti::insert(array_map(fn($nama) => [
                'nama'               => $nama,
                'daerah_mengundi_id' => $dmMapLower[strtoupper(trim($lokalitiToDm[$nama]))] ?? null,
                'created_at'         => $now,
                'updated_at'         => $now,
            ], $newNames));
        }

        // Update existing lokaliti records that have no daerah_mengundi_id
        foreach ($lokalitiToDm as $lokalitiName => $dmName) {
            $dmId = $dmMapLower[strtoupper(trim($dmName))] ?? null;
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
