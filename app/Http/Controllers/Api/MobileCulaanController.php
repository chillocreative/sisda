<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMobileCulaanRequest;
use App\Models\DataPengundi;
use App\Models\EditHistory;
use App\Models\HasilCulaan;
use App\Services\CulaanPayloadNormalizer;
use App\Services\VoterDataMasker;
use App\Services\VoterSyncService;
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
        if (! empty($validated['locked_source_id'])) {
            $source = DataPengundi::find($validated['locked_source_id']);
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
        $record = DB::transaction(function () use ($payload) {
            $record = HasilCulaan::create($payload);
            EditHistory::log('hasil_culaan', $record->id, 'created (mobile)');
            VoterSyncService::syncFromHasilCulaan($record->fresh());

            return $record;
        });

        return response()->json([
            'success' => true,
            'culaan' => ['id' => $record->id, 'no_ic' => $record->no_ic],
        ], 201);
    }
}
