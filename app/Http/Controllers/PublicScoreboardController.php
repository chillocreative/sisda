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
    /**
     * Senarai kad papan tersiar — hanya apa yang pemilik pilih untuk siarkan,
     * DAN hanya kerusi yang Borang 14-nya benar-benar hidup (lihat kadAwam()).
     */
    public function index()
    {
        return Inertia::render('Public/ScoreboardIndex', ['boards' => $this->kadTersiar()]);
    }

    /**
     * Muatan langsung bagi senarai kad — ditinjau oleh halaman index supaya
     * angka pada setiap kad bergerak tanpa memuat semula halaman.
     *
     * Laluan ini MESTI kekal di atas /scoreboard/{kod} dalam routes/web.php;
     * kekangan {kod} ([A-Za-z]\d+) tidak sepadan dengan "senarai", jadi tiada
     * kod kerusi sebenar boleh dirampas oleh laluan ini.
     */
    public function senarai()
    {
        return response()->json(['boards' => $this->kadTersiar()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Papan tersiar yang sudah sedia, sebagai kad.
     *
     * kadAwam() memulangkan null bagi papan yang belum memaut Borang 14 —
     * papan begitu digugurkan sepenuhnya dan bukan dipapar dengan tempat
     * kosong. Kad yang tinggal membawa angka yang dikira pelayan sahaja.
     *
     * KOS: satu SUM(undi) GROUP BY slot bagi SETIAP papan tersiar, setiap
     * tinjauan (rujukan DPT sendiri sudah dicache — lihat Borang14Reference).
     * Itu masih kecil pada bilangan kerusi yang disiarkan hari ini, tetapi ia
     * membesar dengan bilangan papan dan BUKAN dengan bilangan penonton. Jika
     * senarai ini suatu hari merangkumi ratusan kerusi, agregatkan dalam SATU
     * pertanyaan merentas borang sebelum menaikkan had throttle.
     */
    private function kadTersiar(): array
    {
        return Scoreboard::where('status', Scoreboard::STATUS_TERSIAR)
            ->whereNotNull('kod')->orderBy('kod')->get(['kawasan_type', 'kawasan_id', 'kod', 'title'])
            ->map(function ($b) {
                $kad = ScoreboardPayload::kadAwam($b->kawasan_type, (int) $b->kawasan_id);
                if ($kad === null) {
                    return null;
                }

                // kadAwam() mengambil nama daripada rujukan DPT, yang boleh
                // kosong; nama jadual induk ialah sandaran yang pasti ada.
                $kad['kod'] = $b->kod;
                $kad['nama'] = $kad['nama'] ?: $this->namaKerusi($b->kawasan_type, (int) $b->kawasan_id);
                $kad['title'] = $b->title;
                $kad['url'] = route('scoreboard.public', ['kod' => strtolower($b->kod)]);

                return $kad;
            })
            ->filter()->values()->all();
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
