<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVoterUpload;
use App\Models\Lokaliti;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class UploadDatabaseController extends Controller
{
    public function index()
    {
        $batches = UploadBatch::with('uploader')
            ->orderByDesc('created_at')
            ->paginate(10);

        return Inertia::render('UploadDatabase/Index', [
            'batches' => $batches,
            'flash'   => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fail' => 'required|file|mimes:zip|max:102400',
        ]);

        $file = $request->file('fail');
        $timestamp = now()->format('YmdHis');
        $originalName = $file->getClientOriginalName();
        $zipPath = $file->storeAs('voter-uploads', "{$timestamp}_{$originalName}", 'private');

        $batch = UploadBatch::create([
            'nama_fail'    => $originalName,
            'fail_path'    => $zipPath,
            'jumlah_rekod' => 0,
            'status'       => 'processing',
            'is_active'    => false,
            'uploaded_by'  => auth()->id(),
        ]);

        set_time_limit(0);
        ProcessVoterUpload::dispatchSync($batch->id, $zipPath);

        return redirect()->route('upload-database.index')
            ->with('success', 'Fail ZIP berjaya dimuat naik dan diproses.');
    }

    public function restore(UploadBatch $batch)
    {
        UploadBatch::where('id', '!=', $batch->id)->update(['is_active' => false]);
        $batch->update(['is_active' => true]);

        // Sync master data tables from voter database
        \App\Jobs\ProcessVoterUpload::syncMasterData($batch->id);

        return redirect()->route('upload-database.index')
            ->with('success', "Batch '{$batch->nama_fail}' telah dijadikan aktif.");
    }

    public function destroy(UploadBatch $batch)
    {
        // Delete stored zip file
        if ($batch->fail_path && Storage::disk('private')->exists($batch->fail_path)) {
            Storage::disk('private')->delete($batch->fail_path);
        }

        // Delete temp directory if it exists
        $tempDir = Storage::disk('private')->path("voter-uploads/temp_{$batch->id}");
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }

        // Cascade delete handles pangkalan_data_pengundi records
        $batch->delete();

        return redirect()->route('upload-database.index')
            ->with('success', 'Rekod berjaya dipadam.');
    }

    public function suggestIc(Request $request)
    {
        $query = $request->input('ic', '');
        if (strlen($query) < 3) return response()->json([]);

        $activeBatch = UploadBatch::where('is_active', true)->first();

        // Search in active batch OR DPT records
        $voters = PangkalanDataPengundi::where(function ($q) use ($activeBatch) {
                if ($activeBatch) {
                    $q->where('upload_batch_id', $activeBatch->id)
                      ->orWhereNotNull('dpt_upload_id');
                } else {
                    $q->whereNotNull('dpt_upload_id');
                }
            })
            ->where('no_ic', 'like', $query . '%')
            ->limit(8)
            ->get(['no_ic', 'nama', 'lokaliti', 'daerah_mengundi', 'kadun', 'parlimen', 'negeri', 'bangsa']);

        return response()->json($voters);
    }

    public function searchByIc(Request $request)
    {
        $ic = $request->ic;
        $activeBatch = UploadBatch::where('is_active', true)->first();

        // Search exact match first, then try with 0000 suffix for DPT records
        $voter = PangkalanDataPengundi::where(function ($q) use ($activeBatch) {
                if ($activeBatch) {
                    $q->where('upload_batch_id', $activeBatch->id)
                      ->orWhereNotNull('dpt_upload_id');
                } else {
                    $q->whereNotNull('dpt_upload_id');
                }
            })
            ->where(function ($q) use ($ic) {
                $q->where('no_ic', $ic);
                // Also match 8-digit input with 0000 suffix (DPT format)
                if (strlen($ic) === 12 && substr($ic, -4) === '0000') {
                    $q->orWhere('no_ic', $ic);
                } elseif (strlen($ic) <= 8) {
                    $q->orWhere('no_ic', $ic . '0000');
                }
            })
            ->first();

        return response()->json($voter);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
