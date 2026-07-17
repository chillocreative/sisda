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
     * MUST stay eager-loaded: without it isLocked() sees a null relation,
     * returns false, and ships unmasked PII to the phone silently.
     */
    public function search(Request $request): JsonResponse
    {
        $validator = validator($request->all(), ['q' => 'required|string|min:3']);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => ['q' => ['Sila masukkan sekurang-kurangnya 3 aksara.']],
            ], 422);
        }

        $q = $request->query('q');
        $user = $request->user();

        $query = DataPengundi::with('submittedBy');
        VoterScopeService::apply($query, $user);

        $query->where(function ($sub) use ($q) {
            $sub->where('nama', 'like', "%{$q}%")
                ->orWhere('no_ic', 'like', "%{$q}%");
        });

        $voters = $query->limit(self::MAX_RESULTS)->get()
            ->map(fn ($row) => VoterDataMasker::mask($row, $user))
            ->values();

        return response()->json(['success' => true, 'voters' => $voters]);
    }

    public function show(Request $request, string $ic): JsonResponse
    {
        $user = $request->user();

        $query = DataPengundi::with('submittedBy')->where('no_ic', $ic);
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
            'voter' => VoterDataMasker::mask($voter, $user),
        ]);
    }
}
