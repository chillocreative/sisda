<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Borang14Vote;
use App\Models\Kadun;
use App\Models\KeahlianParti;
use App\Models\Negeri;
use App\Support\Borang14Reference;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class Borang14Controller extends Controller
{
    /** Undi Awal & Undi Pos are combined into a single row only for this DUN. */
    private const BULOH_KASAP_KADUN_ID = 41;

    /** Penjuru dropdown → number of party columns. */
    private const PENJURU = [
        2 => '1 vs 1',
        3 => '3 Penjuru',
        4 => '4 Penjuru',
        5 => '5 Penjuru',
        6 => '6 Penjuru',
    ];

    public function index(Request $request)
    {
        return Inertia::render('Pilihanraya/Borang14', [
            'negeriList'   => Negeri::orderBy('nama')->get(['id', 'nama']),
            'parlimenList' => Bandar::orderBy('nama')->get(['id', 'nama', 'negeri_id']),
            'kadunList'    => Kadun::orderBy('nama')->get(['id', 'nama', 'bandar_id']),
            'partiList'    => KeahlianParti::orderBy('nama')->get(['id', 'nama']),
            'penjuruOptions' => collect(self::PENJURU)->map(fn ($label, $val) => ['value' => (int) $val, 'label' => $label])->values(),
        ]);
    }

    /** JSON payload for a chosen DUN (+ optional penjuru): reference geography, saved parties & votes. */
    public function data(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'penjuru'  => 'nullable|integer|in:2,3,4,5,6',
        ]);

        $reference = Borang14Reference::forKadun((int) $validated['kadun_id']);

        $parties = [];
        $votes = [];
        if ($reference && ! empty($validated['penjuru'])) {
            $form = Borang14Form::where('kadun_id', $validated['kadun_id'])
                ->where('penjuru', $validated['penjuru'])
                ->first();

            if ($form) {
                $parties = $form->parties ?? [];
                $votes = $form->votes()
                    ->get(['pusat', 'saluran', 'slot', 'undi'])
                    ->map(fn ($v) => [
                        'key'  => $this->cellKey($v->pusat, $v->saluran, $v->slot),
                        'undi' => $v->undi,
                    ])
                    ->pluck('undi', 'key');
            }
        }

        return response()->json([
            'reference'   => $reference,
            'hasData'     => $reference !== null,
            'parties'     => $parties,
            'votes'       => $votes,
        ]);
    }

    /** Persist the chosen party names for a (DUN, penjuru) scenario. */
    public function saveParties(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'parties'  => 'array',
            'parties.*.slot' => 'required|integer|min:1|max:6',
            'parties.*.keahlian_parti_id' => 'nullable|integer',
            'parties.*.nama' => 'nullable|string|max:100',
        ]);

        $form = Borang14Form::updateOrCreate(
            ['kadun_id' => $validated['kadun_id'], 'penjuru' => $validated['penjuru']],
            ['parties' => $validated['parties'] ?? []],
        );

        return response()->json(['ok' => true, 'form_id' => $form->id]);
    }

    /** Upsert a single editable cell (auto-save on blur). */
    public function saveVote(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'pusat'    => 'nullable|string|max:255',
            'saluran'  => 'required|string|max:50',
            'slot'     => 'required|integer|min:1|max:6',
            'undi'     => 'nullable|integer|min:0|max:1000000',
        ]);

        $form = Borang14Form::firstOrCreate(
            ['kadun_id' => $validated['kadun_id'], 'penjuru' => $validated['penjuru']],
            ['parties' => []],
        );

        Borang14Vote::updateOrCreate(
            [
                'borang14_form_id' => $form->id,
                'pusat'   => $validated['pusat'] ?? '',
                'saluran' => $validated['saluran'],
                'slot'    => $validated['slot'],
            ],
            ['undi' => $validated['undi'] ?? 0],
        );

        return response()->json(['ok' => true]);
    }

    /** Temporary: clear all entered vote figures for a (DUN, penjuru) scenario. */
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
        ]);

        $form = Borang14Form::where('kadun_id', $validated['kadun_id'])
            ->where('penjuru', $validated['penjuru'])
            ->first();

        $form?->votes()->delete();

        return response()->json(['ok' => true]);
    }

    public function pdf(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'penjuru'  => 'required|integer|in:2,3,4,5,6',
            'parti'    => 'array',
            'parti.*'  => 'nullable|string|max:100',
        ]);

        $reference = Borang14Reference::forKadun((int) $validated['kadun_id']);
        abort_if(! $reference, 404, 'Data Borang 14 belum tersedia untuk DUN ini.');

        $form = Borang14Form::where('kadun_id', $validated['kadun_id'])
            ->where('penjuru', $validated['penjuru'])
            ->first();

        // Prefer the party names passed from the page so the PDF column headers
        // match the on-screen dropdown selection exactly; fall back to the saved
        // form when the request doesn't carry them.
        $parties = $form?->parties ?? [];
        if ($request->filled('parti')) {
            $parties = [];
            foreach (array_values($request->input('parti')) as $i => $nama) {
                $parties[] = ['slot' => $i + 1, 'nama' => $nama];
            }
        }
        $votes = $form
            ? $form->votes()->get()->mapWithKeys(fn ($v) => [
                $this->cellKey($v->pusat, $v->saluran, $v->slot) => $v->undi,
            ])->all()
            : [];

        $logo = $this->logoDataUri();

        $pdf = Pdf::loadView('pdf.borang14', [
            'reference' => $reference,
            'penjuru'   => (int) $validated['penjuru'],
            'penjuruLabel' => self::PENJURU[$validated['penjuru']] ?? '',
            'parties'   => $parties,
            'votes'     => $votes,
            'logo'      => $logo,
            'isBulohKasap' => (int) $validated['kadun_id'] === self::BULOH_KASAP_KADUN_ID,
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        $name = 'borang-14-' . str($reference['dun'] ?? 'dun')->slug() . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($name);
    }

    private function cellKey(?string $pusat, string $saluran, int $slot): string
    {
        return ($pusat ?? '') . '|' . $saluran . '|' . $slot;
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}
