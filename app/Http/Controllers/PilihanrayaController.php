<?php

namespace App\Http\Controllers;

use App\Exports\PilihanrayaBriefingExport;
use App\Models\KeahlianParti;
use App\Services\Pilihanraya\ElectionAnalyticsService;
use App\Services\Pilihanraya\ElectionEarlyWarningService;
use App\Services\Pilihanraya\ElectionForecastService;
use App\Support\PartyCode;
use App\Support\SeatScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pilihanraya — Digital War Room & Election Intelligence Center.
 *
 * Laluan dijaga oleh middleware `pilihanraya` (EnsurePilihanrayaAccess):
 * super_admin, admin dan pengarah_dun. Skop kawasan bagi peranan yang
 * dikurung kepada satu Parlimen dipaksa dalam kekangKawasan() di bawah —
 * SETIAP hujung data melalui f().
 */
class PilihanrayaController extends Controller
{
    public function __construct(
        protected ElectionAnalyticsService $analytics,
        protected ElectionEarlyWarningService $earlyWarning,
        protected ElectionForecastService $forecast,
    ) {}

    /**
     * Fallback line-up for the Simulasi Pilihanraya table, used only when
     * Data Induk (Keanggotaan Parti) has no rows — the page must never render
     * an empty party dropdown.
     */
    private const SIMULASI_PARTIES = [
        ['kod' => 'PH', 'nama' => 'Pakatan Harapan'],
        ['kod' => 'PN', 'nama' => 'Perikatan Nasional'],
        ['kod' => 'BN', 'nama' => 'Barisan Nasional'],
        ['kod' => 'PEJUANG', 'nama' => 'PEJUANG'],
        ['kod' => 'MUDA', 'nama' => 'MUDA'],
        ['kod' => 'BEBAS', 'nama' => 'Calon Bebas'],
    ];

    /** Contest sizes: penjuru => label (2 = 1 lawan 1). */
    private const PENJURU_OPTIONS = [
        2 => '1 lawan 1',
        3 => '3 Penjuru',
        4 => '4 Penjuru',
        5 => '5 Penjuru',
        6 => '6 Penjuru',
    ];

    /* ------------------------------ Pages ------------------------------ */

    public function warRoom(Request $request)
    {
        $f = $this->f($request);

        return Inertia::render('Pilihanraya/WarRoom', array_merge(
            ['overview' => $this->analytics->overview($f)],
            $this->analytics->filterLists($request->user()),
        ));
    }

    public function simulasi(Request $request)
    {
        return Inertia::render('Pilihanraya/Simulasi', array_merge(
            [
                'simulasiParties' => $this->simulasiParties(),
                'penjuruOptions' => collect(self::PENJURU_OPTIONS)
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values(),
            ],
            $this->analytics->filterLists($request->user()),
        ));
    }

    /**
     * Selectable parties, sourced from Data Induk → Keanggotaan Parti so the
     * list is maintained by users rather than hardcoded here. Names are
     * de-duplicated case-insensitively (the master table is per-Bandar, so the
     * same coalition appears once per Parlimen) and keep the master table's
     * own sort order. Falls back to the built-in line-up when it is empty.
     *
     * @return array<int, array{kod:string, nama:string}>
     */
    private function simulasiParties(): array
    {
        $names = KeahlianParti::pluck('nama')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => mb_strtoupper($n))
            ->values();

        $parties = PartyCode::forNames($names);

