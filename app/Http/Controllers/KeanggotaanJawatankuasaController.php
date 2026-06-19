<?php

namespace App\Http\Controllers;

use App\Models\KeanggotaanJawatankuasa;
use App\Services\Keanggotaan\MemberMatchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

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

    /** Per-jenis "dicula" counts + per-DUN wing distribution. */
    private function dashboard(): array
    {
        $perJenis = KeanggotaanJawatankuasa::selectRaw('jenis, COUNT(*) AS total, SUM(is_dicula) AS dicula')
            ->groupBy('jenis')->get()
            ->map(fn ($r) => [
                'jenis' => $r->jenis,
                'total' => (int) $r->total,
                'dicula' => (int) $r->dicula,
                'dicula_pct' => $r->total > 0 ? round(($r->dicula / $r->total) * 100, 1) : 0,
            ]);

        $rows = KeanggotaanJawatankuasa::selectRaw("
                COALESCE(NULLIF(dun, ''), matched_kadun) AS dun, jenis,
                COUNT(*) AS jumlah, SUM(is_dicula) AS dicula
            ")
            ->groupByRaw("COALESCE(NULLIF(dun, ''), matched_kadun), jenis")
            ->get();

        // Pivot to one row per DUN with a column per jenis.
        $byDun = [];
        foreach ($rows as $r) {
            $key = $r->dun ?: 'Tidak Diketahui';
            $byDun[$key] ??= ['dun' => $key, 'total' => 0, 'dicula' => 0]
                + array_fill_keys(KeanggotaanJawatankuasa::JENIS, 0);
            $byDun[$key][$r->jenis] = (int) $r->jumlah;
            $byDun[$key]['total'] += (int) $r->jumlah;
            $byDun[$key]['dicula'] += (int) $r->dicula;
        }
        $byDun = collect($byDun)->sortByDesc('total')->values()->all();

        $total = KeanggotaanJawatankuasa::count();
        $dicula = (int) KeanggotaanJawatankuasa::where('is_dicula', true)->count();

        return [
            'summary' => [
                'total' => $total,
                'dicula' => $dicula,
                'dicula_pct' => $total > 0 ? round(($dicula / $total) * 100, 1) : 0,
                'dalam_kawasan' => KeanggotaanJawatankuasa::where('status_kawasan', 'dalam_kawasan')->count(),
            ],
            'perJenis' => $perJenis,
            'byDun' => $byDun,
        ];
    }

    public function store(Request $request)
    {
        $validated = $this->validateMember($request);

        $member = new KeanggotaanJawatankuasa($validated);
        $member->fill($this->matcher->match($validated['no_ic']));
        $member->save();

        return redirect()->back()->with('success', 'Ahli jawatankuasa berjaya ditambah.');
    }

    public function update(Request $request, KeanggotaanJawatankuasa $member)
    {
        $validated = $this->validateMember($request);

        $member->fill($validated);
        $member->fill($this->matcher->match($validated['no_ic']));
        $member->save();

        return redirect()->back()->with('success', 'Ahli jawatankuasa berjaya dikemaskini.');
    }

    public function destroy(KeanggotaanJawatankuasa $member)
    {
        $member->delete();

        return redirect()->back()->with('success', 'Ahli jawatankuasa berjaya dipadam.');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'fail' => 'required|file|mimes:xlsx,xls,csv|max:51200',
            'jenis_default' => 'nullable|in:'.implode(',', KeanggotaanJawatankuasa::JENIS),
        ]);

        $default = $request->input('jenis_default');
        $rows = Excel::toCollection(null, $request->file('fail'))->first() ?? collect();
        $header = collect($rows->first())->map(fn ($v) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $v)))->all();
        $inserted = 0;

        foreach ($rows->slice(1) as $row) {
            $cells = $row->values()->all();
            $get = function (array $aliases) use ($header, $cells) {
                foreach ($aliases as $alias) {
                    $i = array_search($alias, $header, true);
                    if ($i !== false && isset($cells[$i]) && $cells[$i] !== null && $cells[$i] !== '') {
                        return trim((string) $cells[$i]);
                    }
                }

                return null;
            };

            $ic = str_pad((string) ($get(['ic', 'noic', 'nokp', 'kadpengenalan']) ?? ''), 12, '0', STR_PAD_LEFT);
            if (strlen($ic) !== 12 || ! ctype_digit($ic)) {
                continue;
            }

            $jenis = strtoupper(str_replace([' ', '-'], '_', (string) ($get(['jenis', 'kategori']) ?? $default ?? '')));
            if (! in_array($jenis, KeanggotaanJawatankuasa::JENIS, true)) {
                $jenis = $default;
            }
            if (! $jenis) {
                continue;
            }

            $member = new KeanggotaanJawatankuasa([
                'no_ic' => $ic,
                'nama' => strtoupper((string) ($get(['nama', 'name']) ?? '-')),
                'jenis' => $jenis,
                'jawatan' => $get(['jawatan', 'position']),
                'cabang' => $get(['cabang', 'branch']),
                'dun' => $get(['dun', 'kadun']) ? strtoupper($get(['dun', 'kadun'])) : null,
                'no_tel' => $get(['notel', 'telefon', 'phone']),
            ]);
            $member->fill($this->matcher->match($ic));
            $member->save();
            $inserted++;
        }

        return redirect()->back()->with('success', "{$inserted} ahli jawatankuasa berjaya dimuat naik.");
    }

    public function resync()
    {
        $this->matcher->syncTable('keanggotaan_jawatankuasa');

        return redirect()->back()->with('success', 'Padanan jawatankuasa dengan SISDA telah disegerakkan semula.');
    }

    private function validateMember(Request $request): array
    {
        return $request->validate([
            'no_ic' => 'required|string|max:12',
            'nama' => 'required|string|max:255',
            'jenis' => 'required|in:'.implode(',', KeanggotaanJawatankuasa::JENIS),
            'jawatan' => 'nullable|string|max:255',
            'cabang' => 'nullable|string|max:255',
            'dun' => 'nullable|string|max:255',
            'no_tel' => 'nullable|string|max:30',
        ]);
    }
}
