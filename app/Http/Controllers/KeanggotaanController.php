<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessKeanggotaanUpload;
use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Services\Keanggotaan\MemberMatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Keanggotaan (party membership) — bulk upload, manual CRUD and analysis.
 * Every route is super_admin-only (enforced by the route group), mirroring
 * the voter-database upload module.
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

    /** Members in active batches plus all manually-entered members. */
    private function memberQuery()
    {
        $activeIds = KeanggotaanBatch::activeIds();

        return Keanggotaan::where(function ($q) use ($activeIds) {
            $q->whereNull('batch_id');
            if ($activeIds !== []) {
                $q->orWhereIn('batch_id', $activeIds);
            }
        });
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

        $batch = KeanggotaanBatch::create([
            'nama_fail' => $originalName,
            'fail_path' => $path,
            'jumlah_rekod' => 0,
            'status' => 'processing',
            'is_active' => false,
            'uploaded_by' => auth()->id(),
        ]);

        set_time_limit(0);
        ProcessKeanggotaanUpload::dispatchAfterResponse($batch->id, $path);

        return redirect()->route('keanggotaan.index')
            ->with('success', 'Fail keanggotaan dimuat naik. Pemprosesan & padanan sedang berjalan di latar belakang.');
    }

    public function setActive(Request $request)
    {
        $validated = $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'integer|exists:keanggotaan_batches,id',
            'action' => 'required|in:activate,deactivate',
        ]);

        if ($validated['action'] === 'activate') {
            $count = KeanggotaanBatch::whereIn('id', $validated['batch_ids'])
                ->where('status', 'completed')
                ->update(['is_active' => true]);
            $message = "{$count} batch keanggotaan telah diaktifkan.";
        } else {
            $count = KeanggotaanBatch::whereIn('id', $validated['batch_ids'])
                ->update(['is_active' => false]);
            $message = "{$count} batch keanggotaan telah dinyahaktifkan.";
        }

        return redirect()->route('keanggotaan.index')->with('success', $message);
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

        return Inertia::render('Keanggotaan/Senarai', [
            'members' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'status_kawasan']),
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

    public function analisa()
    {
        $base = $this->memberQuery();

        $total = (clone $base)->count();
        $dalam = (clone $base)->where('status_kawasan', 'dalam_kawasan')->count();
        $dicula = (clone $base)->where('is_dicula', true)->count();
        $baru = (clone $base)->where('is_pendaftaran_baru', true)->count();

        $ageBands = [];
        foreach (self::AGE_BANDS as $band) {
            $ageBands[] = [
                'band' => $band['label'],
                'jumlah' => (clone $base)->whereBetween('umur', [$band['min'], $band['max']])->count(),
            ];
        }

        $byNegeri = (clone $base)->whereNotNull('matched_negeri')->where('matched_negeri', '!=', '')
            ->selectRaw('matched_negeri AS nama, COUNT(*) AS jumlah')
            ->groupBy('matched_negeri')->orderByDesc('jumlah')->get();

        $byDun = (clone $base)->whereNotNull('matched_kadun')->where('matched_kadun', '!=', '')
            ->selectRaw('matched_kadun AS nama, COUNT(*) AS jumlah')
            ->groupBy('matched_kadun')->orderByDesc('jumlah')->limit(30)->get();

        $byColor = (clone $base)->selectRaw("COALESCE(NULLIF(voter_color, ''), 'belum_dicula') AS voter_color, COUNT(*) AS jumlah")
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
            'byNegeri' => $byNegeri,
            'byDun' => $byDun,
            'byColor' => $byColor,
        ]);
    }
}
