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

    /**
     * JSON payload for a chosen kawasan (Parlimen OR DUN) + jenis PR/tahun: reference
     * geography, saved parties & votes, and the form's review/publish state.
     *
     * Accepts EITHER `form_id` alone (used by the Upload tab's hand-off, which only
     * knows the form id — never the geography) OR the full
     * (kawasan_type, kawasan_id, jenis_pr, tahun) tuple used by the on-page picker.
     * Either way the response carries a `resolved` block so the caller can populate
     * its Negeri/Parlimen/DUN/Jenis PR/Tahun selects from whichever path was used.
     */
    public function data(Request $request)
    {
        if ($request->filled('form_id')) {
            $form = Borang14Form::findOrFail((int) $request->input('form_id'));
            $kawasanType = $form->kawasan_type;
            $kawasanId = (int) $form->kawasan_id;
            $jenisPr = $form->jenis_pr;
            $tahun = (int) $form->tahun;
        } else {
            $kawasanType = $request->input('kawasan_type');
            $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

            $validated = $request->validate([
                'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
                'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
                'jenis_pr' => 'required|in:pru,prn,prk',
                'tahun'    => 'required|integer|between:1959,2100',
                'penjuru'  => 'nullable|integer|in:2,3,4,5,6', // accepted for backward compat; not required to find the form
            ]);

            $kawasanType = $validated['kawasan_type'];
            $kawasanId = (int) $validated['kawasan_id'];
            $jenisPr = $validated['jenis_pr'];
            $tahun = (int) $validated['tahun'];

            $form = Borang14Form::forKawasan($kawasanType, $kawasanId)
                ->where('jenis_pr', $jenisPr)
                ->where('tahun', $tahun)
                ->first();
        }

        $isParlimen = $kawasanType === Borang14Form::KAWASAN_PARLIMEN;
        $reference = $isParlimen
            ? Borang14Reference::forBandar($kawasanId)
            : Borang14Reference::forKadun($kawasanId);

        // Newly-created (scoresheet-sourced) kawasan have no curated reference JSON
        // and no DPT roll uploaded yet — fall back to the scoresheet's own structure
        // so the tables still render instead of silently showing "no data".
        if (! $reference && $form?->structure) {
            $reference = $this->referenceFromStructure($form->structure, $form->kawasan());
        }

        $votes = $form
            ? $form->votes()->get(['pusat', 'saluran', 'slot', 'undi'])
                ->mapWithKeys(fn ($v) => [$this->cellKey($v->pusat, $v->saluran, $v->slot) => $v->undi])
            : collect();

        return response()->json([
            'reference' => $reference,
            'hasData'   => $reference !== null,
            'parties'   => $form->parties ?? [],
            'votes'     => $votes,
            'form' => $form ? [
                'id' => $form->id,
                'status' => $form->status,
                'source' => $form->source,
                'needs_review' => $form->needs_review,
                'crosscheck_issues' => $this->crosscheckIssues($form),
                'penjuru' => $form->penjuru,
            ] : null,
            'resolved' => array_merge(
                ['kawasan_type' => $kawasanType, 'kawasan_id' => $kawasanId, 'jenis_pr' => $jenisPr, 'tahun' => $tahun],
                $this->resolveIds($kawasanType, $kawasanId),
            ),
        ]);
    }

    /** Persist the chosen party names for a (kawasan, jenis PR, tahun) scenario. */
    public function saveParties(Request $request)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'parties'  => 'array',
            'parties.*.slot' => 'required|integer|min:1|max:6',
            'parties.*.keahlian_parti_id' => 'nullable|integer',
            'parties.*.nama' => 'nullable|string|max:100',
            'parties.*.calon' => 'nullable|string|max:150',
        ]);

        $form = Borang14Form::updateOrCreate(
            [
                'kawasan_type' => $validated['kawasan_type'],
                'kawasan_id'   => $validated['kawasan_id'],
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
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
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
                'kawasan_type' => $validated['kawasan_type'],
                'kawasan_id'   => $validated['kawasan_id'],
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

    /** Temporary: clear all entered vote figures for a (kawasan, jenis PR, tahun) scenario. */
    public function reset(Request $request)
    {
        $kawasanType = $request->input('kawasan_type');
        $existsTable = $kawasanType === Borang14Form::KAWASAN_PARLIMEN ? 'bandar' : 'kadun';

        $validated = $request->validate([
            'kawasan_type' => ['required', Rule::in([Borang14Form::KAWASAN_PARLIMEN, Borang14Form::KAWASAN_DUN])],
            'kawasan_id'   => ['required', 'integer', Rule::exists($existsTable, 'id')],
            'jenis_pr' => 'required|in:pru,prn,prk',
            'tahun'    => 'required|integer|between:1959,2100',
        ]);

        $form = Borang14Form::forKawasan($validated['kawasan_type'], $validated['kawasan_id'])
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        $form?->votes()->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Builds a Borang14Reference-shaped structure straight from a scoresheet's raw
     * extraction when no curated reference file / DPT roll exists yet for the
     * kawasan (true for any seat this feature just created). berdaftar figures are
     * ALWAYS null here — the scoresheet has no registered-voter column, only column
     * (A) which is ballots in the box, not registrations. Rows with pusat === ''
     * (UNDI AWAL / UNDI POS) are surfaced as undi_awal/undi_pos and are only present
     * when the sheet actually printed that row — never fabricated.
     *
     * @return array<string,mixed>
     */
    private function referenceFromStructure(array $structure, Bandar|Kadun|null $kawasan): array
    {
        $isParlimen = $kawasan instanceof Bandar;
        $daerah = [];
        $undiAwal = null;
        $undiPos = null;

        foreach ($structure['rows'] ?? [] as $r) {
            $pusat = (string) ($r['pusat'] ?? '');
            $saluran = $this->normalizeSaluran($r['saluran'] ?? null);

            if ($pusat === '') {
                // Carry the REAL saluran string through as 'label' — votes are
                // stored keyed on this exact string (putVote() uses
                // $row['saluran']), so the frontend must key off it too rather
                // than a hardcoded 'UNDI POS'/'UNDI AWAL' constant. If the AI
                // ever emits something other than the exact literal (e.g. an
                // extra suffix), a hardcoded key would silently show 0 votes
                // and any edit would write an orphan row under a key nothing
                // reads.
                $upper = strtoupper($saluran);
                if (str_contains($upper, 'AWAL')) {
                    $undiAwal = ['berdaftar' => null, 'label' => $saluran];
                } elseif (str_contains($upper, 'POS')) {
                    $undiPos = ['berdaftar' => null, 'label' => $saluran];
                }

                continue;
            }

            $dmNama = (string) ($r['dm'] ?? '');
            $daerah[$dmNama] ??= ['nama' => $dmNama, 'pusat_mengundi' => []];

            $pmIndex = null;
            foreach ($daerah[$dmNama]['pusat_mengundi'] as $i => $pm) {
                if ($pm['nama'] === $pusat) {
                    $pmIndex = $i;

                    break;
                }
            }
            if ($pmIndex === null) {
                $daerah[$dmNama]['pusat_mengundi'][] = ['nama' => $pusat, 'jumlah_berdaftar' => null, 'saluran' => []];
                $pmIndex = array_key_last($daerah[$dmNama]['pusat_mengundi']);
            }

            // Dedup: several raw rows can normalise to the SAME saluran value for one
            // Pusat (the blank-saluran case — the AI couldn't read that column at
            // all). Those votes are aggregated into ONE DB row by putVote(), so the
            // reference must show ONE Saluran row too, not a duplicate per raw row.
            $alreadyListed = collect($daerah[$dmNama]['pusat_mengundi'][$pmIndex]['saluran'])->contains('no', $saluran);
            if (! $alreadyListed) {
                $daerah[$dmNama]['pusat_mengundi'][$pmIndex]['saluran'][] = ['no' => $saluran, 'berdaftar' => null];
            }
        }

        return [
            'negeri'   => $isParlimen ? ($kawasan?->negeri?->nama ?? '') : ($kawasan?->bandar?->negeri?->nama ?? ''),
            'parlimen' => $isParlimen ? $kawasan?->nama : ($kawasan?->bandar?->nama ?? ''),
            'dun'      => $isParlimen ? null : $kawasan?->nama,
            'daerah_mengundi' => array_values($daerah),
            'undi_awal' => $undiAwal,
            'undi_pos'  => $undiPos,
            'source'    => 'scoresheet',
        ];
    }

    /** Negeri/Parlimen ids for a kawasan — lets the frontend picker resync from a bare form_id. */
    private function resolveIds(string $kawasanType, int $kawasanId): array
    {
        if ($kawasanType === Borang14Form::KAWASAN_PARLIMEN) {
            $bandar = Bandar::find($kawasanId);

            return ['negeri_id' => $bandar?->negeri_id, 'bandar_id' => $kawasanId];
        }

        $kadun = Kadun::find($kawasanId);

        return ['negeri_id' => $kadun?->bandar?->negeri_id, 'bandar_id' => $kadun?->bandar_id];
    }

    /**
     * Live silang-semak against the CURRENT votes (not the frozen extraction) so
     * fixing a cell clears its own warning on the next fetch. Column (A) itself is
     * not editable (no slot stores it), so it's taken as-is from the frozen
     * structure; everything else (party undi, ditolak, tidak dimasukkan) is
     * re-read from borang14_votes. Only meaningful for scoresheet-sourced forms —
     * manual entry has no independent (A) to check against.
     *
     * @return string[]
     */
    private function crosscheckIssues(Borang14Form $form): array
    {
        $structure = $form->structure;
        if (empty($structure['rows'])) {
            return [];
        }

        $nCalon = max(1, (int) $form->penjuru);
        $votesByCell = $form->votes()->get(['pusat', 'saluran', 'slot', 'undi'])
            ->groupBy(fn ($v) => $v->pusat.'|'.$v->saluran);

        // Feed validateBalance() the REAL frozen values from the sheet's own
        // extraction — the printed 'jumlah_undian' total and the actual
        // 'calon' list — NOT values re-derived from the live undi array
        // itself. Rebuilding both from the same live array made
        // 'jumlah_undian' and 'calon_count' compare a number against itself
        // (mathematically unreachable); only 'balance' could ever fire. Now:
        //   - jumlah_undian: live vote sum vs the sheet's own printed total —
        //     fires when entered figures no longer add up to what was printed.
        //   - calon_count: live candidate slot count (nCalon) vs the sheet's
        //     own candidate list — fires if the extraction's column count
        //     diverges from the currently configured penjuru.
        $calon = $structure['calon'] ?? array_fill(0, $nCalon, '');

        $liveRows = collect($structure['rows'])->map(function ($r) use ($votesByCell, $nCalon) {
            $pusat = (string) ($r['pusat'] ?? '');
            $saluran = $this->normalizeSaluran($r['saluran'] ?? null);
            $cells = $votesByCell->get($pusat.'|'.$saluran, collect());
            $slotVal = fn (int $n) => (int) ($cells->firstWhere('slot', $n)->undi ?? 0);

            $undi = [];
            for ($i = 1; $i <= $nCalon; $i++) {
                $undi[] = $slotVal($i);
            }

            return [
                'pusat' => $pusat,
                'saluran' => $saluran,
                'a' => (int) ($r['a'] ?? 0),
                'undi' => $undi,
                'jumlah_undian' => (int) ($r['jumlah_undian'] ?? 0),
                'ditolak' => $slotVal(90),
                'tidak_dimasukkan' => $slotVal(91),
            ];
        })->all();

        $findings = ScoresheetExtractor::validateBalance([
            'calon' => $calon,
            'rows' => $liveRows,
        ]);

        return collect($findings)->map(fn ($f) => $this->formatCrosscheckMessage($f))->values()->all();
    }

    private function formatCrosscheckMessage(array $f): string
    {
        $loc = $f['pusat'] !== '' ? "{$f['pusat']} — Saluran {$f['saluran']}" : $f['saluran'];

        return match ($f['rule']) {
            'balance' => "{$loc}: (A) dijangka {$f['jangka']}, dapat {$f['dapat']}",
            'jumlah_undian' => "{$loc}: jumlah undian dijangka {$f['jangka']}, dapat {$f['dapat']}",
            'calon_count' => "{$loc}: bilangan calon dijangka {$f['expected']}, dapat {$f['actual']}",
            default => "{$loc}: silang-semak tidak sepadan",
        };
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

        $form = Borang14Form::where('kawasan_type', $validated['kawasan_type'])
            ->where('kawasan_id', $kawasanId)
            ->where('jenis_pr', $validated['jenis_pr'])
            ->where('tahun', $validated['tahun'])
            ->first();

        // Same fallback as data(): a seat created from an upload has no curated
        // reference JSON and no DPT roll uploaded yet, so build the reference
        // straight from the scoresheet's own frozen structure instead of 404ing
        // on every seat this feature creates.
        if (! $reference && $form?->structure) {
            $reference = $this->referenceFromStructure($form->structure, $form->kawasan());
        }

        $seatLabel = $isParlimen ? 'Parlimen' : 'DUN';
        abort_if(! $reference, 404, "Data Borang 14 belum tersedia untuk {$seatLabel} ini.");

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
            // Surface the resolved seat type BEFORE anything is written, so the
            // confirm panel can show "Parlimen" vs "DUN" explicitly rather than
            // the user only finding out afterwards (or, before this fix, never —
            // the resolver used to hardcode DUN regardless of the sheet's level).
            'kawasan_type' => $kawasan['kawasan_type'],
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
            // 'nama' starts out as the candidate's own name (placeholder until the
            // user maps a party) while 'calon' permanently records who the AI read
            // off the sheet, so the dropdown UI can always show "Calon: X" even
            // after a party is picked and 'nama' becomes the party name instead.
            'parties' => collect($extractedData['calon'] ?? [])->values()
                ->map(fn ($c, $i) => [
                    'slot' => $i + 1, 'keahlian_parti_id' => null,
                    'nama' => $c['nama'], 'calon' => $c['nama'],
                ])->all(),
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

    /**
     * Satu baris per sel. Baris Undi Pos/Awal guna pusat=''.
     *
     * AGGREGATE (jumlah), bukan tulis ganti: beberapa baris mentah scoresheet
     * boleh menyimpang kepada kunci sel (pusat, saluran, slot) yang SAMA apabila
     * saluran kosong (AI gagal baca lajur "No. Tempat Mengundi" untuk baris itu)
     * — spesifikasi memerlukan baris sebegini digabung menjadi SATU baris per
     * Pusat, bukan biarkan baris terakhir menulis ganti yang sebelumnya (undi
     * hilang secara senyap). Untuk kes biasa (saluran sebenar, unik per baris)
     * kelakuan ini SAMA seperti tulis ganti — tiada baris sedia ada untuk
     * ditambah kepada, jadi ia berkelakuan seperti "set" seperti sebelum ini.
     */
    private function putVote(Borang14Form $form, array $row, int $slot, int $undi): void
    {
        $key = [
            'borang14_form_id' => $form->id,
            'pusat' => (string) ($row['pusat'] ?? ''),
            'saluran' => $this->normalizeSaluran($row['saluran'] ?? null),
            'slot' => $slot,
        ];

        $existingUndi = (int) (Borang14Vote::where($key)->value('undi') ?? 0);

        Borang14Vote::updateOrCreate($key, ['undi' => $existingUndi + $undi]);
    }

    /**
     * SATU tempat sahaja untuk normalkan saluran kosong — writer (putVote())
     * dan readers (referenceFromStructure(), crosscheckIssues()) MESTI guna
     * nilai yang SAMA, jika tidak kunci sel (cellKey) menyimpang: undi
     * tersimpan di DB di bawah satu kunci, tetapi skrin/PDF membaca kunci
     * lain — sel kelihatan 0 walaupun undi selamat tersimpan, dan sebarang
     * suntingan skrin menulis baris anak-yatim baharu yang tiada siapa baca.
     */
    private function normalizeSaluran(?string $raw): string
    {
        return trim((string) ($raw ?? ''));
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
