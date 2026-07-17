<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMobileCulaanRequest;
use App\Models\DataPengundi;
use App\Models\EditHistory;
use App\Models\HasilCulaan;
use App\Services\CulaanPayloadNormalizer;
use App\Services\VoterDataMasker;
use App\Services\VoterScopeService;
use App\Services\VoterSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MobileCulaanController extends Controller
{
    /**
     * Create a Hasil Culaan from the mobile app.
     *
     * Idempotent by client-generated key: a replay returns the original
     * record untouched. This is what makes the client's automatic retry
     * safe after a lost response.
     */
    public function store(StoreMobileCulaanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Replay of a key we have already honoured — return the original.
        $existing = HasilCulaan::where('idempotency_key', $validated['idempotency_key'])->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'culaan' => ['id' => $existing->id, 'no_ic' => $existing->no_ic],
            ], 201);
        }

        // Parlimen restriction, mirroring ReportsController::hasilCulaanStore.
        if (($user->isUser() || $user->isAdmin()) && $validated['parlimen'] !== ($user->bandar->nama ?? '')) {
            return response()->json([
                'success' => false,
                'errors' => ['parlimen' => ['Rekod ini di luar Parlimen anda.']],
            ], 403);
        }

        // Masked-create: the draft carries '****' placeholders for fields the
        // user was never shown. Swap in the truth from the source record so
        // validation ran against the mask but storage gets real values.
        //
        // The lookup MUST be scoped through VoterScopeService, the same rule
        // MobileVoterController::show() uses. Without it, DataPengundi::find()
        // would load ANY voter row by ID regardless of the caller's Kadun/
        // Parlimen, letting a 'user' caller launder a stranger's real no_ic /
        // no_tel / alamat / poskod / negeri / bandar / umur / bangsa /
        // pendapatan_isi_rumah into a new record under their own Parlimen
        // (the parlimen check above reads the caller's own submitted value,
        // not the source record's). An admin in the caller's Parlimen could
        // then unmask it, and VoterSyncService propagates it into
        // data_pengundi — a cross-Parlimen PII laundering channel plus a
        // data-integrity attack on the voter roll.
        //
        // Missing-vs-out-of-scope MUST return the identical 409 response.
        // Distinguishing them turns this into an existence oracle over
        // sequential DataPengundi IDs, the same class of bug just fixed on
        // MobileVoterController's `q` (see escapeLike()/Finding 2 there).
        if (! empty($validated['locked_source_id'])) {
            $sourceQuery = DataPengundi::where('id', $validated['locked_source_id']);
            VoterScopeService::apply($sourceQuery, $user);
            $source = $sourceQuery->first();

            if (! $source) {
                return response()->json([
                    'success' => false,
                    'errors' => ['locked_source_id' => ['Rekod sumber tidak lagi wujud. Sila cari semula pengundi ini.']],
                ], 409);
            }
            foreach (VoterDataMasker::SENSITIVE_FIELDS as $field) {
                if (($validated[$field] ?? null) === VoterDataMasker::MASK) {
                    $validated[$field] = $source->{$field};
                }
            }
        }

        unset($validated['has_sumbangan'], $validated['locked_source_id']);

        $payload = CulaanPayloadNormalizer::normalize($validated);
        $payload['submitted_by'] = $user->id;
        $payload['sumber'] = 'mobile';

        // The create fans out through VoterSyncService across two tables.
        // CLAUDE.md flags the HTTP layer as transaction-free; this path is not.
        try {
            $record = DB::transaction(function () use ($payload) {
                $record = HasilCulaan::create($payload);
                EditHistory::log('hasil_culaan', $record->id, 'created (mobile)');
                VoterSyncService::syncFromHasilCulaan($record->fresh());

                return $record;
            });
        } catch (QueryException $e) {
            // Backstop for the check-then-act race: two requests carrying the
            // same key both passed the lookup above before either had
            // written, and the loser hits hasil_culaan's unique index on
            // idempotency_key. Rather than surface that as a 500 the client
            // cannot act on, treat it exactly like an ordinary replay and
            // return whichever row actually won. Any other integrity
            // violation is a real bug and must still surface.
            $winner = HasilCulaan::where('idempotency_key', $payload['idempotency_key'])->first();
            if (! $winner) {
                throw $e;
            }

            return response()->json([
                'success' => true,
                'culaan' => ['id' => $winner->id, 'no_ic' => $winner->no_ic],
            ], 201);
        }

        return response()->json([
            'success' => true,
            'culaan' => ['id' => $record->id, 'no_ic' => $record->no_ic],
        ], 201);
    }
}
