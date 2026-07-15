<?php

namespace App\Http\Controllers;

use App\Models\AnalisaComparison;
use App\Models\AnalisaScenario;
use App\Services\Pilihanraya\ElectionComparisonService;
use App\Support\Pdf;
use App\Support\Pilihanraya\JohorElectionData;
use App\Support\Pilihanraya\ScoresheetParser;
use Illuminate\Http\Request;

/**
 * Saveable AI comparison scenarios on the Analisa Keputusan page. Each
 * comparison holds 1–3 scenarios (one election each); the AI compares them
 * with live web-search context and the result is cached for re-opening.
 */
class AnalisaComparisonController extends Controller
{
    public function __construct(protected ElectionComparisonService $service) {}

    public function index()
    {
        return response()->json(['comparisons' => $this->listPayload()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'kawasan_id' => 'required|string',
        ]);

        $kawasan = collect(JohorElectionData::kawasanList())->firstWhere('id', $data['kawasan_id']);
        if (! $kawasan) {
            return response()->json(['message' => 'Kawasan tidak sah.'], 422);
        }

        $comparison = AnalisaComparison::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'kawasan_id' => $kawasan['id'],
            'dun' => $kawasan['dun'],
            'parlimen' => $kawasan['parlimen'],
            'status' => 'draft',
        ]);

        return response()->json(['comparison' => $this->comparisonPayload($comparison)]);
    }

    public function show(AnalisaComparison $comparison)
    {
        return response()->json(['comparison' => $this->comparisonPayload($comparison)]);
    }

    public function destroy(AnalisaComparison $comparison)
    {
        $comparison->delete();

        return response()->json(['comparisons' => $this->listPayload()]);
    }

    public function storeScenario(Request $request, AnalisaComparison $comparison)
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'election_date' => 'required|date',
            'fail' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ]);

        if ($comparison->scenarios()->count() >= 3) {
            return response()->json(['message' => 'Maksimum 3 senario setiap perbandingan.'], 422);
        }

        $parsed = ScoresheetParser::parse($request->file('fail'));
        if (empty($parsed['parsed']['rows'])) {
            return response()->json([
                'message' => 'Format scoresheet tidak dikenali. Pastikan fail mempunyai lajur Daerah Mengundi serta PH dan BN.',
            ], 422);
        }

        $position = (int) $comparison->scenarios()->max('position') + 1;

        AnalisaScenario::create([
            'analisa_comparison_id' => $comparison->id,
            'position' => $position,
            'label' => $data['label'],
            'election_date' => $data['election_date'],
            'source_filename' => $parsed['filename'],
            'parsed_rows' => $parsed['parsed']['rows'],
            'parsed_totals' => $parsed['parsed']['totals'],
            'row_count' => count($parsed['parsed']['rows']),
        ]);

        // Re-analysis needed after the scenario set changes.
        $comparison->update(['status' => 'draft']);

        return response()->json(['comparison' => $this->comparisonPayload($comparison->fresh())]);
    }

    public function destroyScenario(AnalisaComparison $comparison, AnalisaScenario $scenario)
    {
        abort_unless($scenario->analisa_comparison_id === $comparison->id, 404);
        $scenario->delete();
        $comparison->update(['status' => 'draft']);

        return response()->json(['comparison' => $this->comparisonPayload($comparison->fresh())]);
    }

    public function analyze(Request $request, AnalisaComparison $comparison)
    {
        if ($comparison->scenarios()->count() < 1) {
            return response()->json(['message' => 'Tambah sekurang-kurangnya satu senario dahulu.'], 422);
        }

        @set_time_limit(240);
        $result = $this->service->analyze($comparison, $request->user()->id);

        return response()->json([
            'comparison' => $this->comparisonPayload($comparison->fresh()),
            'status' => $result['status'],
        ]);
    }

    public function pdf(AnalisaComparison $comparison)
    {
        abort_unless(! empty($comparison->ai_result), 404);

        return Pdf::download('pdf.analisa-comparison', [
            'comparison' => $comparison,
            'result' => ElectionComparisonService::sanitizeComparison($comparison->ai_result),
            'facts' => $comparison->fact_payload ?? [],
        ], 'analisa-perbandingan-'.$comparison->id.'.pdf', 'a4', 'portrait');
    }

    /* ----------------------------------------------------------------
     |  Shaping
     * ---------------------------------------------------------------- */

    private function listPayload(): array
    {
        return AnalisaComparison::withCount('scenarios')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'dun' => $c->dun,
                'status' => $c->status,
                'ai_status' => $c->ai_status,
                'scenario_count' => $c->scenarios_count,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ])->all();
    }

    private function comparisonPayload(AnalisaComparison $comparison): array
    {
        $comparison->loadMissing('scenarios');

        return [
            'id' => $comparison->id,
            'title' => $comparison->title,
            'kawasan_id' => $comparison->kawasan_id,
            'dun' => $comparison->dun,
            'parlimen' => $comparison->parlimen,
            'status' => $comparison->status,
            'ai_status' => $comparison->ai_status,
            'ai_model' => $comparison->ai_model,
            'ai_generated_at' => $comparison->ai_generated_at?->toIso8601String(),
            'web_search_count' => $comparison->web_search_count,
            'updated_at' => $comparison->updated_at?->toIso8601String(),
            'fact_payload' => $comparison->fact_payload,
            'ai_result' => $comparison->ai_result,
            'scenarios' => $comparison->scenarios->map(fn ($s) => [
                'id' => $s->id,
                'position' => $s->position,
                'label' => $s->label,
                'election_date' => $s->election_date?->format('Y-m-d'),
                'source_filename' => $s->source_filename,
                'row_count' => $s->row_count,
                'parsed_totals' => $s->parsed_totals,
            ])->all(),
        ];
    }
}
