<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataPengundi;
use App\Services\VoterDataMasker;
use App\Services\VoterScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileVoterController extends Controller
{
    private const MAX_RESULTS = 30;

    /**
     * Search the voter roll by name or IC, scoped to the caller's role.
     *
     * Every payload goes through VoterDataMasker. The submittedBy relation
     * is eager-loaded (columns limited to id/name/role) purely as an N+1
     * query fix — lazy loading is not disabled in this app, so isLocked()
     * would resolve the relation transparently and correctly even without
     * the eager-load. It is NOT a security control.
     */
    public function search(Request $request): JsonResponse
    {
        $validator = validator($request->all(), ['q' => 'required|string|min:3'], [
            'q.required' => 'Sila masukkan kata carian.',
            'q.string' => 'Kata carian tidak sah.',
            'q.min' => 'Sila masukkan sekurang-kurangnya 3 aksara.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $q = $request->query('q');
        $user = $request->user();
        $canUnmask = VoterDataMasker::canUnmask($user);

        $query = DataPengundi::with('submittedBy:id,name,role');
        VoterScopeService::apply($query, $user);

        $query->where(function ($sub) use ($q, $canUnmask) {
            $sub->whereRaw('nama LIKE ? ESCAPE ?', ['%'.$this->escapeLike($q).'%', '\\']);

            // Finding 2: for viewers who cannot already see ICs, `no_ic`
            // must only ever be matched on a full, exact 12-digit value.
            // A `like '%...%'` substring match on a masked column is an
            // oracle — a 'user' caller could recover a full IC one digit
            // at a time by observing which queries return the row (the
            // reviewer did it in ~100 requests). Requiring an exact
            // 12-digit match still serves the real field-app flow (OCR
            // scans a card -> full IC -> exact lookup) while making the
            // incremental attack infeasible (10^12 guesses). Viewers who
            // can already unmask ICs (admin/super_user/super_admin) keep
            // the original substring behaviour since there's nothing to
            // leak from them.
            if ($canUnmask) {
                $sub->orWhereRaw('no_ic LIKE ? ESCAPE ?', ['%'.$this->escapeLike($q).'%', '\\']);
            } elseif (preg_match('/^\d{12}$/', $q) === 1) {
                $sub->orWhere('no_ic', $q);
            }
        });

        $voters = $query->limit(self::MAX_RESULTS)->get()
            ->map(fn ($row) => $this->maskWithSubmitter($row, $user))
            ->values();

        return response()->json(['success' => true, 'voters' => $voters]);
    }

    public function show(Request $request, string $ic): JsonResponse
    {
        $user = $request->user();

        $query = DataPengundi::with('submittedBy:id,name,role')->where('no_ic', $ic);
        VoterScopeService::apply($query, $user);

        $voter = $query->first();

        if (! $voter) {
            return response()->json([
                'success' => false,
                'errors' => ['no_ic' => ['Pengundi tidak dijumpai.']],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'voter' => $this->maskWithSubmitter($voter, $user),
        ]);
    }

    /**
     * Mask a record and replace the eager-loaded submittedBy relation with
     * a minimal array. Without this, VoterDataMasker::mask()'s toArray()
     * call would serialise the submitting staff account's email,
     * telephone, role, status, and last_login_* fields into the response
     * alongside the masked voter fields, and would clobber the
     * submitted_by FK integer with an object the Flutter client doesn't
     * expect. Same convention as ReportsController and
     * UploadDatabaseController.
     */
    private function maskWithSubmitter($record, $user): array
    {
        $masked = VoterDataMasker::mask($record, $user);
        $masked['submitted_by'] = $record->submittedBy
            ? ['id' => $record->submittedBy->id, 'name' => $record->submittedBy->name]
            : null;

        return $masked;
    }

    /**
     * Escape LIKE metacharacters so a search term is matched literally.
     * Without this, `%` and `_` in `q` let a caller widen or short-circuit
     * the search (e.g. q=%%% matches every in-scope row), defeating the
     * min:3 gate and, combined with Finding 2, seeding the IC oracle.
     *
     * Every call site pairs this with an explicit `LIKE ? ESCAPE '\\'`
     * clause rather than Eloquent's `where(..., 'like', ...)`. MySQL
     * defaults its LIKE escape character to `\` already, but SQLite (the
     * CI driver) has no default escape character at all, so a bare
     * `where('col', 'like', ...)` would leave `\_` as a literal backslash
     * followed by a still-live `_` wildcard under SQLite while behaving
     * correctly under MySQL — a silent cross-driver divergence. The
     * explicit ESCAPE clause is honoured identically by both.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
