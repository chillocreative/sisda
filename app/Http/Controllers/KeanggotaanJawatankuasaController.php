<?php

namespace App\Http\Controllers;

use App\Models\KeanggotaanJawatankuasa;
use App\Services\Keanggotaan\CommitteeImportMapper;
use App\Services\Keanggotaan\MemberMatchService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * JPRC / JPRD / AJK Cabang / Wanita / AMK committee tracking, under the
 * Pilihanraya menu. Cross-references members against canvass data to show
 * how many have been "dicula" (voter_color = hitam) and how the wings are
 * distributed across DUNs. super_admin-only (route group).
 */
class KeanggotaanJawatankuasaController extends Controller
{
    public function __construct(protected MemberMatchService $matcher) {}

    public function index(Request $request)
    {
        $dash = $this->dashboard();

        $query = KeanggotaanJawatankuasa::query();
        $this->applyCommitteeFilters($query, $request, $dash);

        // File order = insertion order = ascending id.
        $members = $query->orderBy('id')->paginate(25)->withQueryString();
        $members->getCollection()->transform(function ($m) {
            $m->dun = $m->dun ?: KeanggotaanJawatankuasa::extractDunFromJawatan($m->jawatan);

            return $m;
        });

        // Cascade the DUN dropdown to the selected Parlimen.
        if ($parlimen = $request->input('parlimen')) {
            $dash['dunOptions'] = $dash['dunsByParlimen'][mb_strtoupper($parlimen)] ?? [];
        }
        unset($dash['dunsByParlimen']);

        return Inertia::render('Pilihanraya/Jawatankuasa', array_merge([
            'members' => $members,
            'filters' => $request->only(['jenis', 'search', 'dun', 'parlimen']),
            'jenisOptions' => KeanggotaanJawatankuasa::JENIS,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ], $dash));
    }

