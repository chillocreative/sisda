<?php

namespace App\Http\Controllers;

use App\Imports\KeanggotaanImport;
use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Services\Keanggotaan\MemberMatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Smalot\PdfParser\Parser;

/**
 * Keanggotaan (party membership) — bulk upload, manual CRUD and analysis.
 * Every route is super_admin-only (enforced by the route group).
 *
 * Members are mapped to a Parlimen via their IC match against the active
 * voter roll (matched_parlimen), so the dashboard can be sliced per
 * Parlimen/Kawasan.
 */
class KeanggotaanController extends Controller
{
    /** Age bands shared with the war room analytics. */
    private const AGE_BANDS = [
        ['label' => '18-20', 'min' => 18, 'max' => 20],
        ['label' => '21-29', 'min' => 21, 'max' => 29],
        ['label' => '30-39', 'min' => 30, 'max' => 39],
        ['label' => '40-49', 'min' => 40, 'max' => 49],
        ['label' => '50-59', 'min' => 50, 'max' => 59],
        ['label' => '60-69', 'min' => 60, 'max' => 69],
        ['label' => '70+', 'min' => 70, 'max' => 200],
    ];

    public function __construct(protected MemberMatchService $matcher) {}

    /** All members are visible — visibility is not gated by batch active state. */
    private function memberQuery()
    {
        return Keanggotaan::query();
    }

    /** Distinct Parlimen (Cabang) that members have been matched to. */
    private function parlimenList(): array
    {
        return Keanggotaan::whereNotNull('matched_parlimen')->where('matched_parlimen', '!=', '')
            ->distinct()->orderBy('matched_parlimen')->pluck('matched_parlimen')->all();
    }

    public function index()
    {
        return Inertia::render('Keanggotaan/Index', [
            'batches' => KeanggotaanBatch::with('uploader')->orderByDesc('created_at')->paginate(10),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fail' => 'required|file|mimes:zip,xlsx,xls,csv,pdf|max:102400',
        ]);

        $file = $request->file('fail');
        $originalName = $file->getClientOriginalName();
        $path = $file->storeAs('keanggotaan-uploads', now()->format('YmdHis')."_{$originalName}", 'private');
        $ext = strtolower($file->getClientOriginalExtension());

        $batch = KeanggotaanBatch::create([
            'nama_fail' => $originalName,
            'fail_path' => $path,
            'jumlah_rekod' => 0,
            'status' => 'processing',
            'is_active' => false,
            'uploaded_by' => auth()->id(),
        ]);

        // Membership lists are small — process inline so the result (and
        // any parsing error) is known immediately, instead of relying on a
        // queue/after-response worker that may not run on every host.
        set_time_limit(0);
        try {
            $this->processFile($batch->id, Storage::disk('private')->path($path), $ext);
            $this->matcher->syncTable('keanggotaan', $batch->id);

            $count = Keanggotaan::where('batch_id', $batch->id)->count();
            $batch->update(['jumlah_rekod' => $count, 'status' => 'completed', 'is_active' => true]);

            return redirect()->route('keanggotaan.senarai')
                ->with('success', "{$count} ahli berjaya dimuat naik & dipadankan dengan SISDA.");
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed']);

            return redirect()->route('keanggotaan.index')
                ->with('error', 'Gagal memproses fail: '.$e->getMessage());
        }
    }

    private function processFile(int $batchId, string $absolutePath, string $ext): void
    {
        if ($ext === 'zip') {
            $this->importZip($batchId, $absolutePath);
        } elseif ($ext === 'pdf') {
            $this->importPdf($batchId, $absolutePath);
        } else {
            Excel::import(new KeanggotaanImport($batchId), $absolutePath);
        }
    }

