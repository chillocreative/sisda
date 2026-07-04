<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCulaanUpload;
use App\Models\CulaanUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UploadCulaanController extends Controller
{
    public function index()
    {
        $uploads = CulaanUpload::with('uploader')
            ->orderByDesc('created_at')
            ->paginate(10);

        return Inertia::render('UploadCulaan/Index', [
            'uploads' => $uploads,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $request->validate([
            'fail' => 'required|file|mimes:csv,txt|max:51200',
        ]);

        $file = $request->file('fail');
        $hash = hash_file('sha256', $file->getRealPath());
        $timestamp = now()->format('YmdHis');
        $originalName = $file->getClientOriginalName();
        $path = $file->storeAs('culaan-uploads', "{$timestamp}_{$originalName}", 'private');

        $upload = CulaanUpload::create([
            'nama_fail' => $originalName,
            'fail_path' => $path,
            'file_hash' => $hash,
            'status' => 'processing',
            'uploaded_by' => auth()->id(),
        ]);

        // Match in the background so the upload returns immediately (Cloudflare
        // 100s proxy limit). The UI polls status every 5s.
        set_time_limit(0);
        ProcessCulaanUpload::dispatchAfterResponse($upload->id);

        return redirect()->route('upload-culaan.index')
            ->with('success', 'Fail CSV culaan dimuat naik. Pemprosesan sedang berjalan di latar belakang.');
    }

    public function destroy(CulaanUpload $culaanUpload)
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($culaanUpload->fail_path) {
            Storage::disk('private')->delete($culaanUpload->fail_path);
        }
        $culaanUpload->delete();

        return redirect()->route('upload-culaan.index')
            ->with('success', 'Rekod muat naik culaan dipadam. (Kecenderungan yang telah ditetapkan tidak diundurkan.)');
    }
}
