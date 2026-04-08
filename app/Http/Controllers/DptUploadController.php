<?php

namespace App\Http\Controllers;

use App\Models\DptUpload;
use App\Services\DptParserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DptUploadController extends Controller
{
    public function index()
    {
        $uploads = DptUpload::with('uploader:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('DptUpload/Index', [
            'uploads' => $uploads,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:51200', // 50MB max
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $path = $file->store('dpt-uploads', 'local');

        $upload = DptUpload::create([
            'filename' => $filename,
            'label' => 'Memproses...',
            'status' => 'processing',
            'uploaded_by' => auth()->id(),
        ]);

        try {
            $result = DptParserService::parse(storage_path('app/' . $path), $upload);

            $header = $result['header'];
            $stats = $result['stats'];

            $label = 'DPT';
            if (!empty($header['bulan']) && !empty($header['tahun'])) {
                $label .= ' ' . $header['bulan'] . ' ' . $header['tahun'];
            }
            if (!empty($header['tarikh_warta'])) {
                $label .= ' (warta - ' . $header['tarikh_warta'] . ')';
            }

            $upload->update([
                'label' => $label,
                'parlimen' => $header['parlimen'] ?? null,
                'negeri' => $header['negeri'] ?? null,
                'bulan' => $header['bulan'] ?? null,
                'tahun' => $header['tahun'] ?? null,
                'tarikh_warta' => $header['tarikh_warta'] ?? null,
                'total_records' => $stats['total'],
                'total_new' => $stats['new'],
                'total_deceased' => $stats['deceased'],
                'total_moved' => $stats['moved'],
                'status' => 'completed',
            ]);

            return redirect()->back()->with('success', "Berjaya! {$stats['total']} rekod diproses ({$stats['new']} baru, {$stats['deceased']} kematian, {$stats['moved']} bertukar alamat).");
        } catch (\Exception $e) {
            $upload->update([
                'label' => $filename,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Gagal memproses PDF: ' . $e->getMessage());
        }
    }

    public function destroy(DptUpload $dptUpload)
    {
        $dptUpload->delete();
        return redirect()->back()->with('success', 'Rekod berjaya dipadam.');
    }
}
