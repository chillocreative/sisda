<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use App\Support\SeatScope;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * Papan markah bagi PEMILIK kerusi. Laluan awam berada dalam
 * PublicScoreboardController yang berasingan — tiada auth, tiada tulis.
 *
 * Laluan pemilik digelang oleh 'auth' sahaja; setiap kaedah memanggil
 * SeatScope::assert() sendiri, mengikut konvensyen projek.
 */
class ScoreboardController extends Controller
{
    /**
     * Bentuk kod kerusi yang laluan awam (routes/web.php) benarkan: satu huruf
     * diikuti nombor, cth. N27 atau P129. Ditakrifkan SEKALI di sini dan
     * disemak dalam publish() SEBELUM kod dicap, supaya kod yang dicap pada
     * papan sentiasa sepadan dengan kekangan laluan — tiada pautan mati.
     */
    private const POLA_KOD = '/^[A-Za-z]\d+$/';

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

        // Gambar calon telah dibuang daripada ciri ini — hanya nama disimpan.
        // Sebarang gambar yang pernah dimuat naik dinyahpaut semasa simpanan
        // berikutnya supaya fail lama tidak tertinggal dalam public/uploads.
        $existing = collect($board->candidates ?? [])->keyBy('slot');
        $candidates = [];
        foreach ($validated['candidates'] ?? [] as $c) {
            $slot = (int) $c['slot'];
            $this->deletePublic($existing[$slot]['gambar'] ?? null);

            $candidates[] = ['slot' => $slot, 'nama' => $c['nama'] ?? null];
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

        // Kod mesti sepadan dengan kekangan laluan awam (routes/web.php) SEBELUM
        // dicap — jika tidak, publish() berjaya tetapi pautan awam 404 senyap.
        if (! preg_match(self::POLA_KOD, $kod)) {
            return response()->json([
                'message' => $type === SeatScope::PARLIMEN
                    ? "Kod Parlimen \"{$kod}\" tidak sah. Betulkan medan Kod Parlimen dalam Data Induk > Parlimen — bentuk yang diperlukan ialah satu huruf diikuti nombor, cth. P129."
                    : "Kod DUN \"{$kod}\" tidak sah. Betulkan medan Kod DUN dalam Data Induk > DUN — bentuk yang diperlukan ialah satu huruf diikuti nombor, cth. N27.",
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
