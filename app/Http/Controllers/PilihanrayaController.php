<?php

namespace App\Http\Controllers;

use App\Exports\PilihanrayaBriefingExport;
use App\Services\Pilihanraya\ElectionAnalyticsService;
use App\Services\Pilihanraya\ElectionEarlyWarningService;
use App\Services\Pilihanraya\ElectionForecastService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pilihanraya — Digital War Room & Election Intelligence Center.
 * All routes are super_admin-only (enforced by the route group).
 */
class PilihanrayaController extends Controller
{
    public function __construct(
        protected ElectionAnalyticsService $analytics,
        protected ElectionEarlyWarningService $earlyWarning,
        protected ElectionForecastService $forecast,
    ) {}

    /* ------------------------------ Pages ------------------------------ */

    public function warRoom(Request $request)
    {
        $f = $this->f($request);

        return Inertia::render('Pilihanraya/WarRoom', array_merge(
            ['overview' => $this->analytics->overview($f)],
            $this->analytics->filterLists(),
        ));
    }

    public function simulasi(Request $request)
    {
        $latest = $this->forecast->latestForecast();

        return Inertia::render('Pilihanraya/Simulasi', array_merge(
            [
                'latestForecast' => $latest ? [
                    'status' => $latest->status,
                    'result' => $latest->result,
                    'generated_at' => $latest->created_at->toIso8601String(),
                ] : null,
            ],
            $this->analytics->filterLists(),
        ));
    }

    /* -------------------------- War Room data -------------------------- */

    public function overview(Request $request)
    {
        return response()->json($this->analytics->overview($this->f($request)));
    }

    public function composition(Request $request)
    {
        return response()->json($this->analytics->composition($this->f($request)));
    }

    public function sentiment(Request $request)
    {
        return response()->json($this->analytics->sentiment($this->f($request)));
    }

    public function seatScores(Request $request)
    {
        return response()->json($this->analytics->seatScores($this->f($request)));
    }

    public function battlefield(Request $request)
    {
        return response()->json($this->analytics->battlefield($this->f($request)));
    }

    public function alerts(Request $request)
    {
        return response()->json($this->earlyWarning->scan($this->f($request)));
    }

    /* -------------------------- Simulation ----------------------------- */

    public function baseline(Request $request)
    {
        return response()->json($this->analytics->baseline($this->f($request)));
    }

    public function runForecast(Request $request)
    {
        return response()->json(
            $this->forecast->forecast($this->f($request), $request->user()->id)
        );
    }

    public function warGame(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:1000',
            'sliders' => 'nullable|array',
        ]);

        return response()->json($this->forecast->warGame(
            $this->f($request),
            $validated['question'],
            $validated['sliders'] ?? [],
            $request->user()->id,
        ));
    }

    public function resources(Request $request)
    {
        return response()->json(
            $this->forecast->resourceAllocation($this->f($request), $request->user()->id)
        );
    }

    public function briefing(Request $request)
    {
        $validated = $request->validate([
            'level' => 'required|in:national,negeri,parlimen,kadun',
            'scope_id' => 'required_unless:level,national|nullable|integer',
        ]);

        // Fail loudly on a stale/unknown scope id — silently resolving
        // to null would widen a seat briefing to national scope.
        if ($validated['level'] !== 'national') {
            $exists = match ($validated['level']) {
                'negeri' => \App\Models\Negeri::whereKey($validated['scope_id'])->exists(),
                'parlimen' => \App\Models\Bandar::whereKey($validated['scope_id'])->exists(),
                'kadun' => \App\Models\Kadun::whereKey($validated['scope_id'])->exists(),
            };
            if (! $exists) {
                abort(422, 'Kawasan tidak sah atau telah dipadam.');
            }
        }

        return response()->json($this->forecast->briefing(
            $validated['level'],
            $validated['scope_id'] ?? null,
            $request->user()->id,
        ));
    }

    /* ---------------------------- Exports ------------------------------ */

    public function exportBriefingExcel(Request $request)
    {
        [$briefing, $seatScores] = $this->exportPayload($request);

        return Excel::download(
            new PilihanrayaBriefingExport($briefing, $seatScores),
            'taklimat-pilihanraya-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function exportBriefingPdf(Request $request)
    {
        [$briefing, $seatScores] = $this->exportPayload($request);

        return Pdf::loadView('pdf.pilihanraya-briefing', [
            'briefing' => $briefing,
            'seatScores' => $seatScores,
        ])->setPaper('a4')->download('taklimat-pilihanraya-'.now()->format('Y-m-d').'.pdf');
    }

    /* ---------------------------- Helpers ------------------------------ */

    private function f(Request $request): array
    {
        return $this->analytics->resolveFilters($request->only([
            'negeri_id', 'parlimen_id', 'kadun_id', 'tarikh_dari', 'tarikh_hingga',
        ]));
    }

    /**
     * The export endpoints accept the rendered briefing back from the
     * client — re-sanitise it server-side before it reaches dompdf or
     * the spreadsheet writer, and coerce seat-score numerics.
     */
    private function exportPayload(Request $request): array
    {
        $validated = $request->validate([
            'briefing' => 'required|array',
            'seatScores' => 'nullable|array',
        ]);

        $briefing = \App\Services\Pilihanraya\ElectionForecastService::sanitizeBriefing($validated['briefing']);
        if (! $briefing) {
            abort(422, 'Kandungan taklimat tidak sah.');
        }

        $seatScores = collect($validated['seatScores'] ?? [])
            ->filter(fn ($s) => is_array($s))
            ->map(fn ($s) => [
                'kerusi' => is_scalar($s['kerusi'] ?? null) ? (string) $s['kerusi'] : '',
                'jenis' => is_scalar($s['jenis'] ?? null) ? (string) $s['jenis'] : 'kadun',
                'daftar' => (int) ($s['daftar'] ?? 0),
                'culaan' => (int) ($s['culaan'] ?? 0),
                'liputan' => (float) ($s['liputan'] ?? 0),
                'putih' => (int) ($s['putih'] ?? 0),
                'hitam' => (int) ($s['hitam'] ?? 0),
                'kelabu' => (int) ($s['kelabu'] ?? 0),
                'skor' => (int) ($s['skor'] ?? 0),
                'kategori' => is_scalar($s['kategori'] ?? null) ? (string) $s['kategori'] : '',
                'tren_putih_30h' => is_numeric($s['tren_putih_30h'] ?? null) ? (float) $s['tren_putih_30h'] : null,
            ])
            ->values()
            ->all();

        return [$briefing, $seatScores];
    }

    /* -------------------------- User Manuals -------------------------- */

    public function manualSimulasi()
    {
        $pdf = Pdf::loadView('pdf.manual-simulasi')
            ->setPaper('a4', 'portrait')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        return $pdf->download('manual-pusat-simulasi-pilihanraya.pdf');
    }
}
