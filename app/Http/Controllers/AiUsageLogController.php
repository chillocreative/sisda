<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * AI activity log — token usage and estimated cost of Claude API calls.
 * super_admin only.
 */
class AiUsageLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $summary = AiUsageLog::selectRaw('
            COUNT(*) AS calls,
            COALESCE(SUM(input_tokens), 0) AS input_tokens,
            COALESCE(SUM(output_tokens), 0) AS output_tokens,
            COALESCE(SUM(input_tokens + output_tokens + cache_creation_input_tokens + cache_read_input_tokens), 0) AS total_tokens,
            COALESCE(SUM(cost_usd), 0) AS cost_usd
        ')->first();

        $byModel = AiUsageLog::selectRaw('
            model,
            COUNT(*) AS calls,
            COALESCE(SUM(input_tokens), 0) AS input_tokens,
            COALESCE(SUM(output_tokens), 0) AS output_tokens,
            COALESCE(SUM(cost_usd), 0) AS cost_usd
        ')->groupBy('model')->orderByDesc('cost_usd')->get();

        $logs = AiUsageLog::with('user:id,name')
            ->orderByDesc('id')
            ->paginate(25)
            ->through(fn ($l) => [
                'id' => $l->id,
                'model' => $l->model,
                'context' => $l->context,
                'input_tokens' => $l->input_tokens,
                'output_tokens' => $l->output_tokens,
                'cost_usd' => (float) $l->cost_usd,
                'user' => $l->user?->name,
                'created_at' => $l->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Settings/AiUsage', [
            'summary' => [
                'calls' => (int) $summary->calls,
                'input_tokens' => (int) $summary->input_tokens,
                'output_tokens' => (int) $summary->output_tokens,
                'total_tokens' => (int) $summary->total_tokens,
                'cost_usd' => (float) $summary->cost_usd,
            ],
            'byModel' => $byModel,
            'logs' => $logs,
        ]);
    }
}
