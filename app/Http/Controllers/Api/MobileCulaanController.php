<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMobileCulaanRequest;
use App\Models\EditHistory;
use App\Models\HasilCulaan;
use App\Services\CulaanPayloadNormalizer;
use App\Services\VoterDataMasker;
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
}
