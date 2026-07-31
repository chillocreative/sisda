<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Support\Borang14Reference;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        ]);
    }

    /** Live scoreboard payload — polled by the authenticated page. */
    public function data(Request $request)
    {
        $validated = $request->validate([
            'kadun_id' => 'required|integer|exists:kadun,id',
        ]);

        return $this->liveJson($this->boardPayload((int) $validated['kadun_id']));
    }

    /** Public, no-login results page at /scoreboard/{kadun?}. */
    public function publicShow(Request $request, ?int $kadun = null)
    {
        $board = ($kadun && Kadun::whereKey($kadun)->exists()) ? $this->boardPayload($kadun) : null;

        return Inertia::render('Public/Scoreboard', [
            'kadunId'      => $board ? $kadun : null,
            'initialBoard' => $board,
            'negeriList'   => Negeri::orderBy('nama')->get(['id', 'nama']),
            'parlimenList' => Bandar::orderBy('nama')->get(['id', 'nama', 'negeri_id']),
            'kadunList'    => Kadun::orderBy('nama')->get(['id', 'nama', 'bandar_id']),
        ]);
    }

    /** Public JSON polled by the public results page. */
    public function publicData(int $kadun)
    {
        abort_unless(Kadun::whereKey($kadun)->exists(), 404);

        return $this->liveJson($this->boardPayload($kadun));
    }

    /** JSON that must never be cached — polled live during vote entry. */
    private function liveJson(array $payload)
    {
        return response()->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** Build the live scoreboard payload for a DUN (shared by auth + public). */
    private function boardPayload(int $kadunId): array
    {
        $reference = Borang14Reference::forKadun($kadunId);

        if (! $reference) {
            return ['hasData' => false, 'ready' => false];
        }

        // The penjuru is taken from whatever Borang 14 scenario exists for this
        // DUN (most recently worked on) — no manual selection on the scoreboard.
        $form = Borang14Form::where('kawasan_type', 'dun')
            ->where('kawasan_id', $kadunId)
            ->latest('updated_at')
            ->first();

        if (! $form) {
            return ['hasData' => true, 'ready' => false, 'needsBorang14' => true];
        }

        $penjuru = (int) $form->penjuru;
        $board = Scoreboard::where('borang14_form_id', $form->id)->first();

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

        // Registered total = every saluran + early/postal. Borang14Reference
        // knows its own two shapes and returns null when the roll carries no
        // figures at all — null is "tidak diketahui", NOT zero, and the board
        // renders it as "—". Never coerce it with ?? 0: doing so published a
        // fabricated "0.0% keluar mengundi" on every seat without a curated
        // reference file.
        $berdaftar = Borang14Reference::jumlahBerdaftar($reference);

        $rows = [];
        $phVotes = 0;
        foreach (range(1, $penjuru) as $slot) {
            // The party name always follows the Borang 14 party dropdown.
            $nama = $parties[$slot - 1]['nama'] ?? "Parti {$slot}";
            $isPh = in_array(strtoupper((string) $nama), self::PH_PARTIES, true);
            $undi = $tally[$slot] ?? 0;
            if ($isPh) {
                $phVotes += $undi;
            }
            $rows[] = [
                'slot'   => $slot,
                'parti'  => $nama,
                'is_ph'  => $isPh,
                'calon'  => $candidates[$slot]['nama'] ?? null,
                'gambar' => ! empty($candidates[$slot]['gambar']) ? asset($candidates[$slot]['gambar']) : null,
                'undi'   => $undi,
            ];
        }

        $totalKeluar = array_sum($tally);
        $leaderSlot = $totalKeluar > 0 ? collect($rows)->sortByDesc('undi')->first()['slot'] : null;

        return [
            'hasData'   => true,
            'ready'     => true,
            'penjuru'   => $penjuru,
            'title'     => $board?->title ?? 'SCOREBOARD',
            'logo_url'  => $board?->logo_path ? asset($board->logo_path) : asset('images/logo.png'),
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
        ];
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
            'logo'              => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:4096',
            'photos'            => 'array',
            'photos.*'          => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:4096',
        ]);

        // Resolve the (DUN, penjuru) pair to the Borang 14 form it belongs to —
        // scoreboards are now keyed by borang14_form_id, not kadun_id/penjuru
        // directly. Same "most recently worked on" resolution as boardPayload().
        $form = Borang14Form::where('kawasan_type', 'dun')
            ->where('kawasan_id', $validated['kadun_id'])
            ->latest('updated_at')
            ->first();

        abort_unless($form, 404, 'Sila isi Borang 14 dahulu untuk DUN ini.');

        $board = Scoreboard::firstOrNew(['borang14_form_id' => $form->id]);

        $board->title = $validated['title'] ?: 'SCOREBOARD';
        $board->minima = $validated['minima'] ?? null;

        if ($request->hasFile('logo')) {
            $this->deletePublic($board->logo_path);
            $board->logo_path = $this->storePublic($request->file('logo'), 'scoreboard/logo');
        }

        // Merge candidate names with any newly-uploaded photos (keyed by slot).
        $existing = collect($board->candidates ?? [])->keyBy('slot');
        $candidates = [];
        foreach ($validated['candidates'] ?? [] as $c) {
            $slot = (int) $c['slot'];
            $gambar = $existing[$slot]['gambar'] ?? null;

            if ($request->hasFile("photos.{$slot}")) {
                $this->deletePublic($gambar);
                $gambar = $this->storePublic($request->file("photos.{$slot}"), 'scoreboard/calon');
            }

            $candidates[] = ['slot' => $slot, 'nama' => $c['nama'] ?? null, 'gambar' => $gambar];
        }
        $board->candidates = $candidates;
        $board->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Store an uploaded image straight under public/ so it is served by the
     * web server via asset() — no dependency on the storage:link symlink,
     * which may not exist on the deployment target.
     */
    private function storePublic(UploadedFile $file, string $dir): string
    {
        // Derive the extension from the file *content* (never the client-supplied
        // name) and pin it to an image allowlist, so a polyglot named e.g. .php
        // can't be written into the webroot and executed.
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower($file->guessExtension() ?: '');
        abort_unless(in_array($ext, $allowed, true), 422, 'Format gambar tidak sah.');

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move(public_path('uploads/' . $dir), $name);
        $this->guardUploadsDir();

        return 'uploads/' . $dir . '/' . $name;
    }

    /** Defense in depth (Apache): stop any file under uploads/ being run as PHP. */
    private function guardUploadsDir(): void
    {
        $htaccess = public_path('uploads/.htaccess');
        if (! is_file($htaccess)) {
            file_put_contents($htaccess, <<<'HT'
                php_flag engine off
                RemoveHandler .php .phtml .phar .phps
                <FilesMatch "\.(php|phtml|phar|phps)$">
                    Require all denied
                </FilesMatch>
                HT);
        }
    }

    private function deletePublic(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/') && is_file(public_path($path))) {
            @unlink(public_path($path));
        }
    }
}
