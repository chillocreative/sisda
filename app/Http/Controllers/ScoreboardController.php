<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use App\Support\Borang14Reference;
use App\Support\SeatScope;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * Papan markah bagi PEMILIK kerusi. Laluan awam berada di sini buat
 * sementara waktu (publicShow/publicData/boardPayload) — Tugasan 5
 * memindahkannya ke PublicScoreboardController yang berasingan.
 *
 * Laluan pemilik digelang oleh 'auth' sahaja; setiap kaedah memanggil
 * SeatScope::assert() sendiri, mengikut konvensyen projek.
 */
class ScoreboardController extends Controller
{
    private const PENJURU = [2 => '1 vs 1', 3 => '3 Penjuru', 4 => '4 Penjuru', 5 => '5 Penjuru', 6 => '6 Penjuru'];

    /** Parties that make up Pakatan Harapan — used to tally the "PH" figure. */
    private const PH_PARTIES = ['KEADILAN', 'PKR', 'DAP', 'AMANAH', 'MUDA'];

    public function index(Request $request)
    {
        $seats = SeatScope::seats($request->user());
        abort_if($seats === [], 403, 'Anda tiada kerusi untuk diuruskan.');

        return Inertia::render('Pilihanraya/Scoreboard', [
            'seats' => $seats,
        ]);
    }

    /** Muatan langsung — ditinjau setiap 4 saat oleh halaman pemilik. */
    public function data(Request $request)
    {
        [$type, $id] = $this->seatFromRequest($request);

        $payload = ScoreboardPayload::forSeat($type, $id);
        $payload['sumberList'] = $this->sumberList($type, $id);

        return $this->liveJson($payload);
    }

    /** Simpan tetapan persembahan + sumber undi + pihak kami. */
    public function saveSettings(Request $request)
    {
        [$type, $id] = $this->seatFromRequest($request);

        $validated = $request->validate([
            'title' => 'nullable|string|max:100',
            'minima' => 'nullable|integer|min:0|max:100000000',
            'borang14_form_id' => 'nullable|integer|exists:borang14_forms,id',
            'pihak_kami' => 'array',
            'pihak_kami.*' => 'integer|min:1|max:6',
            'candidates' => 'array',
            'candidates.*.slot' => 'required|integer|min:1|max:6',
            'candidates.*.nama' => 'nullable|string|max:120',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:4096',
            'photos' => 'array',
            'photos.*' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:4096',
        ]);

        // Sumber undi mesti milik kerusi ini — jika tidak, papan DUN Pilah
        // boleh dipaksa membaca undi DUN lain.
        if (! empty($validated['borang14_form_id'])) {
            $milik = Borang14Form::whereKey($validated['borang14_form_id'])
                ->where('kawasan_type', $type)->where('kawasan_id', $id)->exists();
            abort_unless($milik, 422, 'Borang 14 itu bukan milik kerusi ini.');
        }

        $board = Scoreboard::firstOrNew(['kawasan_type' => $type, 'kawasan_id' => $id]);
        $board->title = ($validated['title'] ?? null) ?: 'SCOREBOARD';
        $board->minima = $validated['minima'] ?? null;
        $board->borang14_form_id = $validated['borang14_form_id'] ?? null;
        $board->pihak_kami = array_values(array_unique(array_map('intval', $validated['pihak_kami'] ?? [])));
        $board->status ??= Scoreboard::STATUS_DRAF;
        $board->updated_by = $request->user()->id;

        if ($request->hasFile('logo')) {
            $this->deletePublic($board->logo_path);
            $board->logo_path = $this->storePublic($request->file('logo'), 'scoreboard/logo');
        }

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

        // Satu baris sahaja ditulis — transaksi tidak diperlukan di sini.
        // (Kekangan projek: balut tulisan BERBILANG baris, bukan tulisan tunggal.)
        $board->save();

        return response()->json(['ok' => true]);
    }