    /** Apply the Jenis / Parlimen / search / DUN filters (shared by index + export). */
    private function applyCommitteeFilters($query, Request $request, array $dash): void
    {
        if (in_array($request->input('jenis'), KeanggotaanJawatankuasa::JENIS, true)) {
            $query->where('jenis', $request->input('jenis'));
        }
        // Parlimen = committee cabang, roll-matched parlimen, or a DUN that belongs
        // to that Parlimen (so committees that only carry a DUN still match).
        if ($parlimen = $request->input('parlimen')) {
            $pUpper = mb_strtoupper($parlimen);
            $duns = $dash['dunsByParlimen'][$pUpper] ?? [];
            $query->where(function ($q) use ($pUpper, $duns) {
                $q->whereRaw('UPPER(cabang) = ?', [$pUpper])
                    ->orWhereRaw('UPPER(matched_parlimen) = ?', [$pUpper]);
                foreach ($duns as $d) {
                    $q->orWhereRaw('UPPER(dun) = ?', [mb_strtoupper($d)])->orWhere('jawatan', 'like', "%{$d}%");
                }
            });
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")->orWhere('no_ic', 'like', "%{$search}%");
            });
        }
        // Match the stored DUN or a DUN still only embedded in the jawatan text.
        if ($dun = $request->input('dun')) {
            $query->where(function ($q) use ($dun) {
                $q->where('dun', $dun)->orWhere('jawatan', 'like', "%{$dun}%");
            });
        }
    }

    /** Download the filtered committee list as a professional PDF. */
    public function export(Request $request)
    {
        $dash = $this->dashboard();
        $query = KeanggotaanJawatankuasa::query();
        $this->applyCommitteeFilters($query, $request, $dash);

        $members = $query->orderBy('id')->get()->map(function ($m) {
            $m->dun = $m->dun ?: KeanggotaanJawatankuasa::extractDunFromJawatan($m->jawatan);

            return $m;
        });

        $jenisLabel = ['JPRC' => 'JPRC', 'JPRD' => 'JPRD', 'AJK_CABANG' => 'Cabang', 'WANITA' => 'Wanita', 'AMK' => 'AMK', 'MPKK' => 'MPKK', 'JBPP' => 'JBPP', 'JPWK' => 'JPWK'];
        $sentimen = ['putih' => ['Putih', '#10b981'], 'kelabu' => ['Kelabu', '#94a3b8'], 'hitam' => ['Hitam', '#0f172a']];

        $rows = $members->map(fn ($m) => [
            $m->nama,
            $m->no_ic ?: '-',
            $jenisLabel[$m->jenis] ?? $m->jenis,
            $m->jawatan ?: '-',
            $m->cabang ?: '-',
            $m->dun ?: '-',
            isset($sentimen[$m->voter_color])
                ? [['text' => $sentimen[$m->voter_color][0], 'color' => $sentimen[$m->voter_color][1]]]
                : ($m->is_dicula ? [['text' => 'Dicula', 'color' => '#ef4444']] : []),
        ])->all();

        $filters = [];
        if (($v = $request->input('jenis')) && isset($jenisLabel[$v])) {
            $filters[] = ['label' => 'Jenis', 'value' => $jenisLabel[$v]];
        }
        if ($v = $request->input('parlimen')) {
            $filters[] = ['label' => 'Parlimen', 'value' => $v];
        }
        if ($v = $request->input('dun')) {
            $filters[] = ['label' => 'DUN', 'value' => $v];
        }
        if ($v = $request->input('search')) {
            $filters[] = ['label' => 'Carian', 'value' => $v];
        }

        return Pdf::loadView('pdf.senarai', [
            'title' => 'Senarai Ahli Jawatankuasa',
            'filters' => $filters,
            'columns' => ['Nama', 'No. IC', 'Jenis', 'Jawatan', 'Parlimen', 'DUN', 'Sentimen'],
            'rows' => $rows,
            'total' => count($rows),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4')->download('senarai-ahli-jawatankuasa-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * JPRC/JPRD counts + per-DUN committee distribution, counted by DISTINCT
     * PERSON. The same person often holds several positions (several rows), and
     * may sit on both the JPRC and a JPRD — they are counted once. Identity is
     * the IC when present, otherwise the normalised name.
     */
    private function dashboard(): array
    {
        $rows = KeanggotaanJawatankuasa::get(['nama', 'no_ic', 'dun', 'matched_kadun', 'jenis', 'is_dicula', 'jawatan', 'cabang', 'matched_parlimen']);

        // DUN -> Parlimen from the voter roll, so committee rows that only carry a
        // DUN (no cabang tag) still resolve to their Parlimen. Keyed uppercase.
        $dunToParlimen = DB::table('pangkalan_data_pengundi')
            ->select('kadun', 'parlimen')
            ->whereNotNull('kadun')->where('kadun', '!=', '')
            ->whereNotNull('parlimen')->where('parlimen', '!=', '')
            ->distinct()->get()
            ->reduce(function ($map, $r) {
                $map[mb_strtoupper(trim($r->kadun))] ??= mb_strtoupper(trim($r->parlimen));

                return $map;
            }, []);

        $personKey = function ($r) {
            $ic = trim((string) $r->no_ic);

            return $ic !== '' ? 'ic:'.$ic : 'nama:'.mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $r->nama)));
        };

        // Sets of person keys, deduped as we go.
        $all = [];
        $perJenis = array_fill_keys(KeanggotaanJawatankuasa::JENIS, []);
        $withIc = [];
        $dicula = [];
        $byDun = [];
        $parlimenSet = [];      // distinct Parlimen (cabang)
        $dunsByParlimen = [];   // Parlimen => set of DUNs (for the cascading filter)

        foreach ($rows as $r) {
            $pk = $personKey($r);
            $all[$pk] = true;
            if (in_array($r->jenis, KeanggotaanJawatankuasa::JENIS, true)) {
                $perJenis[$r->jenis][$pk] = true;
            }
            if (trim((string) $r->no_ic) !== '') {
                $withIc[$pk] = true;
            }
            if ($r->is_dicula) {
                $dicula[$pk] = true;
            }

            // Effective DUN: dun column, else roll match, else embedded in the
            // jawatan text; parliament-level positions land under "Peringkat
            // Cabang".
            $dun = ($r->dun !== null && $r->dun !== '') ? $r->dun : ($r->matched_kadun ?: KeanggotaanJawatankuasa::extractDunFromJawatan($r->jawatan));

            // Parlimen = committee cabang, else roll-matched parlimen, else the
            // DUN's parlimen from the voter roll. Normalised uppercase.
            $rawParlimen = ($r->cabang !== null && $r->cabang !== '') ? $r->cabang
                : ($r->matched_parlimen ?: ($dun ? ($dunToParlimen[mb_strtoupper($dun)] ?? null) : null));
            $parlimen = ($rawParlimen !== null && trim($rawParlimen) !== '') ? mb_strtoupper(trim($rawParlimen)) : null;
            if ($parlimen !== null) {
                $parlimenSet[$parlimen] = true;
                if ($dun) {
                    $dunsByParlimen[$parlimen][$dun] = true;
                }
            }

            $key = $dun ?: ($r->jenis === 'JPRC' ? 'Peringkat Cabang' : 'Tidak Diketahui');
            $byDun[$key] ??= ['dun' => $key, 'total' => [], 'dicula' => []]
                + array_fill_keys(KeanggotaanJawatankuasa::JENIS, []);
            $byDun[$key]['total'][$pk] = true;
            if (in_array($r->jenis, KeanggotaanJawatankuasa::JENIS, true)) {
                $byDun[$key][$r->jenis][$pk] = true;
            }
            if ($r->is_dicula) {
                $byDun[$key]['dicula'][$pk] = true;
            }
        }

        // Collapse the per-DUN person sets into counts.
        $byDun = collect($byDun)->map(function ($d) {
            $out = ['dun' => $d['dun'], 'total' => count($d['total']), 'dicula' => count($d['dicula'])];
            foreach (KeanggotaanJawatankuasa::JENIS as $j) {
                $out[$j] = count($d[$j]);
            }

            return $out;
        })->sortByDesc('total')->values()->all();

        // Real DUNs only (exclude the parliament-level / unknown buckets).
        $dunOptions = collect($byDun)->pluck('dun')
            ->reject(fn ($d) => in_array($d, ['Peringkat Cabang', 'Tidak Diketahui'], true))
            ->sort()->values()->all();

        $parlimenOptions = collect(array_keys($parlimenSet))->sort()->values()->all();
        $dunsByParlimen = collect($dunsByParlimen)
            ->map(fn ($duns) => collect(array_keys($duns))->sort()->values()->all())->all();

        return [
            'summary' => [
                'total' => count($all),
                'jprc' => count($perJenis['JPRC']),
                'jprd' => count($perJenis['JPRD']),
                'dun_count' => count($dunOptions),
                'with_ic' => count($withIc),
                'dicula' => count($dicula),
            ],
            'byDun' => $byDun,
            'dunOptions' => $dunOptions,
            'parlimenOptions' => $parlimenOptions,
            'dunsByParlimen' => $dunsByParlimen,
        ];
    }

    public function store(Request $request)
    {
        $validated = $this->validateMember($request);

        $member = new KeanggotaanJawatankuasa($validated);
        $member->fill($this->matcher->match($validated['no_ic'] ?? ''));
        $member->save();

        return redirect()->back()->with('success', 'Ahli jawatankuasa berjaya ditambah.');
    }

    public function update(Request $request, KeanggotaanJawatankuasa $member)
    {
        $validated = $this->validateMember($request);

        $member->fill($validated);
        $member->fill($this->matcher->match($validated['no_ic'] ?? ''));
        $member->save();

        return redirect()->back()->with('success', 'Ahli jawatankuasa berjaya dikemaskini.');
    }

    public function destroy(KeanggotaanJawatankuasa $member)
    {
        $member->delete();

        return redirect()->back()->with('success', 'Ahli jawatankuasa berjaya dipadam.');
    }

    /**
     * Step 1 of the upload: read the file, let AI (or the heuristic fallback)
     * map its columns, and return the normalised rows for a preview — nothing
     * is saved yet.
     */
    public function analyze(Request $request, CommitteeImportMapper $mapper)
    {
        $request->validate([
            'fail' => 'required|file|mimes:xlsx,xls,csv,pdf,txt|max:51200',
            'jenis_default' => 'nullable|in:'.implode(',', KeanggotaanJawatankuasa::JENIS),
        ]);

        $file = $request->file('fail');

        return response()->json($mapper->analyze($file, $request->input('jenis_default'), $file->getClientOriginalName()));
    }

    /**
     * Step 2: persist the rows confirmed in the preview. Every row is
     * re-validated server-side (they round-tripped through the browser).
     */
    public function commit(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.no_ic' => 'nullable|digits:12',
            'rows.*.nama' => 'required|string|max:255',
            'rows.*.jenis' => 'required|in:'.implode(',', KeanggotaanJawatankuasa::JENIS),
            'rows.*.jawatan' => 'nullable|string|max:255',
            'rows.*.cabang' => 'nullable|string|max:255',
            'rows.*.dun' => 'nullable|string|max:255',
            'rows.*.no_tel' => 'nullable|string|max:30',
        ]);

        $count = 0;
        foreach ($validated['rows'] as $row) {
            // Store '' rather than null for a missing IC so the insert works
            // whether or not no_ic has been migrated to nullable.
            $row['no_ic'] = $row['no_ic'] ?? '';
            $member = new KeanggotaanJawatankuasa($row);
            // Voter-roll / dicula cross-check only runs for members with an IC.
            if ($row['no_ic'] !== '') {
                $member->fill($this->matcher->match($row['no_ic']));
            }
            $member->save();
            $count++;
        }

        return response()->json(['count' => $count]);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $count = KeanggotaanJawatankuasa::whereIn('id', $validated['ids'])->delete();

        return response()->json(['count' => $count]);
    }

    public function resync()
    {
        $this->matcher->syncTable('keanggotaan_jawatankuasa');

        return redirect()->back()->with('success', 'Padanan jawatankuasa dengan SISDA telah disegerakkan semula.');
    }

    private function validateMember(Request $request): array
    {
        $data = $request->validate([
            'no_ic' => 'nullable|string|max:12',
            'nama' => 'required|string|max:255',
            'jenis' => 'required|in:'.implode(',', KeanggotaanJawatankuasa::JENIS),
            'jawatan' => 'nullable|string|max:255',
            'cabang' => 'nullable|string|max:255',
            'dun' => 'nullable|string|max:255',
            'no_tel' => 'nullable|string|max:30',
        ]);
        // Never null — '' keeps the NOT NULL column happy and the cross-check skips it.
        $data['no_ic'] = $data['no_ic'] ?? '';

        return $data;
    }
}
