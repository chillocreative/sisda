<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVoterUpload;
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

        ProcessVoterUpload::dispatch($batch->id, $zipPath);

        return redirect()->route('upload-database.index')
            ->with('success', 'Fail ZIP berjaya dimuat naik. Pemprosesan sedang berjalan di latar belakang.');
    }

    public function restore(UploadBatch $batch)
    {
        UploadBatch::where('id', '!=', $batch->id)->update(['is_active' => false]);
        $batch->update(['is_active' => true]);

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
        if (!$activeBatch) return response()->json([]);

        $voters = PangkalanDataPengundi::where('upload_batch_id', $activeBatch->id)
            ->where('no_ic', 'like', $query . '%')
            ->limit(8)
            ->get(['no_ic', 'nama', 'lokaliti', 'daerah_mengundi', 'kadun', 'parlimen', 'negeri', 'bangsa']);

        return response()->json($voters);
    }

    public function searchByIc(Request $request)
    {
        $activeBatch = UploadBatch::where('is_active', true)->first();

        if (!$activeBatch) {
            return response()->json(null);
        }

        $voter = PangkalanDataPengundi::where('upload_batch_id', $activeBatch->id)
            ->where('no_ic', $request->ic)
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