    /** Togol Draf ⇄ Tersiar. Menyiarkan mengecap kod kerusi pada papan. */
    public function publish(Request $request)
    {
        [$type, $id] = $this->seatFromRequest($request);

        $validated = $request->validate([
            'status' => 'required|in:'.Scoreboard::STATUS_DRAF.','.Scoreboard::STATUS_TERSIAR,
        ]);

        $board = Scoreboard::where('kawasan_type', $type)->where('kawasan_id', $id)->first();
        abort_unless($board, 404, 'Papan markah belum wujud bagi kerusi ini.');

        if ($validated['status'] === Scoreboard::STATUS_DRAF) {
            $board->status = Scoreboard::STATUS_DRAF;
            $board->save();

            return response()->json(['ok' => true, 'status' => $board->status, 'kod' => $board->kod]);
        }

        $kod = $this->kodKerusi($type, $id);
        if (! $kod) {
            return response()->json([
                'message' => $type === SeatScope::PARLIMEN
                    ? 'Kerusi ini tiada Kod Parlimen. Isi medan itu dalam Data Induk > Parlimen sebelum menyiarkan.'
                    : 'Kerusi ini tiada Kod DUN. Isi medan itu dalam Data Induk > DUN sebelum menyiarkan.',
            ], 422);
        }

        $dipegang = Scoreboard::where('kod', $kod)
            ->where(fn ($q) => $q->where('kawasan_type', '!=', $type)->orWhere('kawasan_id', '!=', $id))
            ->exists();
        if ($dipegang) {
            return response()->json([
                'message' => "Kod {$kod} sudah digunakan papan markah kerusi lain. Betulkan kod dalam Data Induk.",
            ], 422);
        }

        $board->kod = $kod;
        $board->status = Scoreboard::STATUS_TERSIAR;
        $board->save();

        return response()->json([
            'ok' => true,
            'status' => $board->status,
            'kod' => $board->kod,
            'url' => route('scoreboard.public', ['kod' => strtolower($board->kod)]),
        ]);
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

    /**
     * Baca kerusi daripada permintaan dan sahkan kebenaran SEBELUM apa-apa
     * kerja lain. Setiap kaedah awam bermula di sini.
     *
     * @return array{0: string, 1: int}
     */
    private function seatFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'kawasan_type' => 'required|in:'.SeatScope::DUN.','.SeatScope::PARLIMEN,
            'kawasan_id' => 'required|integer|min:1',
        ]);

        $type = $validated['kawasan_type'];
        $id = (int) $validated['kawasan_id'];

        SeatScope::assert($request->user(), $type, $id);

        return [$type, $id];
    }

    private function kodKerusi(string $type, int $id): ?string
    {
        $kod = $type === SeatScope::PARLIMEN
            ? Bandar::whereKey($id)->value('kod_parlimen')
            : Kadun::whereKey($id)->value('kod_dun');

        $kod = strtoupper(trim((string) $kod));

        return $kod !== '' ? $kod : null;
    }

    /** Senario Borang 14 kerusi ini, untuk dropdown "Sumber Undi". */
    private function sumberList(string $type, int $id): array
    {
        return Borang14Form::where('kawasan_type', $type)->where('kawasan_id', $id)
            ->orderByDesc('tahun')->orderBy('jenis_pr')->get()
            ->map(fn ($f) => ['id' => $f->id, 'label' => ScoreboardPayload::labelSumber($f)])
            ->all();
    }

    /** JSON yang tidak boleh dicache — ditinjau langsung semasa kemasukan undi. */
    private function liveJson(array $payload)
    {
        return response()->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Build the live scoreboard payload for a DUN (shared by the legacy
     * public methods above). Warisan Tugasan <4 — Tugasan 5 menggantikan
     * laluan awam dengan PublicScoreboardController + ScoreboardPayload.
     */
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

    /**
     * Simpan imej terus di bawah public/ supaya dihidangkan pelayan web melalui
     * asset() — tiada kebergantungan pada symlink storage:link.
     */
    private function storePublic(UploadedFile $file, string $dir): string
    {
        // Sambungan diterbitkan daripada KANDUNGAN fail (bukan nama daripada
        // pelanggan) dan dipaku pada senarai izin imej, supaya polyglot bernama
        // .php tidak boleh ditulis ke dalam webroot lalu dilaksanakan.
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower($file->guessExtension() ?: '');
        abort_unless(in_array($ext, $allowed, true), 422, 'Format gambar tidak sah.');

        $name = bin2hex(random_bytes(16)).'.'.$ext;
        $file->move(public_path('uploads/'.$dir), $name);
        $this->guardUploadsDir();

        return 'uploads/'.$dir.'/'.$name;
    }

    /** Pertahanan berlapis (Apache): halang fail di bawah uploads/ dijalankan sebagai PHP. */
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
