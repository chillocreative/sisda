<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMobileCulaanRequest;
use App\Models\EditHistory;
use App\Models\HasilCulaan;
use App\Models\TujuanSumbangan;
use App\Services\CulaanPayloadNormalizer;
use App\Services\VoterColorService;
use App\Services\VoterDataMasker;
use App\Services\VoterSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileCulaanController extends Controller
{
    /**
     * Create a Hasil Culaan from the mobile app.
     *
     * Idempotent by client-generated key: a replay returns the original
     * record untouched. This is what makes the client's automatic retry
     * safe after a lost response. The replay lookup itself lives in
     * StoreMobileCulaanRequest::prepareForValidation() (scoped to the
     * caller, and run BEFORE source resolution/validation — see that
     * method's docblock) so this controller only ever sees requests that
     * are either genuinely new or lost the check-then-act race handled by
     * the QueryException catch below.
     */
    public function store(StoreMobileCulaanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Parlimen restriction, mirroring ReportsController::hasilCulaanStore.
        if (($user->isUser() || $user->isAdmin()) && $validated['parlimen'] !== ($user->bandar->nama ?? '')) {
            return response()->json([
                'success' => false,
                'errors' => ['parlimen' => ['Rekod ini di luar Parlimen anda.']],
            ], 403);
        }

        // Masked-create: '****' placeholders and the scoped locked_source_id
        // swap are resolved in StoreMobileCulaanRequest::prepareForValidation(),
        // BEFORE rules() runs — see that method's docblock for why the
        // ordering matters and why the lookup must stay scoped through
        // VoterScopeService. $validated already carries real values here.
        unset($validated['has_sumbangan'], $validated['locked_source_id']);

        $payload = CulaanPayloadNormalizer::normalize($validated);
        $payload['submitted_by'] = $user->id;
        $payload['sumber'] = 'mobile';
        // Compute voter_color ONLY when this submission actually carries
        // political signal. VoterColorService::determine(null, null)
        // returns a definite 'kelabu' — it answers "what does THIS
        // submission's signal classify as", not "did this submission
        // mention politics at all". keahlian_parti/kecenderungan_politik
        // are plain `nullable` here (unlike the web form's
        // has_sumbangan=false branch, which never reaches
        // syncFromHasilCulaan() at all — see ReportsController.php:465), so
        // mobile routinely omits both. Setting $payload['voter_color']
        // unconditionally would put it in getAttributes() on every
        // create(), and VoterSyncService::extract()'s array_key_exists()
        // gate (see the ->fresh() comment below) propagates anything
        // present there — so a submission that said nothing about politics
        // would overwrite a known 'hitam'/'putih' voter_color with 'kelabu'
        // on the existing data_pengundi row. Leaving the key unset means
        // the new hasil_culaan row gets voter_color = NULL, which is
        // honest: this submission recorded no political signal. When there
        // IS signal (at least one of the two fields present), this mirrors
        // ReportsController.php:455 exactly.
        if (($payload['keahlian_parti'] ?? null) !== null || ($payload['kecenderungan_politik'] ?? null) !== null) {
            $payload['voter_color'] = VoterColorService::determine($payload['keahlian_parti'] ?? null, $payload['kecenderungan_politik'] ?? null);
        }

        // The create fans out through VoterSyncService across two tables.
        // CLAUDE.md flags the HTTP layer as transaction-free; this path is not.
        try {
            $record = DB::transaction(function () use ($payload) {
                $record = HasilCulaan::create($payload);
                EditHistory::log('hasil_culaan', $record->id, 'created (mobile)');
                // Deliberately NOT ->fresh(): syncFromHasilCulaan()/extract()
                // copies a shared field only if it is present in
                // getAttributes(). Right after create(), that's exactly (and
                // only) what this mobile submission actually sent — matching
                // ReportsController.php:460's web create path, which also
                // passes $record, not fresh(). fresh() re-reads every column
                // from the DB (mostly NULL on a minimal submission) and would
                // blanket-wipe an existing voter's enriched fields —
                // including silently resurrecting anyone marked deceased.
                VoterSyncService::syncFromHasilCulaan($record);

                return $record;
            });
        } catch (QueryException $e) {
            // Backstop for the check-then-act race: two requests carrying the
            // same key both passed the scoped lookup in prepareForValidation()
            // before either had written, and the loser hits hasil_culaan's
            // unique index on idempotency_key. Rather than surface that as a
            // 500 the client cannot act on, treat it like a replay of the
            // caller's OWN row — scoped, exactly like the primary lookup, so
            // this can never hand back a record the caller does not own.
            $winner = HasilCulaan::where('idempotency_key', $payload['idempotency_key'])
                ->where('submitted_by', $user->id)
                ->first();

            if ($winner) {
                return response()->json([
                    'success' => true,
                    'culaan' => VoterDataMasker::maskedIdAndIc($winner, $user),
                ], 201);
            }

            // The scoped lookup found nothing, so this was not the caller's
            // own race. Check unscoped, only to route the response, never to
            // disclose anything: if the key exists at all, some OTHER user's
            // row already claimed it and the unique index is what actually
            // fired (Finding 2) — a permanent failure, so 409 rather than the
            // 500 a genuinely unrelated integrity violation should still
            // surface as.
            $keyClaimedByAnotherUser = HasilCulaan::where('idempotency_key', $payload['idempotency_key'])->exists();
            if (! $keyClaimedByAnotherUser) {
                throw $e;
            }

            return response()->json([
                'success' => false,
                'errors' => ['idempotency_key' => ['Kunci idempotency ini telah digunakan oleh pengguna lain.']],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'culaan' => VoterDataMasker::maskedIdAndIc($record, $user),
        ], 201);
    }

    /**
     * Taxonomy for the form's dropdowns and checkbox groups. Single source
     * of truth for phone and web.
     *
     * `pekerjaan` MUST stay identical to the in: rule in
     * StoreMobileCulaanRequest — if they drift, the app offers a choice the
     * server rejects with a 422 the user has no way to fix.
     * MobileCulaanReadTest asserts they match.
     *
     * `jenis_pekerjaan` is NOT a flat list. Create.jsx (~line 946-1258)
     * shows a different, categorised set of "Sektor Pekerjaan" checkboxes
     * depending on which `pekerjaan` the field agent already picked — this
     * is transcribed verbatim, keyed by pekerjaan, preserving the category
     * groupings so the phone can render the identical cascading UI. There
     * is no `in:` rule on jenis_pekerjaan server-side (it's `array|min:1`
     * with `jenis_pekerjaan.*` => `string|max:255`), so unlike pekerjaan
     * this list cannot 422 a legitimate choice — but an inaccurate list
     * would still mislead field agents about what the web app actually
     * offers.
     *
     * `jenis_sumbangan` and `bantuan_lain` are likewise hardcoded literal
     * arrays in Create.jsx (lines ~1315 and ~1459) even though the page
     * also receives `jenisSumbanganList` / `bantuanLainList` master-data
     * props from ReportsController::hasilCulaanCreate() — those two props
     * are destructured but never referenced in the JSX body. That is
     * existing dead wiring in the web page, not something this endpoint
     * should paper over; the JSX's hardcoded arrays are what the web form
     * actually presents, so they are what is transcribed here.
     *
     * `tujuan_sumbangan` is the one list that is genuinely dynamic:
     * Create.jsx maps `tujuanSumbanganList` (a real DB-backed
     * `TujuanSumbangan::all()` query from the controller, ordered by
     * sort_order), not a hardcoded array. Hardcoding it here would drift
     * the moment someone edits Master Data > Tujuan Sumbangan, so this
     * queries the same model instead.
     */
    public function options(): JsonResponse
    {
        $lainLain = ['category' => 'Lain-lain', 'items' => ['Lain-lain']];

        return response()->json([
            'success' => true,
            'options' => [
                'pekerjaan' => ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja'],

                'jenis_pekerjaan' => [
                    'Kerajaan' => [
                        [
                            'category' => 'Jenis Perkhidmatan',
                            'items' => [
                                'Perkhidmatan Awam Persekutuan (Kementerian / Jabatan)',
                                'Perkhidmatan Awam Negeri',
                                'Pihak Berkuasa Tempatan (PBT)',
                            ],
                        ],
                        [
                            'category' => 'Agensi & Badan',
                            'items' => [
                                'Badan Berkanun (MARA, LHDN, KWSP, dll)',
                                'Syarikat Berkaitan Kerajaan (GLC)',
                            ],
                        ],
                        [
                            'category' => 'Keselamatan & Penguatkuasaan',
                            'items' => [
                                'Angkatan Tentera Malaysia (ATM)',
                                'Polis Diraja Malaysia (PDRM)',
                                'Agensi Penguatkuasaan (APMM, JPJ, Imigresen, dll)',
                            ],
                        ],
                        [
                            'category' => 'Pendidikan & Kesihatan',
                            'items' => [
                                'Pendidikan Awam (Guru Sekolah Kerajaan)',
                                'Pendidikan Tinggi Awam (Pensyarah IPTA)',
                                'Kesihatan Awam (Hospital / Klinik Kerajaan)',
                            ],
                        ],
                        $lainLain,
                    ],
                    'Swasta' => [
                        [
                            'category' => 'Korporat & Profesional',
                            'items' => [
                                'Syarikat Korporat / Multinasional',
                                'Profesional (Jurutera, Akauntan, Arkitek, dll)',
                                'Eksekutif / Pengurusan',
                            ],
                        ],
                        [
                            'category' => 'Perdagangan & Perkhidmatan',
                            'items' => [
                                'Peruncitan / Jualan (Retail)',
                                'Perkhidmatan (Servis – bengkel, salon, dll)',
                                'Perhotelan & Pelancongan',
                            ],
                        ],
                        [
                            'category' => 'Industri & Teknikal',
                            'items' => [
                                'Perkilangan / Industri',
                                'Pembinaan / Kontraktor',
                                'Logistik & Pengangkutan',
                            ],
                        ],
                        [
                            'category' => 'Sektor Moden',
                            'items' => [
                                'Teknologi Maklumat / Digital',
                                'Kewangan / Perbankan / Insurans',
                            ],
                        ],
                        [
                            'category' => 'Sosial & Lain-lain',
                            'items' => [
                                'Pendidikan Swasta',
                                'Kesihatan Swasta',
                            ],
                        ],
                        $lainLain,
                    ],
                    'Bekerja Sendiri' => [
                        [
                            'category' => 'Perniagaan & Jualan',
                            'items' => [
                                'Peniaga Kecil (gerai, pasar, online)',
                                'Usahawan / Pemilik Syarikat',
                                'E-dagang (Shopee, TikTok Shop, dll)',
                            ],
                        ],
                        [
                            'category' => 'Perkhidmatan',
                            'items' => [
                                'Freelance (design, IT, content creator, dll)',
                                'Servis (bengkel, tukang, plumbing, wiring, dll)',
                                'Ejen (insurans, hartanah, dll)',
                            ],
                        ],
                        [
                            'category' => 'Pengangkutan & Gig Economy',
                            'items' => [
                                'Pemandu e-hailing (Grab, dll)',
                                'Rider penghantaran (Foodpanda, GrabFood, dll)',
                                'Lori / Van persendirian',
                            ],
                        ],
                        [
                            'category' => 'Sektor Asas',
                            'items' => [
                                'Pertanian',
                                'Penternakan',
                                'Perikanan',
                            ],
                        ],
                        $lainLain,
                    ],
                    'Tidak Bekerja' => [
                        [
                            'category' => 'Status',
                            'items' => [
                                'Pelajar Sekolah',
                                'Pelajar IPT (IPTA / IPTS)',
                                'Suri Rumah',
                                'Pesara Kerajaan',
                                'Pesara Swasta',
                                'Tidak Bekerja / Menganggur',
                            ],
                        ],
                        $lainLain,
                    ],
                ],

                'jenis_sumbangan' => [
                    'Barangan Keperluan Dapur',
                    'Hamper / Sumbangan Perayaan',
                    'Wang Tunai / Kewangan',
                    'Bantuan Perumahan (baik pulih)',
                    'Bantuan Perumahan (bina baharu)',
                    'Bantuan Pendidikan (yuran / kelengkapan sekolah)',
                    'Bantuan Perubatan / Kesihatan',
                    'Bantuan Perniagaan / Ekonomi (modal / peralatan)',
                    'Bantuan Bencana / Kecemasan',
                    'Lain-lain',
                ],

                'tujuan_sumbangan' => TujuanSumbangan::pluck('nama')->values(),

                'bantuan_lain' => [
                    'Jabatan Kebajikan Masyarakat (JKM)',
                    'i-Sejahtera',
                    'Zakat Pulau Pinang (ZPP)',
                    'PERKESO',
                    'Tiada',
                    'Lain-lain',
                ],

                'pemilik_rumah' => ['Sendiri', 'Sewa', 'Keluarga', 'Lain-lain'],
            ],
        ]);
    }

    /**
     * Records submitted by the caller. Backs "Rekod Saya" and lets a
     * reinstalled app reconcile what already reached the server.
     *
     * Filters on submitted_by = caller directly, NOT VoterScopeService — a
     * caller's own records must always be visible to them regardless of
     * Kadun/Parlimen scoping; wrapping this in VoterScopeService would hide
     * a caller's own out-of-Kadun submissions from themselves.
     *
     * submittedBy is eager-loaded scoped to id/name/role (never a bare
     * with('submittedBy')) and the submitted_by key is overwritten after
     * masking to {id, name} only. Without this, mask()'s toArray() call
     * would serialise the submitting staff account's email, telephone,
     * role, and status into the response — the exact PII leak
     * MobileVoterController::maskWithSubmitter() already had to fix once.
     * Every own-record row here is submitted by the caller themselves, so
     * {id, name} is always the caller's own — not a leak of anyone else.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = HasilCulaan::with('submittedBy:id,name,role')
            ->where('submitted_by', $user->id)
            ->orderByDesc('created_at')
            // Tiebreaker: created_at has only second precision, and a
            // synced/imported batch can land multiple rows in the same
            // second. Without a secondary sort, ties fall back to
            // whatever order the DB happens to return, which is not
            // reliably insertion order.
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function ($row) use ($user) {
                $masked = VoterDataMasker::mask($row, $user);
                $masked['submitted_by'] = $row->submittedBy
                    ? ['id' => $row->submittedBy->id, 'name' => $row->submittedBy->name]
                    : null;

                return $masked;
            })
            ->values();

        return response()->json(['success' => true, 'culaan' => $rows]);
    }
}
