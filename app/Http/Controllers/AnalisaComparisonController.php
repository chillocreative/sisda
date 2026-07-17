<?php

namespace App\Http\Controllers;

use App\Models\AnalisaComparison;
use App\Models\AnalisaScenario;
use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Services\Pilihanraya\Borang14ScenarioMapper;
use App\Services\Pilihanraya\ElectionComparisonService;
use App\Services\Pilihanraya\ScoresheetExtractor;
use App\Support\Pdf;
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
            'level' => 'required|in:dun,parlimen',
            'bandar_id' => 'required|integer',
            'kadun_id' => 'nullable|integer',
        ]);

        $bandar = Bandar::with('negeri')->find($data['bandar_id']);
        if (! $bandar) {
            return response()->json(['message' => 'Parlimen tidak sah.'], 422);
        }

        $kadun = null;
        if ($data['level'] === 'dun') {
            $kadun = Kadun::find($data['kadun_id']);
            if (! $kadun || (int) $kadun->bandar_id !== (int) $bandar->id) {
                return response()->json(['message' => 'DUN tidak sah untuk parlimen ini.'], 422);
            }
        }

        $comparison = AnalisaComparison::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'level' => $data['level'],
            'negeri' => $bandar->negeri?->nama,
            'bandar_id' => $bandar->id,
            'kadun_id' => $kadun?->id,
            'parlimen' => $bandar->nama,
            'dun' => $kadun?->nama,
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

    public function storeScenario(Request $request, AnalisaComparison $comparison, ScoresheetExtractor $extractor)
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'election_date' => 'required|date',
            'fail' => 'required|file|mimes:xlsx,xls,csv,txt,pdf,jpg,jpeg,png,webp|max:20480',
        ]);

        if ($comparison->scenarios()->count() >= 3) {
            return response()->json(['message' => 'Maksimum 3 senario setiap perbandingan.'], 422);
        }

        @set_time_limit(180);
        $file = $request->file('fail');

        // The AI reads the sheet itself and detects the contesting parties from
        // its own headers; the standard layout is still parsed for free first.
        $extracted = $extractor->extract($file);
        if (! $extracted || empty($extracted['rows'])) {
            return response()->json([
                'message' => 'Tidak dapat membaca scoresheet ini. Untuk fail format bukan standard, aktifkan AI (Tetapan → Claude) supaya sistem boleh membaca data dan mengesan parti yang bertanding secara automatik.',
            ], 422);
        }

        $position = (int) $comparison->scenarios()->max('position') + 1;

        AnalisaScenario::create([
            'analisa_comparison_id' => $comparison->id,
            'position' => $position,
            'label' => $data['label'],
            'election_date' => $data['election_date'],
            'source_filename' => $file->getClientOriginalName(),
            'parsed_rows' => $extracted['rows'],
            'parsed_totals' => $extracted['totals'],
            'row_count' => count($extracted['rows']),
        ]);

        // Re-analysis needed after the scenario set changes.
        $comparison->update(['status' => 'draft']);

        return response()->json(['comparison' => $this->comparisonPayload($comparison->fresh())]);
    }

    /** Borang 14 yang layak untuk kerusi comparison ini. */
    public function borang14Tersedia(AnalisaComparison $comparison)
    {
        $forms = $this->formsForComparison($comparison)
            ->orderByDesc('tahun')->orderBy('jenis_pr')
            ->get()
            ->map(fn (Borang14Form $f) => [
                'id' => $f->id,
                'label' => mb_strtoupper($f->jenis_pr).' '.$f->tahun,
                'tahun' => $f->tahun,
                'jenis_pr' => $f->jenis_pr,
                'status' => $f->status,
                'penjuru' => $f->penjuru,
                // Borang tanpa nama parti tidak boleh dipeta — tandakan supaya user tahu.
                'sedia' => collect($f->parties ?? [])->contains(fn ($p) => trim((string) ($p['nama'] ?? '')) !== ''),
            ]);

        return response()->json(['forms' => $forms]);
    }

    public function storeScenarioFromBorang14(Request $request, AnalisaComparison $comparison, Borang14ScenarioMapper $mapper)
    {
        $data = $request->validate(['form_id' => 'required|integer|exists:borang14_forms,id']);

        if ($comparison->scenarios()->count() >= 3) {
            return response()->json(['message' => 'Maksimum 3 senario setiap perbandingan.'], 422);
        }

        // Kerusi mesti padan — jangan bergantung pada tapisan frontend sahaja.
        $form = $this->formsForComparison($comparison)->where('borang14_forms.id', $data['form_id'])->first();
        if (! $form) {
            return response()->json(['message' => 'Borang 14 ini bukan untuk kawasan perbandingan ini.'], 422);
        }

        try {
            $mapped = $mapper->map($form);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $position = (int) $comparison->scenarios()->max('position') + 1;

        $comparison->scenarios()->create([
            'position' => $position,
            'label' => mb_strtoupper($form->jenis_pr).' '.$form->tahun,
            // Borang 14 simpan tahun sahaja. 1 Jan menjaga isihan; UI papar label, bukan tarikh ini.
            'election_date' => $form->tahun.'-01-01',
            'source_filename' => 'Borang 14 — '.mb_strtoupper($form->jenis_pr).' '.$form->tahun,
            'parsed_rows' => $mapped['rows'],
            'parsed_totals' => $mapped['totals'],
            'row_count' => count($mapped['rows']),
        ]);

        $comparison->update(['status' => 'draft']);

        return response()->json(['comparison' => $this->comparisonPayload($comparison->fresh('scenarios'))]);
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

        // The frontend waits 300s; give PHP the same headroom so a slow AI step
        // completes (or falls back) instead of being killed mid-request.
        @set_time_limit(295);
        @ini_set('max_execution_time', '295');
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

    /** Query borang yang sejajar dengan kerusi comparison. */
    private function formsForComparison(AnalisaComparison $comparison)
    {
        return $comparison->level === 'parlimen'
            ? Borang14Form::forKawasan(Borang14Form::KAWASAN_PARLIMEN, (int) $comparison->bandar_id)
            : Borang14Form::forKawasan(Borang14Form::KAWASAN_DUN, (int) $comparison->kadun_id);
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
                'level' => $c->level,
                'negeri' => $c->negeri,
                'parlimen' => $c->parlimen,
                'dun' => $c->dun,
                'bandar_id' => $c->bandar_id,
                'kadun_id' => $c->kadun_id,
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
            'level' => $comparison->level,
            'negeri' => $comparison->negeri,
            'bandar_id' => $comparison->bandar_id,
            'kadun_id' => $comparison->kadun_id,
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
