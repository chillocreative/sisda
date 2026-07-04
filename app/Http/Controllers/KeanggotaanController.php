<?php

namespace App\Http\Controllers;

use App\Imports\KeanggotaanImport;
use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Models\KeanggotaanSetting;
use App\Services\Keanggotaan\MemberMatchService;
use App\Services\Keanggotaan\MemberWingService;
use App\Support\Pdf;
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

    /** Per-import state: members merged by IC across sheets/files, and a skip tally. */
    private array $merged = [];

    private array $importTally = ['kept' => 0, 'skipped_no_ic' => 0, 'duplicates' => 0];

    /** All members are visible — visibility is not gated by batch active state. */
    private function memberQuery()
    {
        return Keanggotaan::query();
    }

    /** Distinct Cabang (from the uploaded file) that members belong to. */
    private function parlimenList(): array
    {
        return Keanggotaan::whereNotNull('cabang')->where('cabang', '!=', '')
            ->distinct()->orderBy('cabang')->pluck('cabang')->all();
    }

    /**
     * DUNs from the voter-roll match (matched_kadun) plus the file-derived branch
     * DUN. When a Parlimen (Cabang) is selected, only DUNs under that Parlimen
     * are listed (matched DUNs registered in it + that Cabang's branch DUN).
     */
    private function dunList(Request $request): array
    {
        $parlimen = $request->input('parlimen');

        $matched = Keanggotaan::whereNotNull('matched_kadun')->where('matched_kadun', '!=', '')
            ->when($parlimen, fn ($q, $p) => $q->where('cabang', $p)->whereRaw('UPPER(matched_parlimen) = ?', [strtoupper($p)]))
            ->distinct()->pluck('matched_kadun');
        $branch = Keanggotaan::whereNotNull('dun')->where('dun', '!=', '')
            ->when($parlimen, fn ($q, $p) => $q->where('cabang', $p))
            ->distinct()->pluck('dun');

        return $matched->merge($branch)->unique()->sort()->values()->all();
    }

    private function bangsaList(): array
    {
        return Keanggotaan::whereNotNull('bangsa')->where('bangsa', '!=', '')
            ->distinct()->orderBy('bangsa')->pluck('bangsa')->all();
    }

    /** Data Induk (master data) map: UPPER(Parlimen/Bandar name) => Negeri name. */
    private function negeriByParlimenMap(): array
    {
        return Bandar::join('negeri', 'negeri.id', '=', 'bandar.negeri_id')
            ->get(['bandar.nama as parlimen', 'negeri.nama as negeri'])
            ->reduce(function ($map, $r) {
                $map[mb_strtoupper($r->parlimen)] = $r->negeri;

                return $map;
            }, []);
    }

    /** Data Induk (master data) map: UPPER(DUN/Kadun name) => Parlimen (Bandar) name. */
    private function parlimenByDunMap(): array
    {
        return Kadun::join('bandar', 'bandar.id', '=', 'kadun.bandar_id')
            ->get(['kadun.nama as dun', 'bandar.nama as parlimen'])
            ->reduce(function ($map, $r) {
                $map[mb_strtoupper($r->dun)] = $r->parlimen;

                return $map;
            }, []);
    }

    /** Daerah Mengundi (from the DPPR match), cascaded to the selected Parlimen/DUN. */
    private function dmList(Request $request): array
    {
        return Keanggotaan::whereNotNull('matched_daerah_mengundi')->where('matched_daerah_mengundi', '!=', '')
            ->when($request->input('parlimen'), fn ($q, $p) => $q->where('cabang', $p))
            ->when($request->input('dun'), fn ($q, $d) => $q->where(fn ($x) => $x->where('matched_kadun', $d)->orWhere('dun', $d)))
            ->distinct()->orderBy('matched_daerah_mengundi')->pluck('matched_daerah_mengundi')->all();
    }

    /** Lokaliti (from the DPPR match), cascaded to the selected Parlimen/DUN/Daerah Mengundi. */
    private function lokalitiList(Request $request): array
    {
        return Keanggotaan::whereNotNull('matched_lokaliti')->where('matched_lokaliti', '!=', '')
            ->when($request->input('parlimen'), fn ($q, $p) => $q->where('cabang', $p))
            ->when($request->input('dun'), fn ($q, $d) => $q->where(fn ($x) => $x->where('matched_kadun', $d)->orWhere('dun', $d)))
            ->when($request->input('daerah_mengundi'), fn ($q, $dm) => $q->where('matched_daerah_mengundi', $dm))
            ->distinct()->orderBy('matched_lokaliti')->pluck('matched_lokaliti')->all();
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
            // Read everything straight from the file. The DPT/DPPR cross-check is
            // NOT run here — it's a separate step via "Sync Semula".
            $this->processFile($batch->id, Storage::disk('private')->path($path), $ext);
            // Branch Cabang/DUN live only in the file name (files carry no such
            // column), so derive them and apply as defaults for members that
            // lack their own.
            $labels = KeanggotaanImport::labelsFromFilename($originalName);
            $this->flushMembers($batch->id, $labels['cabang'], $labels['dun']);

            $count = Keanggotaan::where('batch_id', $batch->id)->count();
            $batch->update(['jumlah_rekod' => $count, 'status' => 'completed', 'is_active' => true]);

            $message = number_format($count).' ahli berjaya dimuat naik.';
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
     * Read every worksheet (members may be split across tabs with different
     * layouts) and merge rows by IC so the richest record wins.
     */
    private function importExcel(int $batchId, string $path): void
    {
        foreach (Excel::toArray(null, $path) as $sheet) {
            $this->mergeMembers(KeanggotaanImport::extract($sheet, $this->importTally));
        }
    }

    /**
     * Merge parsed rows into the per-batch set keyed by IC: first occurrence
     * wins for each field, later sheets fill in any blanks (e.g. cabang/negeri
     * that only one layout carries).
     *
     * @param  array<int, array<string, mixed>>  $members
     */
    private function mergeMembers(array $members): void
    {
        foreach ($members as $m) {
            $ic = $m['no_ic'];
            if (! isset($this->merged[$ic])) {
                $this->merged[$ic] = $m;

                continue;
            }
            $this->importTally['duplicates']++;
            foreach ($m as $k => $v) {
                if (($this->merged[$ic][$k] ?? null) === null && $v !== null && $v !== '') {
                    $this->merged[$ic][$k] = $v;
                }
            }
        }
    }

    /**
     * Insert the merged members for this batch. Fields come straight from the
     * file; umur is derived from the IC; status_kawasan stays blank until a
     * DPT/DPPR sync is run.
     */
    private function flushMembers(int $batchId, ?string $defaultCabang = null, ?string $defaultDun = null): void
    {
        $records = [];
        foreach ($this->merged as $m) {
            $records[] = [
                'batch_id' => $batchId,
                'no_anggota' => $m['no_anggota'] ?? null,
                'no_ic' => $m['no_ic'],
                'nama' => $m['nama'] ?: '-',
                'no_tel' => $m['no_tel'] ?? null,
                'jantina' => $m['jantina'] ?? MemberMatchService::jantinaFromIc($m['no_ic']),
                'bangsa' => $m['bangsa'] ?? null,
                'cabang' => $m['cabang'] ?? $defaultCabang,
                'dun' => $defaultDun,
                'negeri' => $m['negeri'] ?? null,
                'alamat' => $m['alamat'] ?? null,
                'umur' => MemberMatchService::ageFromIc($m['no_ic']),
                'status_kawasan' => null,
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
        $this->mergeMembers($members);
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
        $this->matcher->syncTable('keanggotaan', keepFileFields: true);

        return redirect()->route('keanggotaan.index')
            ->with('success', "{$count} batch keanggotaan telah ".($isActive ? 'diaktifkan' : 'dinyahaktifkan').'.');
    }

    /**
     * Flag a batch as EKYC-verified. Ticking EKYC automatically marks every
     * member in the batch as an active anggota; unticking only clears the flag
     * (member statuses are left as-is).
     */
    public function setEkyc(Request $request, KeanggotaanBatch $batch)
    {
        $validated = $request->validate(['is_ekyc' => 'required|boolean']);

        $batch->update(['is_ekyc' => $validated['is_ekyc']]);

        if ($validated['is_ekyc']) {
            Keanggotaan::where('batch_id', $batch->id)->update(['status_anggota' => 'aktif']);
        }

        return redirect()->back()->with('success', $validated['is_ekyc']
            ? "Batch ditanda EKYC — semua ahli ditetapkan sebagai Aktif."
            : 'Tanda EKYC dibuang daripada batch.');
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
        $this->applyMemberFilters($query, $request);

        $setting = KeanggotaanSetting::current();
        $year = (int) date('Y');

        // EKYC status is a property of the member's upload batch.
        $members = $query->with('batch:id,is_ekyc')->orderByDesc('id')->paginate(25)->withQueryString();
        $members->through(fn ($m) => $this->attachWings($m, $setting, $year));

        return Inertia::render('Keanggotaan/Senarai', [
            'members' => $members,
            'filters' => $request->only(['search', 'status_kawasan', 'parlimen', 'dun', 'daerah_mengundi', 'lokaliti', 'bangsa', 'jantina', 'status_anggota', 'sentimen', 'sayap']),
            'parlimenList' => $this->parlimenList(),
            'dunList' => $this->dunList($request),
            'dmList' => $this->dmList($request),
            'lokalitiList' => $this->lokalitiList($request),
            'bangsaList' => $this->bangsaList(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /** Apply the full search + geographic + demographic filter set. */
    private function applyMemberFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")->orWhere('no_ic', 'like', "%{$search}%");
            });
        }
        // Geographic drill: Parlimen (Cabang) > DUN > Daerah Mengundi > Lokaliti.
        if ($parlimen = $request->input('parlimen')) {
            $query->where('cabang', $parlimen);
        }
        if ($dun = $request->input('dun')) {
            $query->where(fn ($q) => $q->where('matched_kadun', $dun)->orWhere('dun', $dun));
        }
        if ($dm = $request->input('daerah_mengundi')) {
            $query->where('matched_daerah_mengundi', $dm);
        }
        if ($lokaliti = $request->input('lokaliti')) {
            $query->where('matched_lokaliti', $lokaliti);
        }

        $this->applyNonGeoFilters($query, $request);
    }

    /**
     * Non-geographic filters (kawasan status, bangsa, jantina, status anggota,
     * sentimen, sayap). Split out so the Analisa page can apply them to its
     * Cabang-level scope without the DUN/DM/Lokaliti drill.
     */
    private function applyNonGeoFilters($query, Request $request): void
    {
        if (in_array($request->input('status_kawasan'), ['dalam_kawasan', 'luar_kawasan', 'tiada_dppr'], true)) {
            $query->where('status_kawasan', $request->input('status_kawasan'));
        }
        if ($bangsa = $request->input('bangsa')) {
            $query->where('bangsa', $bangsa);
        }
        if (in_array($request->input('jantina'), ['LELAKI', 'PEREMPUAN'], true)) {
            $query->whereRaw('UPPER(jantina) = ?', [$request->input('jantina')]);
        }
        if (in_array($request->input('status_anggota'), ['aktif', 'tidak_aktif'], true)) {
            $query->where('status_anggota', $request->input('status_anggota'));
        }

        // Sentimen = latest voter colour (or "belum dicula" when none yet).
        $sentimen = $request->input('sentimen');
        if (in_array($sentimen, ['putih', 'kelabu', 'hitam'], true)) {
            $query->where('voter_color', $sentimen);
        } elseif ($sentimen === 'belum_dicula') {
            $query->where(fn ($q) => $q->whereNull('voter_color')->orWhere('voter_color', ''));
        }

        // Sayap is derived live from jantina + umur + term — translate the same
        // rule to SQL so the filter works across paginated results.
        $sayap = $request->input('sayap');
        if (in_array($sayap, ['AMK', 'Srikandi', 'Wanita'], true)) {
            $setting = KeanggotaanSetting::current();
            $year = (int) date('Y');
            $within = MemberWingService::withinTerm($setting->tahun_mula, $setting->tahun_tamat, $year);
            $youthMax = $within ? MemberWingService::MAX_AGE + ($year - $setting->tahun_mula) : MemberWingService::MAX_AGE;
            if ($sayap === 'Wanita') {
                $query->whereRaw('UPPER(jantina) = ?', ['PEREMPUAN']);
            } else {
                $query->whereRaw('UPPER(jantina) = ?', [$sayap === 'AMK' ? 'LELAKI' : 'PEREMPUAN'])
                    ->whereNotNull('umur')->where('umur', '<=', $youthMax);
            }
        }
    }

    private function attachWings($m, $setting, int $year)
    {
        $wing = MemberWingService::classify($m->umur, $m->jantina, $setting->tahun_mula, $setting->tahun_tamat, $year);
        $m->wings = $wing['wings'];
        $m->grace_wings = $wing['graceWings'];
        $m->wing_grace = $wing['grace'];

        return $m;
    }

    /** Download the filtered member list as a professional PDF. */
    public function senaraiExport(Request $request)
    {
        $query = $this->memberQuery();
        $this->applyMemberFilters($query, $request);

        $setting = KeanggotaanSetting::current();
        $year = (int) date('Y');
        $members = $query->orderByDesc('id')->limit(10000)->get()->map(fn ($m) => $this->attachWings($m, $setting, $year));

        $wingColors = ['AMK' => '#2563eb', 'Srikandi' => '#db2777', 'Wanita' => '#9333ea'];
        $sentimen = ['putih' => ['Putih', '#10b981'], 'kelabu' => ['Kelabu', '#94a3b8'], 'hitam' => ['Hitam', '#0f172a']];

        $rows = $members->map(fn ($m) => [
            $m->no_anggota ?: '-',
            $m->nama,
            $m->no_ic,
            $m->umur ?? '-',
            $m->jantina ?: '-',
            $m->bangsa ?: '-',
            $m->cabang ?: '-',
            $m->matched_kadun ?: ($m->dun ?: '-'),
            $m->matched_daerah_mengundi ?: '-',
            $m->matched_lokaliti ?: '-',
            collect($m->wings)->map(fn ($w) => ['text' => $w, 'color' => $wingColors[$w] ?? '#64748b'])->all(),
            $m->status_kawasan === 'dalam_kawasan' ? [['text' => 'Dalam Kawasan', 'color' => '#10b981']]
                : ($m->status_kawasan === 'tiada_dppr' ? [['text' => 'Tiada DPPR/DPT', 'color' => '#ef4444']]
                : ($m->status_kawasan === 'luar_kawasan' ? [['text' => 'Luar', 'color' => '#f59e0b']] : [])),
            isset($sentimen[$m->voter_color]) ? [['text' => $sentimen[$m->voter_color][0], 'color' => $sentimen[$m->voter_color][1]]] : [],
        ])->all();

        $filters = [];
        if ($v = $request->input('search')) {
            $filters[] = ['label' => 'Carian', 'value' => $v];
        }
        if ($v = $request->input('parlimen')) {
            $filters[] = ['label' => 'Parlimen', 'value' => $v];
        }
        if ($v = $request->input('status_kawasan')) {
            $filters[] = ['label' => 'Status Kawasan', 'value' => match($v) {
                'dalam_kawasan' => 'Pengundi Dalam Kawasan',
                'luar_kawasan'  => 'Pengundi Luar',
                'tiada_dppr'    => 'Tiada dalam DPPR/DPT',
                default         => $v,
            }];
        }
        if ($v = $request->input('dun')) {
            $filters[] = ['label' => 'DUN', 'value' => $v];
        }
        if ($v = $request->input('daerah_mengundi')) {
            $filters[] = ['label' => 'Daerah Mengundi', 'value' => $v];
        }
        if ($v = $request->input('lokaliti')) {
            $filters[] = ['label' => 'Lokaliti', 'value' => $v];
        }
        if ($v = $request->input('bangsa')) {
            $filters[] = ['label' => 'Bangsa', 'value' => $v];
        }
        if ($v = $request->input('jantina')) {
            $filters[] = ['label' => 'Jantina', 'value' => ucfirst(strtolower($v))];
        }
        if ($v = $request->input('status_anggota')) {
            $filters[] = ['label' => 'Status Anggota', 'value' => $v === 'aktif' ? 'Aktif' : 'Tidak Aktif'];
        }
        if ($v = $request->input('sentimen')) {
            $filters[] = ['label' => 'Sentimen', 'value' => ucfirst(str_replace('_', ' ', $v))];
        }
        if ($v = $request->input('sayap')) {
            $filters[] = ['label' => 'Sayap', 'value' => $v];
        }

        return Pdf::download('pdf.senarai', [
            'title' => 'Senarai Ahli Keanggotaan',
            'filters' => $filters,
            'columns' => ['No. Anggota', 'Nama', 'No. IC', 'Umur', 'Jantina', 'Bangsa', 'Cabang', 'DUN', 'Daerah Mengundi', 'Lokaliti', 'Sayap', 'Status Pengundi', 'Sentimen'],
            'rows' => $rows,
            'total' => count($rows),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ], 'senarai-ahli-keanggotaan-'.now()->format('Y-m-d').'.pdf', 'a4', 'landscape');
    }

    public function memberStore(Request $request)
    {
        $validated = $request->validate([
            'no_ic'                    => 'required|string|max:12',
            'nama'                     => 'required|string|max:255',
            'no_tel'                   => 'nullable|string|max:30',
            'status_anggota'           => 'nullable|in:aktif,tidak_aktif',
            'daftar_tanpa_pengetahuan' => 'boolean',
        ]);

        $validated['no_tel'] = $this->normalizePhone($validated['no_tel'] ?? null);

        $member = new Keanggotaan($validated);
        $member->fill($this->matcher->match($validated['no_ic']));

        if (in_array(auth()->user()->role, ['super_admin', 'admin'])) {
            $admin = $request->validate([
                'no_anggota'     => 'nullable|string|max:50',
                'alamat'         => 'nullable|string|max:500',
                'bangsa'         => 'nullable|string|max:50',
                'jantina'        => 'nullable|in:LELAKI,PEREMPUAN',
                'cabang'         => 'nullable|string|max:100',
                'negeri'         => 'nullable|string|max:100',
                'voter_color'    => 'nullable|in:putih,kelabu,hitam',
                'status_kawasan' => 'nullable|in:dalam_kawasan,luar_kawasan,tiada_dppr',
            ]);
            $member->fill(array_filter($admin, fn ($v) => $v !== null));
        }

        $member->save();

        return redirect()->back()->with('success', 'Ahli berjaya ditambah.');
    }

    public function memberUpdate(Request $request, Keanggotaan $member)
    {
        $validated = $request->validate([
            'no_ic'                    => 'required|string|max:12',
            'nama'                     => 'required|string|max:255',
            'no_tel'                   => 'nullable|string|max:30',
            'status_anggota'           => 'nullable|in:aktif,tidak_aktif',
            'daftar_tanpa_pengetahuan' => 'boolean',
        ]);

        $validated['no_tel'] = $this->normalizePhone($validated['no_tel'] ?? null);

        $member->fill($validated);
        $member->fill($this->matcher->match($validated['no_ic']));

        if (in_array(auth()->user()->role, ['super_admin', 'admin'])) {
            $admin = $request->validate([
                'no_anggota'     => 'nullable|string|max:50',
                'alamat'         => 'nullable|string|max:500',
                'bangsa'         => 'nullable|string|max:50',
                'jantina'        => 'nullable|in:LELAKI,PEREMPUAN',
                'cabang'         => 'nullable|string|max:100',
                'negeri'         => 'nullable|string|max:100',
                'voter_color'    => 'nullable|in:putih,kelabu,hitam',
                'status_kawasan' => 'nullable|in:dalam_kawasan,luar_kawasan,tiada_dppr',
            ]);
            $member->fill(array_filter($admin, fn ($v) => $v !== null));
        }

        $member->save();

        return redirect()->back()->with('success', 'Ahli berjaya dikemaskini.');
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return $phone;
        }
        if (! preg_match('/^[0+]/', $phone)) {
            return '0'.$phone;
        }

        return $phone;
    }

    public function memberDestroy(Keanggotaan $member)
    {
        $member->delete();

        return redirect()->back()->with('success', 'Ahli berjaya dipadam.');
    }

    public function resync()
    {
        $this->matcher->syncTable('keanggotaan', keepFileFields: true);

        return redirect()->back()->with('success', 'Padanan keanggotaan dengan SISDA telah disegerakkan semula.');
    }

    /* ----------------------------- Analisa ----------------------------- */

    public function analisa(Request $request)
    {
        // Auto-cross-check freshly-uploaded members against the DPT/DPPR roll so
        // kawasan/DUN/dicula fill in without a manual "Sync Semula". Only runs
        // while there's unsynced data (status_kawasan still blank); file fields
        // (cabang/negeri/no_anggota/bangsa/jantina) are preserved.
        if (Keanggotaan::whereNull('status_kawasan')->exists()) {
            $this->matcher->syncTable('keanggotaan', keepFileFields: true);
        }

        $parlimen = $request->input('parlimen') ?: null;
        $dun = $request->input('dun') ?: null;
        // Main scope = the full filter set (geographic drill Parlimen > DUN >
        // Daerah Mengundi > Lokaliti, plus the demographic/kawasan/sentimen/
        // sayap filters). The KPI/age/jantina/wings/etc. cards drill with all.
        $base = fn () => tap($this->memberQuery(), fn ($q) => $this->applyMemberFilters($q, $request));
        // Parlimen-only scope for the DUN chart and the "luar" cards — they must
        // see the whole Cabang to list all DUNs / count members registered
        // outside the focused DUN, so they skip the DUN/DM/Lokaliti drill but
        // still honour the non-geographic filters.
        $parlimenBase = fn () => tap($this->memberQuery(), function ($q) use ($parlimen, $request) {
            if ($parlimen) {
                $q->where('cabang', $parlimen);
            }
            $this->applyNonGeoFilters($q, $request);
        });

        // DUNs available for the DUN dropdown: those within the selected Parlimen
        // (only populated once a Parlimen/Cabang is chosen).
        $dunList = $parlimen
            ? Keanggotaan::where('cabang', $parlimen)
                ->whereNotNull('matched_kadun')->where('matched_kadun', '!=', '')
                ->whereRaw('UPPER(matched_parlimen) = ?', [strtoupper($parlimen)])
                ->distinct()->orderBy('matched_kadun')->pluck('matched_kadun')->all()
            : [];

        // Daerah Mengundi + Lokaliti dropdowns, cascaded to the current selection.
        $dmList = $this->dmList($request);
        $lokalitiList = $this->lokalitiList($request);

        $total = $base()->count();
        // Kawasan (DPT/DPPR roll membership) is a Cabang-level property: a
        // "luar kawasan" member is not in the roll and therefore has no
        // matched_kadun, so scoping by DUN would always zero them out. Count
        // these against the whole Parlimen/Cabang so the cards stay meaningful
        // when a DUN is focused. (dicula/baru still drill down with the DUN.)
        $kawasanTotal = (clone $parlimenBase())->count();
        $dalam = (clone $parlimenBase())->where('status_kawasan', 'dalam_kawasan')->count();
        $luar = (clone $parlimenBase())->where('status_kawasan', 'luar_kawasan')->count();
        $tiadaDppr = (clone $parlimenBase())->where('status_kawasan', 'tiada_dppr')->count();
        $dicula = (clone $base())->where('is_dicula', true)->count();
        $baru = (clone $base())->where('is_pendaftaran_baru', true)->count();

        $ageBands = [];
        foreach (self::AGE_BANDS as $band) {
            $ageBands[] = [
                'band' => $band['label'],
                'jumlah' => (clone $base())->whereBetween('umur', [$band['min'], $band['max']])->count(),
            ];
        }

        // Data Induk (master data) lookups: derive Negeri from a member's
        // Parlimen (Bandar → Negeri), and the Parlimen a DUN belongs to.
        $negeriByParlimen = $this->negeriByParlimenMap();
        $parlimenByDun = $this->parlimenByDunMap();

        // File-based breakdowns (cabang / bangsa) plus a Negeri breakdown that
        // derives the state from Data Induk (Parlimen → Negeri), not the file.
        $rows = (clone $base())->get(['cabang', 'matched_parlimen', 'negeri', 'bangsa', 'is_dicula']);
        $cAgg = [];
        $nAgg = [];
        $bAgg = [];
        foreach ($rows as $r) {
            $c = ($r->cabang !== null && $r->cabang !== '') ? $r->cabang : 'Tiada Cabang';
            $cAgg[$c] ??= ['nama' => $c, 'jumlah' => 0, 'dicula' => 0];
            $cAgg[$c]['jumlah']++;
            if ($r->is_dicula) {
                $cAgg[$c]['dicula']++;
            }

            // Negeri from Data Induk via the member's Parlimen (branch Cabang first,
            // else the roll-matched Parlimen); fall back to the file's negeri.
            $n = $negeriByParlimen[mb_strtoupper((string) $r->cabang)]
                ?? $negeriByParlimen[mb_strtoupper((string) $r->matched_parlimen)]
                ?? (($r->negeri !== null && $r->negeri !== '') ? $r->negeri : 'Tiada Negeri');
            $nAgg[$n] ??= ['nama' => $n, 'jumlah' => 0];
            $nAgg[$n]['jumlah']++;

            $b = ($r->bangsa !== null && $r->bangsa !== '') ? $r->bangsa : 'Tidak Dinyatakan';
            $bAgg[$b] ??= ['nama' => $b, 'jumlah' => 0];
            $bAgg[$b]['jumlah']++;
        }
        $byParlimen = collect(array_values($cAgg))->sortByDesc('jumlah')->values();
        $byNegeri = collect(array_values($nAgg))->sortByDesc('jumlah')->values();
        $byBangsa = collect(array_values($bAgg))->sortByDesc('jumlah')->values();

        // DUN comes from the voter-roll match. When a Cabang is selected, keep
        // only DUNs in that Cabang's Parlimen (members registered elsewhere are
        // surfaced via the "luar parlimen" card below, not this chart).
        $byDun = (clone $parlimenBase())->whereNotNull('matched_kadun')->where('matched_kadun', '!=', '')
            ->when($parlimen, fn ($q) => $q->whereRaw('UPPER(matched_parlimen) = ?', [strtoupper($parlimen)]))
            ->selectRaw('matched_kadun AS nama, COUNT(*) AS jumlah')
            ->groupBy('matched_kadun')->orderByDesc('jumlah')->get();

        // Members per Daerah Mengundi within the selected Parlimen/DUN (excludes
        // the DM/Lokaliti drill so the whole list shows, honours non-geo filters).
        $byDm = (clone $parlimenBase())
            ->when($dun, fn ($q) => $q->where(fn ($x) => $x->where('matched_kadun', $dun)->orWhere('dun', $dun)))
            ->whereNotNull('matched_daerah_mengundi')->where('matched_daerah_mengundi', '!=', '')
            ->selectRaw('matched_daerah_mengundi AS nama, COUNT(*) AS jumlah')
            ->groupBy('matched_daerah_mengundi')->orderByDesc('jumlah')->get();

        // Members in the roll but registered to vote in a different Parlimen than
        // their party Cabang (Cabang scope — independent of the focused DUN). The
        // registered Parlimen comes from the roll; when the roll left it blank it
        // is derived from the roll DUN via Data Induk (Kadun → Bandar).
        $luarParlimen = (clone $parlimenBase())
            ->get(['cabang', 'matched_parlimen', 'matched_kadun'])
            ->filter(function ($r) use ($parlimenByDun) {
                $cabang = mb_strtoupper((string) $r->cabang);
                if ($cabang === '') {
                    return false;
                }
                $reg = ($r->matched_parlimen !== null && $r->matched_parlimen !== '')
                    ? $r->matched_parlimen
                    : ($parlimenByDun[mb_strtoupper((string) $r->matched_kadun)] ?? null);

                return $reg !== null && $reg !== '' && mb_strtoupper($reg) !== $cabang;
            })->count();

        // When a DUN is focused: members of this Cabang registered to vote in a
        // DUN other than the selected one (parallel to the "luar parlimen" card).
        $luarDun = $dun
            ? (clone $parlimenBase())->whereNotNull('matched_kadun')->where('matched_kadun', '!=', '')
                ->where('matched_kadun', '!=', $dun)->count()
            : 0;

        $byColor = (clone $base())->selectRaw("COALESCE(NULLIF(voter_color, ''), 'belum_dicula') AS voter_color, COUNT(*) AS jumlah")
            ->groupBy('voter_color')->get();

        // Jantina straight from the file (IC fallback at import). Follows the
        // main Parlimen > DUN scope.
        $jantinaRaw = (clone $base())
            ->selectRaw("COALESCE(NULLIF(jantina, ''), 'TIDAK DIKETAHUI') AS jantina, COUNT(*) AS jumlah")
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
                'kawasan_total' => $kawasanTotal,
                'dalam_kawasan' => $dalam,
                'luar_kawasan' => $luar,
                'tiada_dppr' => $tiadaDppr,
                'belum_sync' => $kawasanTotal - $dalam - $luar - $tiadaDppr,
                'dicula' => $dicula,
                'pendaftaran_baru' => $baru,
                'luar_parlimen' => $luarParlimen,
                'luar_dun' => $luarDun,
            ],
            'ageBands' => $ageBands,
            'byParlimen' => $byParlimen,
            'byNegeri' => $byNegeri,
            'byBangsa' => $byBangsa,
            'byDun' => $byDun,
            'byDm' => $byDm,
            'byColor' => $byColor,
            'byJantina' => $byJantina,
            'wings' => $wings,
            'parlimenList' => $this->parlimenList(),
            'dunList' => $dunList,
            'dmList' => $dmList,
            'lokalitiList' => $lokalitiList,
            'bangsaList' => $this->bangsaList(),
            'filters' => $request->only(['parlimen', 'dun', 'daerah_mengundi', 'lokaliti', 'status_kawasan', 'bangsa', 'jantina', 'status_anggota', 'sentimen', 'sayap']),
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

        $rows = $base->select('umur', 'jantina', 'cabang')->get();
        foreach ($rows as $r) {
            $wing = MemberWingService::classify($r->umur, $r->jantina, $setting->tahun_mula, $setting->tahun_tamat, $year);
            if ($wing['wings'] === []) {
                continue;
            }
            $cabang = $r->cabang ?: 'Tiada Cabang';
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