    private function importZip(int $batchId, string $zipPath): void
    {
        $tempDir = Storage::disk('private')->path("keanggotaan-uploads/temp_{$batchId}");
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \Exception('Tidak dapat membuka fail ZIP.');
        }
        $zip->extractTo($tempDir);
        $zip->close();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || str_starts_with($file->getFilename(), '._')) {
                continue;
            }
            $e = strtolower($file->getExtension());
            if (in_array($e, ['xlsx', 'xls', 'csv'], true)) {
                Excel::import(new KeanggotaanImport($batchId), $file->getPathname());
            } elseif ($e === 'pdf') {
                $this->importPdf($batchId, $file->getPathname());
            }
        }

        $this->deleteDirectory($tempDir);
    }

    /**
     * Best-effort PDF extraction: pull every 12-digit IC and the trailing
     * text as the name. Membership PDFs have no standard layout — Excel is
     * the reliable format.
     */
    private function importPdf(int $batchId, string $pdfPath): void
    {
        $text = (new Parser)->parseFile($pdfPath)->getText();
        $records = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (! preg_match('/(\d{12})\s*(.*)/', trim($line), $m)) {
                continue;
            }
            $records[] = [
                'batch_id' => $batchId,
                'no_ic' => $m[1],
                'nama' => strtoupper(trim(preg_replace('/\s+/', ' ', $m[2]))) ?: '-',
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

    public function setActive(Request $request)
    {
        $validated = $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'integer|exists:keanggotaan_batches,id',
            'action' => 'required|in:activate,deactivate',
        ]);

        $isActive = $validated['action'] === 'activate';
        $count = KeanggotaanBatch::whereIn('id', $validated['batch_ids'])
            ->when($isActive, fn ($q) => $q->where('status', 'completed'))
            ->update(['is_active' => $isActive]);

        // Re-match everything — kawasan/DUN depend on which voter batches are active.
        $this->matcher->syncTable('keanggotaan');

        return redirect()->route('keanggotaan.index')
            ->with('success', "{$count} batch keanggotaan telah ".($isActive ? 'diaktifkan' : 'dinyahaktifkan').'.');
    }

    public function cancel(KeanggotaanBatch $batch)
    {
        if ($batch->status === 'processing') {
            $batch->update(['status' => 'failed']);
        }

        return redirect()->route('keanggotaan.index')->with('success', 'Muat naik telah dibatalkan.');
    }

    public function destroy(KeanggotaanBatch $batch)
    {
        if ($batch->fail_path && Storage::disk('private')->exists($batch->fail_path)) {
            Storage::disk('private')->delete($batch->fail_path);
        }
        $batch->delete();

        return redirect()->route('keanggotaan.index')->with('success', 'Batch keanggotaan berjaya dipadam.');
    }

    /* ----------------------------- Senarai ----------------------------- */

    public function senarai(Request $request)
    {
        $query = $this->memberQuery();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")->orWhere('no_ic', 'like', "%{$search}%");
            });
        }
        if (in_array($request->input('status_kawasan'), ['dalam_kawasan', 'luar_kawasan'], true)) {
            $query->where('status_kawasan', $request->input('status_kawasan'));
        }
        if ($parlimen = $request->input('parlimen')) {
            $query->where('matched_parlimen', $parlimen);
        }

        return Inertia::render('Keanggotaan/Senarai', [
            'members' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'status_kawasan', 'parlimen']),
            'parlimenList' => $this->parlimenList(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function memberStore(Request $request)
    {
        $validated = $request->validate([
            'no_ic' => 'required|string|max:12',
            'nama' => 'required|string|max:255',
            'no_tel' => 'nullable|string|max:30',
        ]);

        $member = new Keanggotaan($validated);
        $member->fill($this->matcher->match($validated['no_ic']));
        $member->save();

        return redirect()->back()->with('success', 'Ahli berjaya ditambah.');
    }

    public function memberUpdate(Request $request, Keanggotaan $member)
    {
        $validated = $request->validate([
            'no_ic' => 'required|string|max:12',
            'nama' => 'required|string|max:255',
            'no_tel' => 'nullable|string|max:30',
        ]);

        $member->fill($validated);
        $member->fill($this->matcher->match($validated['no_ic']));
        $member->save();

        return redirect()->back()->with('success', 'Ahli berjaya dikemaskini.');
    }

    public function memberDestroy(Keanggotaan $member)
    {
        $member->delete();

        return redirect()->back()->with('success', 'Ahli berjaya dipadam.');
    }

    public function resync()
    {
        $this->matcher->syncTable('keanggotaan');

        return redirect()->back()->with('success', 'Padanan keanggotaan dengan SISDA telah disegerakkan semula.');
    }

    /* ----------------------------- Analisa ----------------------------- */

    public function analisa(Request $request)
    {
        $parlimen = $request->input('parlimen') ?: null;
        $base = fn () => $this->memberQuery()->when($parlimen, fn ($q) => $q->where('matched_parlimen', $parlimen));

        $total = $base()->count();
        $dalam = (clone $base())->where('status_kawasan', 'dalam_kawasan')->count();
        $dicula = (clone $base())->where('is_dicula', true)->count();
        $baru = (clone $base())->where('is_pendaftaran_baru', true)->count();

        $ageBands = [];
        foreach (self::AGE_BANDS as $band) {
            $ageBands[] = [
                'band' => $band['label'],
                'jumlah' => (clone $base())->whereBetween('umur', [$band['min'], $band['max']])->count(),
            ];
        }

        $byParlimen = (clone $base())->whereNotNull('matched_parlimen')->where('matched_parlimen', '!=', '')
            ->selectRaw('matched_parlimen AS nama, COUNT(*) AS jumlah, SUM(is_dicula) AS dicula')
            ->groupBy('matched_parlimen')->orderByDesc('jumlah')->get();

        $byNegeri = (clone $base())->whereNotNull('matched_negeri')->where('matched_negeri', '!=', '')
            ->selectRaw('matched_negeri AS nama, COUNT(*) AS jumlah')
            ->groupBy('matched_negeri')->orderByDesc('jumlah')->get();

        $byDun = (clone $base())->whereNotNull('matched_kadun')->where('matched_kadun', '!=', '')
            ->selectRaw('matched_kadun AS nama, COUNT(*) AS jumlah')
            ->groupBy('matched_kadun')->orderByDesc('jumlah')->limit(30)->get();

        $byColor = (clone $base())->selectRaw("COALESCE(NULLIF(voter_color, ''), 'belum_dicula') AS voter_color, COUNT(*) AS jumlah")
            ->groupBy('voter_color')->get();

        return Inertia::render('Keanggotaan/Analisa', [
            'summary' => [
                'total' => $total,
                'dalam_kawasan' => $dalam,
                'luar_kawasan' => $total - $dalam,
                'dicula' => $dicula,
                'pendaftaran_baru' => $baru,
            ],
            'ageBands' => $ageBands,
            'byParlimen' => $byParlimen,
            'byNegeri' => $byNegeri,
            'byDun' => $byDun,
            'byColor' => $byColor,
            'parlimenList' => $this->parlimenList(),
            'filters' => ['parlimen' => $parlimen],
        ]);
    }
}