        return $parties !== [] ? $parties : self::SIMULASI_PARTIES;
    }

    /** Latest DPPR voter counts by kaum for the selected Parlimen/DUN. */
    public function simulasiPengundi(Request $request)
    {
        return response()->json($this->analytics->pengundiByKaum($this->f($request)));
    }

    /**
     * Professional PDF of a Simulasi Pilihanraya scenario. The scenario lives
     * only in the browser (session-only), so it is POSTed back and re-sanitised
     * server-side before dompdf renders it.
     */
    public function simulasiPdf(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'penjuru_label' => 'nullable|string|max:60',
            'kawasan' => 'nullable|string|max:160',
            'parties' => 'required|array|min:2|max:6',
            'parties.*.kod' => 'required|string|max:20',
            'parties.*.nama' => 'required|string|max:80',
            'parties.*.color' => 'nullable|string|max:9',
            'pengundi' => 'required|array',
            'andaian' => 'required|array|max:4',
            'keputusan' => 'required|array|max:4',
            'totals' => 'required|array',
        ]);

        // Coerce a colour to a safe #rrggbb string (defends the inline styles).
        $safeColor = fn ($c) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c) ? $c : '#64748b';

        $parties = collect($data['parties'])->map(fn ($p) => [
            'kod' => (string) $p['kod'],
            'nama' => (string) $p['nama'],
            'color' => $safeColor($p['color'] ?? null),
        ])->values()->all();

        $num = fn ($v) => (float) ($v ?? 0);
        $partyCount = count($parties);
        $undiArr = fn ($a) => collect($a ?? [])->take($partyCount)->map($num)->all();

        $andaian = collect($data['andaian'])->map(fn ($r) => [
            'kaum' => (string) ($r['kaum'] ?? ''),
            'turnout' => $num($r['turnout'] ?? 0),
            'sokongan' => collect($r['sokongan'] ?? [])->map($num)->all(),
            'baki_kod' => (string) ($r['baki_kod'] ?? ''),
        ])->all();

        $keputusan = collect($data['keputusan'])->map(fn ($r) => [
            'kaum' => (string) ($r['kaum'] ?? ''),
            'pengundi' => $num($r['pengundi'] ?? 0),
            'keluar' => $num($r['keluar'] ?? 0),
            'undi' => $undiArr($r['undi'] ?? []),
        ])->all();

        $totals = $data['totals'];
        $totals = [
            'undi' => $undiArr($totals['undi'] ?? []),
            'keluar' => $num($totals['keluar'] ?? 0),
            'pengundi' => $num($totals['pengundi'] ?? 0),
            'perlu' => $num($totals['perlu'] ?? 0),
            'turnout_all' => $num($totals['turnout_all'] ?? 0),
            'majoriti' => $num($totals['majoriti'] ?? 0),
            'status' => (string) ($totals['status'] ?? '—'),
            'winner' => isset($totals['winner']) && is_array($totals['winner']) ? [
                'kod' => (string) ($totals['winner']['kod'] ?? ''),
                'nama' => (string) ($totals['winner']['nama'] ?? ''),
            ] : null,
        ];

        $pdf = Pdf::loadView('pdf.simulasi-pilihanraya', [
            'title' => $data['title'],
            'penjuruLabel' => $data['penjuru_label'] ?? '',
            'kawasan' => $data['kawasan'] ?? 'Data manual',
            'parties' => $parties,
            'pengundi' => $data['pengundi'],
            'andaian' => $andaian,
            'keputusan' => $keputusan,
            'totals' => $totals,
            'genAt' => now()->translatedFormat('d F Y, g:i A'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        return $pdf->download('simulasi-pilihanraya-'.now()->format('Y-m-d').'.pdf');
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

        $this->kekangSkopTaklimat($validated['level'], $validated['scope_id'] ?? null);

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
        return $this->analytics->resolveFilters($this->kekangKawasan($request->only([
            'negeri_id', 'parlimen_id', 'kadun_id', 'tarikh_dari', 'tarikh_hingga',
            'umur_dari', 'umur_hingga', 'status_pengundi',
        ])));
    }

    /**
     * Kurung skop taklimat bagi peranan yang berskop Parlimen.
     *
     * briefing() ialah SATU-SATUNYA hujung dalam pengawal ini yang TIDAK
     * melalui f(): ia menerima `level` + `scope_id` terus daripada permintaan
     * dan memanggil ElectionForecastService::briefing(), yang memanggil
     * resolveFilters() sendiri. Jadi kekangKawasan() tidak pernah menyentuhnya
     * dan `level=national` mencapai lapisan pertanyaan.
     *
     * `national` dan `negeri` ditolak terus bagi peranan yang dikurung: kedua-
     * duanya LEBIH LUAS daripada satu Parlimen, jadi tiada kerusi yang boleh
     * ditegaskan terhadapnya.
     */
    private function kekangSkopTaklimat(string $level, ?string $scopeId): void
    {
        $user = auth()->user();

        if (! SeatScope::parlimenKurungan($user)) {
            return; // Peranan lain: tiada perubahan langsung.
        }

        abort_if(
            in_array($level, ['national', 'negeri'], true),
            403,
            'Taklimat hanya boleh dijana untuk Parlimen anda dan DUN di bawahnya.',
        );

        SeatScope::assert(
            $user,
            $level === 'parlimen' ? SeatScope::PARLIMEN : SeatScope::DUN,
            (int) $scopeId,
        );
    }

    /**
     * Kurung penapis kawasan kepada Parlimen pengguna bagi peranan yang
     * berskop Parlimen (kini `pengarah_dun` sahaja — lihat
     * SeatScope::parlimenKurungan()).
     *
     * SETIAP hujung data dalam pengawal ini melalui f(), jadi ini satu-satunya
     * titik sekatan yang diperlukan. Penting: parlimen_id DIPAKSA, bukan
     * sekadar dilalaikan — kalau tidak, menaip `?parlimen_id=` Parlimen orang
     * lain akan menembusinya.
     *
     * Peranan lain melalui tanpa sebarang perubahan.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function kekangKawasan(array $input): array
    {
        $user = auth()->user();
        $bandar = SeatScope::parlimenKurungan($user);

        if (! $bandar) {
            return $input;
        }

        $input['parlimen_id'] = $bandar->id;
        $input['negeri_id'] = $bandar->negeri_id;

        // DUN dibenarkan hanya jika ia berada di bawah Parlimen itu; kalau
        // tidak, tapisan DUN digugurkan dan paparan kekal pada Parlimen penuh.
        if (! empty($input['kadun_id'])
            && ! SeatScope::allows($user, SeatScope::DUN, (int) $input['kadun_id'])) {
            unset($input['kadun_id']);
        }

        return $input;
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

    public function forecastPdf()
    {
        $latest = $this->forecast->latestForecast();

        abort_if(! $latest, 404, 'Tiada ramalan dijana lagi. Jana ramalan dahulu di Pusat Simulasi.');

        $pdf = Pdf::loadView('pdf.forecast', [
            'result'       => $latest->result ?? [],
            'generated_at' => $latest->created_at->format('d/m/Y H:i'),
            'status'       => $latest->status ?? 'ai',
            'scope'        => $latest->scope_name ?? 'Keseluruhan',
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        return $pdf->download('analisis-strategik-' . now()->format('Y-m-d') . '.pdf');
    }
}
