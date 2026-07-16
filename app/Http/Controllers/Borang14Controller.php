<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Snapshot;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\KeahlianParti;
use App\Models\Negeri;
use App\Services\Pilihanraya\KawasanResolver;
use App\Services\Pilihanraya\ScoresheetExtractor;
use App\Support\Borang14Reference;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class Borang14Controller extends Controller
{
    /** Undi Awal & Undi Pos are combined into a single row only for this DUN. */
    private const BULOH_KASAP_KADUN_ID = 41;

    /** Penjuru dropdown → number of party columns. */
    private const PENJURU = [
        2 => '1 vs 1',
        3 => '3 Penjuru',
        4 => '4 Penjuru',
        5 => '5 Penjuru',
        6 => '6 Penjuru',
    ];

    public function index(Request $request)
    {
        return Inertia::render('Pilihanraya/Borang14', [
            'negeriList'   => Negeri::orderBy('nama')->get(['id', 'nama']),
            'parlimenList' => Bandar::orderBy('nama')->get(['id', 'nama', 'negeri_id']),
            'kadunList'    => Kadun::orderBy('nama')->get(['id', 'nama', 'bandar_id']),
            'partiList'    => KeahlianParti::orderBy('nama')->get(['id', 'nama']),
            'penjuruOptions' => collect(self::PENJURU)->map(fn ($label, $val) => ['value' => (int) $val, 'label' => $label])->values(),
        ]);
    }

    /** JSON payload for a chosen DUN (+ jenis PR/tahun + optional penjuru): reference geography, saved parties & votes. */
    public function data(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'nullable|integer|in:2,3,4,5,6',
        ]);

        $reference = Borang14Reference::forKadun((int) $validated['kadun_id']);

        $parties = [];
        $votes = [];
        if ($reference && ! empty($validated['penjuru'])) {
            $form = Borang14Form::where('kawasan_type', Borang14Form::KAWASAN_DUN)
                ->where('kawasan_id', $validated['kadun_id'])
                ->where('jenis_pr', $validated['jenis_pr'])
                ->where('tahun', $validated['tahun'])
                ->first();

            if ($form) {
                $parties = $form->parties ?? [];
                $votes = $form->votes()
                    ->get(['pusat', 'saluran', 'slot', 'undi'])
                    ->map(fn ($v) => [
                        'key'  => $this->cellKey($v->pusat, $v->saluran, $v->slot),
                        'undi' => $v->undi,
                    ])
                    ->pluck('undi', 'key');
            }
        }

        return response()->json([
            'reference'   => $reference,
            'hasData'     => $reference !== null,
            'parties'     => $parties,
            'votes'       => $votes,
        ]);
    }

    /** Persist the chosen party names for a (DUN, jenis PR, tahun, penjuru) scenario. */
    public function saveParties(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'parties'  => 'array',
            'parties.*.slot' => 'required|integer|min:1|max:6',
            'parties.*.keahlian_parti_id' => 'nullable|integer',
            'parties.*.nama' => 'nullable|string|max:100',
        ]);

        $form = Borang14Form::updateOrCreate(
            [
                'kawasan_type' => Borang14Form::KAWASAN_DUN,
                'kawasan_id'   => $validated['kadun_id'],
                'jenis_pr'     => $validated['jenis_pr'],
                'tahun'        => $validated['tahun'],
            ],
            ['penjuru' => $validated['penjuru'], 'parties' => $validated['parties'] ?? []],
        );

        return response()->json(['ok' => true, 'form_id' => $form->id]);
    }

    /** Upsert a single editable cell (auto-save on blur). */
    public function saveVote(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'pusat'    => 'nullable|string|max:255',
            'saluran'  => 'required|string|max:50',
            'slot'     => 'required|integer|in:1,2,3,4,5,6,90,91',   // 90 = ditolak (C), 91 = tidak dimasukkan (D)
            'undi'     => 'nullable|integer|min:0|max:1000000',
        ]);

        $form = Borang14Form::firstOrCreate(
            [
                'kawasan_type' => Borang14Form::KAWASAN_DUN,
                'kawasan_id'   => $validated['kadun_id'],
                'jenis_pr'     => $validated['jenis_pr'],
                'tahun'        => $validated['tahun'],
            ],
            ['penjuru' => $validated['penjuru'], 'parties' => []],
        );

        Borang14Vote::updateOrCreate(
            [
                'borang14_form_id' => $form->id,
                'pusat'   => $validated['pusat'] ?? '',
                'saluran' => $validated['saluran'],
                'slot'    => $validated['slot'],
            ],
            ['undi' => $validated['undi'] ?? 0],
        );

        return response()->json(['ok' => true]);
    }

    /** Temporary: clear all entered vote figures for a (DUN, jenis PR, tahun, penjuru) scenario. */
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
        ]);

        $form = Borang14Form::where('kawasan_type', Borang14Form::KAWASAN_DUN)
            ->where('kawasan_id', $validated['kadun_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        $form?->votes()->delete();

        return response()->json(['ok' => true]);
    }

    public function pdf(Request $request)
    {
        // kawasan_id's exists-table depends on kawasan_type — validated against
        // bandar for Parlimen, kadun for DUN, so an id from the wrong table
        // (e.g. a Parlimen id passed with kawasan_type=dun) is always rejected.
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'parti'    => 'array',
            'parti.*'  => 'nullable|string|max:100',
        ]);

        $isParlimen = $validated['kawasan_type'] === Borang14Form::KAWASAN_PARLIMEN;
        $kawasanId = (int) $validated['kawasan_id'];

        $reference = $isParlimen
            ? Borang14Reference::forBandar($kawasanId)
            : Borang14Reference::forKadun($kawasanId);

        $seatLabel = $isParlimen ? 'Parlimen' : 'DUN';
        abort_if(! $reference, 404, "Data Borang 14 belum tersedia untuk {$seatLabel} ini.");

        $form = Borang14Form::where('kawasan_type', $validated['kawasan_type'])
            ->where('kawasan_id', $kawasanId)
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        // Prefer the party names passed from the page so the PDF column headers
        // match the on-screen dropdown selection exactly; fall back to the saved
        // form when the request doesn't carry them.
        $parties = $form?->parties ?? [];
        if ($request->filled('parti')) {
            $parties = [];
            foreach (array_values($request->input('parti')) as $i => $nama) {
                $parties[] = ['slot' => $i + 1, 'nama' => $nama];
            }
        }
        $votes = $form
            ? $form->votes()->get()->mapWithKeys(fn ($v) => [
                $this->cellKey($v->pusat, $v->saluran, $v->slot) => $v->undi,
            ])->all()
            : [];

        $logo = $this->logoDataUri();

        $pdf = Pdf::loadView('pdf.borang14', [
            'reference' => $reference,
            'penjuru'   => (int) $validated['penjuru'],
            'penjuruLabel' => self::PENJURU[$validated['penjuru']] ?? '',
            'parties'   => $parties,
            'votes'     => $votes,
            'logo'      => $logo,
            // Buloh Kasap's Undi Awal/Pos merge is a DUN-only exception — a
            // Parlimen that happens to share id 41 must never trigger it.
            'isBulohKasap' => ! $isParlimen && $kawasanId === self::BULOH_KASAP_KADUN_ID,
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        $areaName = $isParlimen ? ($reference['parlimen'] ?? 'parlimen') : ($reference['dun'] ?? 'dun');
        $name = 'borang-14-' . str($areaName)->slug() . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($name);
    }

    /** TTL untuk cache dry-run — cukup masa untuk pengguna baca prompt pengesahan. */
    private const DRY_RUN_TTL_MINUTES = 15;

    /**
     * Upload scoresheet dalam DUA langkah supaya AI yang tersalah baca nama kawasan
     * boleh dibatalkan SEBELUM apa-apa ditulis:
     *   1) dry_run=1 + fail  -> baca (AI) + padan kawasan TANPA menulis; pulangkan token.
     *   2) token (tiada fail) -> baca semula hasil ekstrak dari cache ikut token, tulis sebenar.
     * Ekstrak (mahal, ~200s) hanya berlaku SEKALI, semasa langkah 1.
     */
    public function upload(Request $request, ScoresheetExtractor $extractor)
    {
        if ($request->boolean('dry_run')) {
            return $this->uploadDryRun($request, $extractor);
        }

        if ($request->filled('token')) {
            return $this->uploadCommit($request);
        }

        return response()->json(['message' => 'Permintaan muat naik tidak lengkap.'], 422);
    }

    /** Langkah 1: baca scoresheet, padan kawasan tanpa menulis, simpan hasil ekstrak dalam cache. */
    private function uploadDryRun(Request $request, ScoresheetExtractor $extractor)
    {
        $data = $request->validate([
            'fail' => 'required|file|mimes:xlsx,xls,csv,txt,pdf,jpg,jpeg,png,webp|max:20480',
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun' => 'required|integer|between:1959,2100',
        ]);

        @set_time_limit(200);

        $res = $extractor->extractDetailed($request->file('fail'));
        if (! $res['ok']) {
            return response()->json(['message' => $res['error'] ?: 'Bacaan scoresheet gagal. Semak Tetapan → Claude.'], 422);
        }

        $kawasan = KawasanResolver::resolve($res['data'], dryRun: true);
        if (! $kawasan['ok']) {
            return response()->json(['message' => $kawasan['error']], 422);
        }

        $token = Str::random(40);
        Cache::put($this->dryRunCacheKey($token), [
            'user_id' => $request->user()?->id,
            'extracted' => $res['data'],
            'jenis_pr' => $data['jenis_pr'],
            'tahun' => $data['tahun'],
            'filename' => $request->file('fail')->getClientOriginalName(),
        ], now()->addMinutes(self::DRY_RUN_TTL_MINUTES));

        $review = $this->computeNeedsReview($res['data']);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'will_create' => $kawasan['created'],
            'negeri' => $res['data']['negeri'] ?? null,
            'kawasan_nama' => $res['data']['kawasan_nama'] ?? null,
            'needs_review' => $review['needs_review'],
            'unbalanced' => $review['unbalanced'],
        ]);
    }

    /** Langkah 2: ambil hasil ekstrak dari cache ikut token, cipta kawasan sebenar, tulis borang + undi. */
    private function uploadCommit(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);

        $cacheKey = $this->dryRunCacheKey($data['token']);
        $cached = Cache::get($cacheKey);

        // Token asing/luput/tidak wujud dilayan SAMA — jangan bocorkan sebab sebenar.
        if (! $cached || ($cached['user_id'] ?? null) !== $request->user()?->id) {
            return response()->json(['message' => 'Token muat naik tidak sah atau telah tamat tempoh. Sila muat naik semula.'], 422);
        }

        $extractedData = $cached['extracted'];

        $kawasan = KawasanResolver::resolve($extractedData, dryRun: false);
        if (! $kawasan['ok']) {
            Cache::forget($cacheKey);

            return response()->json(['message' => $kawasan['error']], 422);
        }

        $form = Borang14Form::firstOrNew([
            'kawasan_type' => $kawasan['kawasan_type'],
            'kawasan_id' => $kawasan['kawasan_id'],
            'jenis_pr' => $cached['jenis_pr'],
            'tahun' => $cached['tahun'],
        ]);

        // Scoresheet menang — tetapi simpan keadaan lama dahulu supaya boleh revert.
        if ($form->exists) {
            Borang14Snapshot::create([
                'borang14_form_id' => $form->id,
                'structure' => $form->structure,
                'votes' => $form->votes()->get(['pusat', 'saluran', 'slot', 'undi'])->toArray(),
                'parties' => $form->parties,
                'reason' => 'before_scoresheet_overwrite',
                'created_by' => $request->user()?->id,
            ]);
            $form->votes()->delete();
        }

        $review = $this->computeNeedsReview($extractedData);

        $form->fill([
            'penjuru' => max(2, count($extractedData['calon'] ?? [])),
            'parties' => collect($extractedData['calon'] ?? [])->values()
                ->map(fn ($c, $i) => ['slot' => $i + 1, 'keahlian_parti_id' => null, 'nama' => $c['nama']])->all(),
            'structure' => $extractedData,
            'status' => 'draft',
            'source' => 'scoresheet',
            'source_filename' => $cached['filename'],
            'needs_review' => $review['needs_review'],
        ])->save();

        foreach ($extractedData['rows'] as $r) {
            foreach (($r['undi'] ?? []) as $i => $undi) {
                $this->putVote($form, $r, $i + 1, (int) $undi);
            }
            $this->putVote($form, $r, 90, (int) ($r['ditolak'] ?? 0));
            $this->putVote($form, $r, 91, (int) ($r['tidak_dimasukkan'] ?? 0));
        }

        // Elak main semula token selepas commit berjaya.
        Cache::forget($cacheKey);

        return response()->json([
            'ok' => true,
            'form_id' => $form->id,
            'created' => $kawasan['created'],
            'unbalanced' => $review['unbalanced'],
            'needs_review' => $form->needs_review,
        ]);
    }

    private function dryRunCacheKey(string $token): string
    {
        return 'borang14:upload-dry-run:' . $token;
    }

    /**
     * Satu-satunya tempat "needs_review" dikira — dikongsi antara dry run dan commit
     * supaya kedua-dua langkah tidak boleh terpesong (drift) antara satu sama lain.
     */
    private function computeNeedsReview(array $extractedData): array
    {
        $unbalanced = ScoresheetExtractor::validateBalance($extractedData);
        $anyGuess = collect($extractedData['calon'] ?? [])->contains(fn ($c) => ! ($c['yakin'] ?? false));
        $noSaluran = collect($extractedData['rows'] ?? [])
            ->contains(fn ($r) => ($r['pusat'] ?? '') !== '' && blank($r['saluran'] ?? null));

        return [
            'unbalanced' => $unbalanced,
            'needs_review' => $unbalanced !== [] || $anyGuess || $noSaluran,
        ];
    }

    /** Satu baris per sel. Baris Undi Pos/Awal guna pusat=''. */
    private function putVote(Borang14Form $form, array $row, int $slot, int $undi): void
    {
        Borang14Vote::updateOrCreate(
            [
                'borang14_form_id' => $form->id,
                'pusat' => (string) ($row['pusat'] ?? ''),
                'saluran' => (string) ($row['saluran'] ?? '1'),
                'slot' => $slot,
            ],
            ['undi' => $undi],
        );
    }

    public function publish(Request $request)
    {
        $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);
        $form = Borang14Form::findOrFail($data['form_id']);
        $form->update(['status' => 'published', 'published_at' => now()]);

        return response()->json(['ok' => true, 'published_at' => $form->published_at]);
    }

    public function revert(Request $request)
    {
        $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);
        $form = Borang14Form::findOrFail($data['form_id']);

        $snap = $form->snapshots()->latest('created_at')->first();
        if (! $snap) {
            return response()->json(['message' => 'Tiada snapshot untuk dipulihkan.'], 422);
        }

        $form->votes()->delete();
        foreach ($snap->votes as $v) {
            Borang14Vote::create([
                'borang14_form_id' => $form->id,
                'pusat' => $v['pusat'], 'saluran' => $v['saluran'],
                'slot' => $v['slot'], 'undi' => $v['undi'],
            ]);
        }
        $form->update(['structure' => $snap->structure, 'parties' => $snap->parties]);

        return response()->json(['ok' => true]);
    }

    public function senarai(Request $request)
    {
        $data = $request->validate([
            'negeri_id' => 'required|integer|exists:negeri,id',
            'bandar_id' => 'nullable|integer|exists:bandar,id',
            'kadun_id' => 'nullable|integer|exists:kadun,id',
        ]);

        // Semantik penapis (spec):
        //   Negeri sahaja      -> semua rekod dalam negeri (Parlimen DAN DUN)
        //   + Parlimen         -> rekod Parlimen itu DAN semua DUN di bawahnya
        //   + DUN              -> DUN itu sahaja
        $bandarIds = Bandar::where('negeri_id', $data['negeri_id'])->pluck('id');
        if (! empty($data['bandar_id'])) {
            $bandarIds = collect([$data['bandar_id']]);
        }
        $kadunIds = ! empty($data['kadun_id'])
            ? collect([$data['kadun_id']])
            : Kadun::whereIn('bandar_id', $bandarIds)->pluck('id');

        $rows = Borang14Form::query()
            ->where(function ($q) use ($bandarIds, $kadunIds, $data) {
                if (empty($data['kadun_id'])) {
                    $q->orWhere(fn ($w) => $w->where('kawasan_type', 'parlimen')->whereIn('kawasan_id', $bandarIds));
                }
                $q->orWhere(fn ($w) => $w->where('kawasan_type', 'dun')->whereIn('kawasan_id', $kadunIds));
            })
            ->orderByDesc('tahun')->orderBy('jenis_pr')->orderBy('kawasan_type')
            ->get()
            ->map(function (Borang14Form $f) {
                // Resolve the real ids ourselves so the frontend never has to
                // recover them by matching kawasan_nama strings — duplicate
                // names within a state (plausible since this feature CREATES
                // seats from AI-read sheets) would otherwise silently target
                // the wrong seat.
                $isParlimen = $f->kawasan_type === Borang14Form::KAWASAN_PARLIMEN;
                $kawasan = $f->kawasan();
                $bandar = $isParlimen ? $kawasan : $kawasan?->bandar;

                return [
                    'id' => $f->id, 'tahun' => $f->tahun, 'jenis_pr' => $f->jenis_pr,
                    'kawasan_type' => $f->kawasan_type, 'kawasan_id' => $f->kawasan_id,
                    'kawasan_nama' => $kawasan?->nama ?? '—',
                    'negeri_id' => $bandar?->negeri_id,
                    'bandar_id' => $isParlimen ? $f->kawasan_id : $kawasan?->bandar_id,
                    'penjuru' => $f->penjuru, 'status' => $f->status, 'source' => $f->source,
                    'source_filename' => $f->source_filename, 'needs_review' => $f->needs_review,
                    'published_at' => $f->published_at,
                ];
            });

        return response()->json(['rows' => $rows]);
    }

    private function cellKey(?string $pusat, string $saluran, int $slot): string
    {
        return ($pusat ?? '') . '|' . $saluran . '|' . $slot;
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}
