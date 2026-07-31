<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Scoreboard;
use App\Services\Pilihanraya\ScoreboardPayload;
use App\Support\SeatScope;
use Inertia\Inertia;

/**
 * Papan markah awam — tiada log masuk, tiada tulis.
 *
 * SATU peraturan mengawal segalanya di sini: hanya papan berstatus 'tersiar'
 * boleh diselesaikan. Papan draf dan kod yang tidak wujud memulangkan 404 yang
 * SAMA, supaya tiada petunjuk kerusi mana yang wujud.
 *
 * Muatan diambil melalui ScoreboardPayload::forPublicSeat() — BUKAN forSeat()
 * — supaya nama pengendali SISDA yang menyimpan terakhir ('dikemaskini') dan
 * senario Borang 14 dalaman ('sumber') tidak terbit pada URL tanpa log masuk.
 */
class PublicScoreboardController extends Controller
{
    /** Senarai ringkas papan tersiar — hanya apa yang pemilik pilih untuk siarkan. */
    public function index()
    {
        $boards = Scoreboard::where('status', Scoreboard::STATUS_TERSIAR)
            ->whereNotNull('kod')->orderBy('kod')->get(['kawasan_type', 'kawasan_id', 'kod', 'title'])
            ->map(fn ($b) => [
                'kod' => $b->kod,
                'title' => $b->title,
                'nama' => $this->namaKerusi($b->kawasan_type, (int) $b->kawasan_id),
                'url' => route('scoreboard.public', ['kod' => strtolower($b->kod)]),
            ])->values()->all();

        return Inertia::render('Public/ScoreboardIndex', ['boards' => $boards]);
    }

    public function show(string $kod)
    {
        $board = $this->tersiar($kod);

        return Inertia::render('Public/Scoreboard', [
            'kod' => strtolower($board->kod),
            'board' => ScoreboardPayload::forPublicSeat($board->kawasan_type, (int) $board->kawasan_id),
        ]);
    }

    public function data(string $kod)
    {
        $board = $this->tersiar($kod);

        return response()->json(ScoreboardPayload::forPublicSeat($board->kawasan_type, (int) $board->kawasan_id))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** URL lama /scoreboard/{kadun} — kekalkan pautan yang sudah tersebar. */
    public function legacy(int $kadun)
    {
        $board = Scoreboard::where('kawasan_type', SeatScope::DUN)
            ->where('kawasan_id', $kadun)
            ->where('status', Scoreboard::STATUS_TERSIAR)
            ->whereNotNull('kod')
            ->first();

        abort_unless($board, 404);

        return redirect()->route('scoreboard.public', ['kod' => strtolower($board->kod)], 301);
    }

    private function tersiar(string $kod): Scoreboard
    {
        $board = Scoreboard::where('kod', strtoupper($kod))
            ->where('status', Scoreboard::STATUS_TERSIAR)
            ->first();

        abort_unless($board, 404);

        return $board;
    }

    private function namaKerusi(string $type, int $id): string
    {
        return $type === SeatScope::PARLIMEN
            ? (string) Bandar::whereKey($id)->value('nama')
            : (string) Kadun::whereKey($id)->value('nama');
    }
}
