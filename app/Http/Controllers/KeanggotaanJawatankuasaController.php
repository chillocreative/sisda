<?php

namespace App\Http\Controllers;

use App\Models\KeanggotaanJawatankuasa;
use App\Services\Keanggotaan\CommitteeImportMapper;
use App\Services\Keanggotaan\MemberMatchService;
use Illuminate\Http\Request;
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
        $query = KeanggotaanJawatankuasa::query();
        if (in_array($request->input('jenis'), KeanggotaanJawatankuasa::JENIS, true)) {
            $query->where('jenis', $request->input('jenis'));
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")->orWhere('no_ic', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Pilihanraya/Jawatankuasa', array_merge([
            'members' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $request->only(['jenis', 'search']),
            'jenisOptions' => KeanggotaanJawatankuasa::JENIS,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ], $this->dashboard()));
    }

    /** JPRC/JPRD counts + per-DUN committee distribution. */
    private function dashboard(): array
    {
        $perJenis = KeanggotaanJawatankuasa::selectRaw('jenis, COUNT(*) AS total')
            ->groupBy('jenis')->pluck('total', 'jenis');

        // Pivot per DUN in PHP (grouping on a COALESCE expression trips
        // ONLY_FULL_GROUP_BY on strict MySQL). JPRC is parliament-level and
        // usually carries no DUN, so it lands under "Peringkat Cabang".
        $byDun = [];
        foreach (KeanggotaanJawatankuasa::get(['dun', 'matched_kadun', 'jenis', 'is_dicula']) as $r) {
            $dun = ($r->dun !== null && $r->dun !== '') ? $r->dun : ($r->matched_kadun ?: null);
            $key = $dun ?: ($r->jenis === 'JPRC' ? 'Peringkat Cabang' : 'Tidak Diketahui');
            $byDun[$key] ??= ['dun' => $key, 'total' => 0, 'dicula' => 0]
                + array_fill_keys(KeanggotaanJawatankuasa::JENIS, 0);
            $byDun[$key][$r->jenis] = ($byDun[$key][$r->jenis] ?? 0) + 1;
            $byDun[$key]['total'] += 1;
            $byDun[$key]['dicula'] += $r->is_dicula ? 1 : 0;
        }
        $byDun = collect($byDun)->sortByDesc('total')->values()->all();

        return [
            'summary' => [
                'total' => KeanggotaanJawatankuasa::count(),
                'jprc' => (int) ($perJenis['JPRC'] ?? 0),
                'jprd' => (int) ($perJenis['JPRD'] ?? 0),
                'dun_count' => KeanggotaanJawatankuasa::whereNotNull('dun')->where('dun', '!=', '')->distinct()->count('dun'),
                'with_ic' => KeanggotaanJawatankuasa::whereNotNull('no_ic')->count(),
                'dicula' => (int) KeanggotaanJawatankuasa::where('is_dicula', true)->count(),
            ],
            'byDun' => $byDun,
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
            'fail' => 'required|file|mimes:xlsx,xls,csv|max:51200',
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
            $member = new KeanggotaanJawatankuasa($row);
            // Voter-roll / dicula cross-check only runs for members with an IC.
            if (! empty($row['no_ic'])) {
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
        return $request->validate([
            'no_ic' => 'nullable|string|max:12',
            'nama' => 'required|string|max:255',
            'jenis' => 'required|in:'.implode(',', KeanggotaanJawatankuasa::JENIS),
            'jawatan' => 'nullable|string|max:255',
            'cabang' => 'nullable|string|max:255',
            'dun' => 'nullable|string|max:255',
            'no_tel' => 'nullable|string|max:30',
        ]);
    }
}
