<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\KeahlianParti;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Support\Borang14Reference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ScoreboardController extends Controller
{
    private const PENJURU = [2 => '1 vs 1', 3 => '3 Penjuru', 4 => '4 Penjuru', 5 => '5 Penjuru', 6 => '6 Penjuru'];

    /** Parties that make up Pakatan Harapan — used to tally the "PH" figure. */
    private const PH_PARTIES = ['KEADILAN', 'PKR', 'DAP', 'AMANAH', 'MUDA'];

    public function index(Request $request)
    {
        return Inertia::render('Pilihanraya/Scoreboard', [
            'negeriList'     => Negeri::orderBy('nama')->get(['id', 'nama']),
            'parlimenList'   => Bandar::orderBy('nama')->get(['id', 'nama', 'negeri_id']),
            'kadunList'      => Kadun::orderBy('nama')->get(['id', 'nama', 'bandar_id']),
            'partiList'      => KeahlianParti::orderBy('nama')->get(['id', 'nama']),
            'penjuruOptions' => collect(self::PENJURU)->map(fn ($label, $val) => ['value' => (int) $val, 'label' => $label])->values(),
        ]);
    }

    /** Live scoreboard payload — polled by the page. */
    public function data(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
            'penjuru'  => 'nullable|integer|in:2,3,4,5,6',
        ]);

        $reference = Borang14Reference::forKadun((int) $validated['kadun_id']);
        $penjuru = (int) ($validated['penjuru'] ?? 0);

        if (! $reference || ! $penjuru) {
            return response()->json(['hasData' => $reference !== null, 'ready' => false]);
        }

        $form = Borang14Form::where('kadun_id', $validated['kadun_id'])->where('penjuru', $penjuru)->first();
        $board = Scoreboard::where('kadun_id', $validated['kadun_id'])->where('penjuru', $penjuru)->first();

        $parties = $form?->parties ?? [];
        $candidates = collect($board?->candidates ?? [])->keyBy('slot');

        // Live tally: sum of votes per party slot across every pusat/saluran
        // (including Undi Awal/Pos rows).
        $tally = array_fill(1, $penjuru, 0);
        if ($form) {
            $sums = $form->votes()->where('slot', '>=', 1)
                ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')->pluck('total', 'slot');
            foreach ($sums as $slot => $total) {
                if ($slot >= 1 && $slot <= $penjuru) {
                    $tally[$slot] = (int) $total;
                }
            }
        }

        // Registered total = every saluran + early/postal.
        $berdaftar = 0;
        foreach ($reference['daerah_mengundi'] as $dm) {
            $berdaftar += (int) ($dm['jumlah_berdaftar'] ?? 0);
        }
        $berdaftar += (int) ($reference['undi_awal']['berdaftar'] ?? 0);
        $berdaftar += (int) ($reference['undi_pos']['berdaftar'] ?? 0);

        $rows = [];
        $phVotes = 0;
        foreach (range(1, $penjuru) as $slot) {
            $nama = $parties[$slot - 1]['nama'] ?? "Parti {$slot}";
            $isPh = in_array(strtoupper($nama), self::PH_PARTIES, true);
            $undi = $tally[$slot] ?? 0;
            if ($isPh) {
                $phVotes += $undi;
            }
            $rows[] = [
                'slot'      => $slot,
                'parti'     => $nama,
                'is_ph'     => $isPh,
                'calon'     => $candidates[$slot]['nama'] ?? null,
                'gambar'    => isset($candidates[$slot]['gambar']) ? Storage::url($candidates[$slot]['gambar']) : null,
                'undi'      => $undi,
            ];
        }

        $totalKeluar = array_sum($tally);
        $leaderSlot = $totalKeluar > 0 ? collect($rows)->sortByDesc('undi')->first()['slot'] : null;

        return response()->json([
            'hasData'   => true,
            'ready'     => true,
            'title'     => $board?->title ?? 'SCOREBOARD',
            'logo_url'  => $board?->logo_path ? Storage::url($board->logo_path) : asset('images/logo.png'),
            'minima'    => $board?->minima,
            'dun'       => $reference['dun'] ?? null,
            'parlimen'  => $reference['parlimen'] ?? null,
            'negeri'    => $reference['negeri'] ?? null,
            'penjuru_label' => self::PENJURU[$penjuru] ?? '',
            'rows'      => $rows,
            'ph_votes'  => $phVotes,
            'total_keluar'    => $totalKeluar,
            'total_berdaftar' => $berdaftar,
            'leader_slot'     => $leaderSlot,
        ]);
    }

    /** Save presentation settings (title, minima, logo, candidate names & photos). */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'kadun_id'          => 'required|integer|exists:kadun,id',
            'penjuru'           => 'required|integer|in:2,3,4,5,6',
            'title'             => 'nullable|string|max:100',
            'minima'            => 'nullable|integer|min:0|max:100000000',
            'candidates'        => 'array',
            'candidates.*.slot' => 'required|integer|min:1|max:6',
            'candidates.*.nama' => 'nullable|string|max:120',
            'logo'              => 'nullable|image|max:4096',
            'photos'            => 'array',
            'photos.*'          => 'nullable|image|max:4096',
        ]);

        $board = Scoreboard::firstOrNew([
            'kadun_id' => $validated['kadun_id'],
            'penjuru'  => $validated['penjuru'],
        ]);

        $board->title = $validated['title'] ?: 'SCOREBOARD';
        $board->minima = $validated['minima'] ?? null;

        if ($request->hasFile('logo')) {
            if ($board->logo_path) {
                Storage::disk('public')->delete($board->logo_path);
            }
            $board->logo_path = $request->file('logo')->store('scoreboard/logo', 'public');
        }

        // Merge candidate names with any newly-uploaded photos (keyed by slot).
        $existing = collect($board->candidates ?? [])->keyBy('slot');
        $candidates = [];
        foreach ($validated['candidates'] ?? [] as $c) {
            $slot = (int) $c['slot'];
            $gambar = $existing[$slot]['gambar'] ?? null;

            if ($request->hasFile("photos.{$slot}")) {
                if ($gambar) {
                    Storage::disk('public')->delete($gambar);
                }
                $gambar = $request->file("photos.{$slot}")->store('scoreboard/calon', 'public');
            }

            $candidates[] = ['slot' => $slot, 'nama' => $c['nama'] ?? null, 'gambar' => $gambar];
        }
        $board->candidates = $candidates;
        $board->save();

        return response()->json(['ok' => true]);
    }
}
