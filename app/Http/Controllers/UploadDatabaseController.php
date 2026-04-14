<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVoterUpload;
use App\Models\DataPengundi;
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

    public function cancel(UploadBatch $batch)
    {
        if ($batch->status === 'processing') {
            $batch->update(['status' => 'failed', 'nota' => 'Dibatalkan oleh pengguna']);
        }

        return redirect()->route('upload-database.index')
            ->with('success', 'Muat naik telah dibatalkan.');
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

        // Data Pengundi matches (previously submitted records) - full fields for form auto-fill
        $dataPengundi = DataPengundi::where('no_ic', 'like', $query . '%')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get([
                'id', 'no_ic', 'nama', 'umur', 'no_tel', 'bangsa', 'alamat', 'poskod',
                'negeri', 'bandar', 'parlimen', 'kadun', 'mpkk', 'daerah_mengundi', 'lokaliti',
                'keahlian_parti', 'kecenderungan_politik', 'status_pengundi',
            ])
            ->map(function ($v) {
                $arr = $v->toArray();
                $arr['source'] = 'data_pengundi';
                return $arr;
            });

        // DPPR matches, excluding IC numbers already present in Data Pengundi
        $existingIcs = $dataPengundi->pluck('no_ic')->unique()->toArray();
        $dpprQuery = PangkalanDataPengundi::where('no_ic', 'like', $query . '%');
        if (count($existingIcs) > 0) {
            $dpprQuery->whereNotIn('no_ic', $existingIcs);
        }
        $dppr = $dpprQuery
            ->limit(10)
            ->get(['no_ic', 'nama', 'lokaliti', 'daerah_mengundi', 'kadun', 'parlimen', 'negeri', 'bangsa'])
            ->map(function ($v) {
                $arr = $v->toArray();
                $arr['source'] = 'dppr';
                return $arr;
            });

        return response()->json($dataPengundi->concat($dppr)->values());
    }

    public function searchByIc(Request $request)
    {
        $ic = $request->ic;

        // For 12-digit IC, check data_pengundi first, then DPPR fallback
        if (strlen($ic) === 12) {
            $voter = DataPengundi::where('no_ic', $ic)
                ->select(['no_ic', 'nama', 'no_tel', 'alamat', 'poskod', 'lokaliti', 'daerah_mengundi', 'kadun', 'mpkk', 'parlimen', 'bandar', 'negeri', 'bangsa', 'keahlian_parti', 'kecenderungan_politik'])
                ->first();

            if (! $voter) {
                $voter = PangkalanDataPengundi::where('no_ic', $ic)->first();
            }

            return response()->json($voter);
        }

        // For partial IC (6-11 digits): check data_pengundi first, then DPPR
        $voters = DataPengundi::where('no_ic', 'like', $ic . '%')
            ->limit(15)
            ->get(['no_ic', 'nama', 'lokaliti', 'daerah_mengundi', 'kadun', 'parlimen', 'negeri', 'bangsa']);

        if ($voters->isEmpty()) {
            $voters = PangkalanDataPengundi::where('no_ic', 'like', $ic . '%')
                ->limit(15)
                ->get(['no_ic', 'nama', 'lokaliti', 'daerah_mengundi', 'kadun', 'parlimen', 'negeri', 'bangsa']);
        }

        // If only one match, return as single object (backward compatible)
        if ($voters->count() === 1) {
            return response()->json($voters->first());
        }

        // If multiple matches, return as array
        if ($voters->count() > 1) {
            return response()->json(['multiple' => true, 'voters' => $voters]);
        }

        return response()->json(null);
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
