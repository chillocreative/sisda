<?php

namespace App\Http\Controllers;

use App\Imports\KeanggotaanImport;
use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Models\KeanggotaanSetting;
use App\Services\Keanggotaan\MemberMatchService;
use App\Services\Keanggotaan\MemberWingService;
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

    /** Per-import state: ICs already inserted this batch, and a skip tally. */
    private array $seenIc = [];

    private array $importTally = ['kept' => 0, 'skipped_no_ic' => 0, 'duplicates' => 0];

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

            $message = number_format($count).' ahli berjaya dimuat naik & dipadankan dengan SISDA.';
            $notes = [];
            if ($this->importTally['skipped_no_ic'] > 0) {
                $notes[] = number_format($this->importTally['skipped_no_ic']).' baris dilangkau (IC tidak sah)';
            }
            if ($this->importTally['duplicates'] > 0) {
                $notes[] = number_format($this->importTally['duplicates']).' IC berganda diabaikan';
            }
            if ($notes !== []) {
                $message .= ' '.implode(', ', $notes).'.';
            }

            return redirect()->route('keanggotaan.senarai')->with('success', $message);
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
            $this->importExcel($batchId, $absolutePath);
        }
    }

    /**
     * Read every worksheet (members may be split across tabs, e.g. by race),
     * map columns by header and bulk insert. Duplicate ICs are dropped by
     * persistMembers() — workbooks often repeat the same people across tabs.
     */
    private function importExcel(int $batchId, string $path): void
    {
        foreach (Excel::toArray(null, $path) as $sheet) {
            $members = KeanggotaanImport::extract($sheet, $this->importTally);
            $this->persistMembers($batchId, $members);
        }
    }

    /**
     * Insert members, skipping any IC already seen this batch. Records are
     * [no_ic, nama, no_tel] maps; the cached match is filled later by syncTable.
     */
    private function persistMembers(int $batchId, array $members): void
    {
        $records = [];
        foreach ($members as $m) {
            if (isset($this->seenIc[$m['no_ic']])) {
                $this->importTally['duplicates']++;

                continue;
            }
            $this->seenIc[$m['no_ic']] = true;
            $records[] = $m + [
                'batch_id' => $batchId,
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
                $this->importExcel($batchId, $file->getPathname());
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
        $members = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            // Match an IC with or without dashes/spaces (e.g. 880515-01-5555).
            if (! preg_match('/(\d{6}[\s-]?\d{2}[\s-]?\d{4})(.*)/', trim($line), $m)) {
                continue;
            }
            $ic = KeanggotaanImport::normaliseIc($m[1]);
            if ($ic === null) {
                continue;
            }
            // Name = trailing text with any stray digits/punctuation stripped.
            $nama = strtoupper(trim(preg_replace('/[^\p{L}\s]+/u', ' ', $m[2])));
            $members[] = [
                'no_ic' => $ic,
                'nama' => preg_replace('/\s+/', ' ', $nama) ?: '-',
                'no_tel' => null,
            ];
        }
        $this->persistMembers($batchId, $members);
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

        $setting = KeanggotaanSetting::current();
        $year = (int) date('Y');

        $members = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $members->through(function ($m) use ($setting, $year) {
            $wing = MemberWingService::classify($m->umur, $m->jantina, $setting->tahun_mula, $setting->tahun_tamat, $year);
            $m->wings = $wing['wings'];
            $m->grace_wings = $wing['graceWings'];
            $m->wing_grace = $wing['grace'];

            return $m;
        });

        return Inertia::render('Keanggotaan/Senarai', [
            'members' => $members,
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

        // Jantina (cross-checked against the DPPR/DPT roll, IC fallback), respects the Parlimen filter.
        $jantinaRaw = (clone $base())->selectRaw("COALESCE(NULLIF(jantina, ''), 'TIDAK DIKETAHUI') AS jantina, COUNT(*) AS jumlah")
            ->groupBy('jantina')->pluck('jumlah', 'jantina');
        $byJantina = [
            'lelaki' => (int) ($jantinaRaw['LELAKI'] ?? 0),
            'perempuan' => (int) ($jantinaRaw['PEREMPUAN'] ?? 0),
            'tidak_diketahui' => (int) ($jantinaRaw['TIDAK DIKETAHUI'] ?? 0),
        ];

        $wings = $this->wingBreakdown($base());

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
            'byJantina' => $byJantina,
            'wings' => $wings,
            'parlimenList' => $this->parlimenList(),
            'filters' => ['parlimen' => $parlimen],
        ]);
    }

    /**
     * Aggregate AMK / Srikandi / Wanita counts (with grace sub-counts) and a
     * per-Cabang breakdown, classified live via MemberWingService.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $base
     */
    private function wingBreakdown($base): array
    {
        $setting = KeanggotaanSetting::current();
        $year = (int) date('Y');

        $labels = ['AMK', 'Srikandi', 'Wanita'];
        $totals = array_fill_keys($labels, 0);
        $grace = array_fill_keys($labels, 0);
        $byCabang = [];

        $rows = $base->select('umur', 'jantina', 'matched_parlimen')->get();
        foreach ($rows as $r) {
            $wing = MemberWingService::classify($r->umur, $r->jantina, $setting->tahun_mula, $setting->tahun_tamat, $year);
            if ($wing['wings'] === []) {
                continue;
            }
            $cabang = $r->matched_parlimen ?: 'Tiada Padanan';
            $graceWings = array_flip($wing['graceWings']);
            foreach ($wing['wings'] as $w) {
                $totals[$w]++;
                if (isset($graceWings[$w])) {
                    $grace[$w]++;
                }
                $byCabang[$cabang] ??= array_fill_keys($labels, 0) + ['nama' => $cabang];
                $byCabang[$cabang][$w]++;
            }
        }

        // Sort Cabang by member volume (AMK + Srikandi; Wanita == Srikandi here).
        $byCabang = array_values($byCabang);
        usort($byCabang, fn ($a, $b) => ($b['AMK'] + $b['Srikandi']) <=> ($a['AMK'] + $a['Srikandi']));

        return [
            'totals' => $totals,
            'grace' => $grace,
            'term' => ['tahun_mula' => $setting->tahun_mula, 'tahun_tamat' => $setting->tahun_tamat],
            'within_term' => MemberWingService::withinTerm($setting->tahun_mula, $setting->tahun_tamat, $year),
            'byCabang' => array_slice($byCabang, 0, 20),
        ];
    }

    /* ----------------------------- Tetapan ----------------------------- */

    public function tetapan()
    {
        $setting = KeanggotaanSetting::current();

        return Inertia::render('Keanggotaan/Tetapan', [
            'setting' => ['tahun_mula' => $setting->tahun_mula, 'tahun_tamat' => $setting->tahun_tamat],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function tetapanUpdate(Request $request)
    {
        $validated = $request->validate([
            'tahun_mula' => 'nullable|integer|min:2000|max:2100',
            'tahun_tamat' => 'nullable|integer|min:2000|max:2100|gte:tahun_mula',
        ]);

        KeanggotaanSetting::current()->update($validated);

        return redirect()->route('keanggotaan.tetapan')
            ->with('success', 'Penggal Pemilihan Parti berjaya dikemaskini.');
    }
}
