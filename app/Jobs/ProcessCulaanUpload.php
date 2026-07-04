<?php

namespace App\Jobs;

use App\Imports\CulaanSentimentImport;
use App\Models\CulaanUpload;
use App\Services\CulaanMatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessCulaanUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(private int $uploadId) {}

    public function handle(): void
    {
        $upload = CulaanUpload::findOrFail($this->uploadId);
        $absPath = Storage::disk('private')->path($upload->fail_path);

        $rows = (new CulaanSentimentImport)->read($absPath);
        $report = (new CulaanMatchService)->run($rows, $upload->uploaded_by, false);

        $upload->update([
            'status' => 'completed',
            'jumlah_baris' => $report['jumlah_baris'] ?? 0,
            'matched' => $report['matched'] ?? 0,
            'dicipta' => $report['dicipta'] ?? 0,
            'dikemaskini' => $report['dikemaskini'] ?? 0,
            'tidak_dijumpai' => $report['tidak_dijumpai'] ?? 0,
            'taksah' => $report['taksah'] ?? 0,
            'tiada_sentimen' => $report['tiada_sentimen'] ?? 0,
            'report' => $report,
        ]);
    }

    public function failed(Throwable $e): void
    {
        CulaanUpload::where('id', $this->uploadId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
