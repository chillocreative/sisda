<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVoterUpload;
use App\Models\DataPengundi;
use App\Models\Lokaliti;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use App\Services\VoterDataMasker;
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

        // Run the import after the HTTP response is sent so the upload returns
        // immediately. A long-running synchronous dispatch trips Cloudflare's
        // 100s proxy timeout (524) for sizeable zips. The UI polls batch
        // status every 5s and surfaces completion/failure from there.
        set_time_limit(0);
        ProcessVoterUpload::dispatchAfterResponse($batch->id, $zipPath);

        return redirect()->route('upload-database.index')
            ->with('success', 'Fail ZIP dimuat naik. Pemprosesan sedang berjalan di latar belakang.');
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

        $viewer = auth()->user();

        // Non-super_admin viewers can only see records inside their own parlimen.
        $parlimenScope = null;
        if (! $viewer->isSuperAdmin()) {
            $parlimenScope = $viewer->bandar?->nama;
            if (! $parlimenScope) {
                return response()->json([]);
            }
        }

        // Data Pengundi matches (previously submitted records) - full fields for form auto-fill
        $dataPengundiQuery = DataPengundi::with('submittedBy:id,name,role')
            ->where('no_ic', 'like', $query . '%');
        if ($parlimenScope !== null) {
            $dataPengundiQuery->whereRaw('UPPER(bandar) = ?', [strtoupper($parlimenScope)]);
        }
        $dataPengundi = $dataPengundiQuery
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($v) use ($viewer) {
                $locked = VoterDataMasker::isLocked($v) && ! VoterDataMasker::canUnmask($viewer);
                $arr = [
                    'id' => $v->id,
                    'no_ic' => $locked ? VoterDataMasker::MASK : $v->no_ic,
                    'nama' => $v->nama,
                    'umur' => $locked ? VoterDataMasker::MASK : $v->umur,
                    'no_tel' => $locked ? VoterDataMasker::MASK : $v->no_tel,
                    'bangsa' => $locked ? VoterDataMasker::MASK : $v->bangsa,
                    'alamat' => $locked ? VoterDataMasker::MASK : $v->alamat,
                    'poskod' => $locked ? VoterDataMasker::MASK : $v->poskod,
                    'negeri' => $locked ? VoterDataMasker::MASK : $v->negeri,
                    'bandar' => $locked ? VoterDataMasker::MASK : $v->bandar,
                    'parlimen' => $v->parlimen,
                    'kadun' => $v->kadun,
                    'mpkk' => $v->mpkk,
                    'daerah_mengundi' => $v->daerah_mengundi,
                    'lokaliti' => $v->lokaliti,
                    'keahlian_parti' => $v->keahlian_parti,
                    'kecenderungan_politik' => $v->kecenderungan_politik,
                    'status_pengundi' => $v->status_pengundi,
                    'source' => 'data_pengundi',
                    'is_locked' => $locked,
                ];
                return $arr;
            });

        // DPPR matches, excluding IC numbers already present in Data Pengundi
        $existingIcs = DataPengundi::where('no_ic', 'like', $query . '%')->pluck('no_ic')->unique()->toArray();
        $dpprQuery = PangkalanDataPengundi::where('no_ic', 'like', $query . '%');
        if ($parlimenScope !== null) {
            $dpprQuery->whereRaw('UPPER(parlimen) = ?', [strtoupper($parlimenScope)]);
        }
        if (count($existingIcs) > 0) {
            $dpprQuery->whereNotIn('no_ic', $existingIcs);
        }
        $dppr = $dpprQuery
            ->limit(10)
            ->get(['no_ic', 'nama', 'lokaliti', 'daerah_mengundi', 'kadun', 'parlimen', 'negeri', 'bangsa'])
            ->map(function ($v) {
                $arr = $v->toArray();
                $arr['source'] = 'dppr';
                $arr['is_locked'] = false;
                return $arr;
            });

        return response()->json($dataPengundi->concat($dppr)->values());
    }

    public function searchByIc(Request $request)
    {
        $ic = $request->ic;
        $viewer = auth()->user();

        // Non-super_admin viewers can only see records inside their own parlimen.
        $parlimenScope = null;
        if (! $viewer->isSuperAdmin()) {
            $parlimenScope = $viewer->bandar?->nama;
            if (! $parlimenScope) {
                return response()->json(null);
            }
        }
        $upperScope = $parlimenScope !== null ? strtoupper($parlimenScope) : null;

        // For 12-digit IC, check data_pengundi first, then DPPR fallback
        if (strlen($ic) === 12) {
            $dpQuery = DataPengundi::with('submittedBy:id,name,role')->where('no_ic', $ic);
            if ($upperScope !== null) {
                $dpQuery->whereRaw('UPPER(bandar) = ?', [$upperScope]);
            }
            $voter = $dpQuery->first();

            if ($voter) {
                $locked = VoterDataMasker::isLocked($voter) && ! VoterDataMasker::canUnmask($viewer);
                $arr = $voter->only([
                    'id', 'no_ic', 'nama', 'umur', 'no_tel', 'bangsa', 'alamat', 'poskod',
                    'negeri', 'bandar', 'parlimen', 'kadun', 'mpkk', 'daerah_mengundi', 'lokaliti',
                    'keahlian_parti', 'kecenderungan_politik', 'status_pengundi',
                ]);
                if ($locked) {
                    foreach (VoterDataMasker::SENSITIVE_FIELDS as $field) {
                        if (array_key_exists($field, $arr)) {
                            $arr[$field] = VoterDataMasker::MASK;
                        }
                    }
                }
                $arr['source'] = 'data_pengundi';
                $arr['is_locked'] = $locked;
                return response()->json($arr);
            }

            $dpprQuery = PangkalanDataPengundi::where('no_ic', $ic);
            if ($upperScope !== null) {
                $dpprQuery->whereRaw('UPPER(parlimen) = ?', [$upperScope]);
            }
            $dpprVoter = $dpprQuery->first();
            return response()->json($dpprVoter);
        }

        // For partial IC (6-11 digits): check data_pengundi first, then DPPR
        $partialDpQuery = DataPengundi::with('submittedBy:id,name,role')->where('no_ic', 'like', $ic . '%');
        if ($upperScope !== null) {
            $partialDpQuery->whereRaw('UPPER(bandar) = ?', [$upperScope]);
        }
        $voters = $partialDpQuery
            ->limit(15)
            ->get()
            ->map(function ($v) use ($viewer) {
                $locked = VoterDataMasker::isLocked($v) && ! VoterDataMasker::canUnmask($viewer);
                return [
                    'no_ic' => $locked ? VoterDataMasker::MASK : $v->no_ic,
                    'nama' => $v->nama,
                    'lokaliti' => $v->lokaliti,
                    'daerah_mengundi' => $v->daerah_mengundi,
                    'kadun' => $v->kadun,
                    'parlimen' => $v->parlimen,
                    'negeri' => $locked ? VoterDataMasker::MASK : $v->negeri,
                    'bangsa' => $locked ? VoterDataMasker::MASK : $v->bangsa,
                    'source' => 'data_pengundi',
                    'is_locked' => $locked,
                ];
            });

        if ($voters->isEmpty()) {
            $partialDpprQuery = PangkalanDataPengundi::where('no_ic', 'like', $ic . '%');
            if ($upperScope !== null) {
                $partialDpprQuery->whereRaw('UPPER(parlimen) = ?', [$upperScope]);
            }
            $voters = $partialDpprQuery
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
