<?php

namespace App\Services\Pilihanraya;

use App\Models\Borang14Form;
use App\Models\Scoreboard;
use App\Support\Borang14Reference;
use App\Support\SeatScope;
use Illuminate\Support\Arr;

/**
 * Membina muatan papan markah langsung bagi satu kerusi. Logik baca tulen —
 * tiada kesedaran tentang Request, dipanggil oleh laluan pemilik dan awam.
 *
 * "Tiada data" BUKAN sifar: apabila pemilik belum memilih sumber Borang 14,
 * muatan mengembalikan ready=false TANPA kunci angka langsung, supaya antara
 * muka memapar "—" dan bukan "0".
 */
class ScoreboardPayload
{
    private const PENJURU = [2 => '1 vs 1', 3 => '3 Penjuru', 4 => '4 Penjuru', 5 => '5 Penjuru', 6 => '6 Penjuru'];

    /**
     * Kunci yang HANYA untuk skrin pemilik dan tidak boleh keluar pada laluan
     * awam tanpa log masuk:
     *  - 'dikemaskini' membawa users.name pengendali SISDA yang menyimpan
     *    terakhir — nama sebenar seorang petugas.
     *  - 'sumber' mendedahkan id + label senario Borang 14 dalaman.
     * Halaman awam tidak memerlukan kedua-duanya (Pages/Public/Scoreboard.jsx
     * tidak merujuknya langsung), jadi ia ditapis di SATU tempat sahaja —
     * forPublicSeat() — supaya laluan pemilik terbukti tidak tersentuh.
     */
    private const KUNCI_PEMILIK = ['dikemaskini', 'sumber'];

    public static function forSeat(string $type, int $id): array
    {
        $reference = $type === SeatScope::PARLIMEN
            ? Borang14Reference::forBandar($id)
            : Borang14Reference::forKadun($id);

        $board = Scoreboard::where('kawasan_type', $type)->where('kawasan_id', $id)->first();

        if (! $reference) {
            return ['hasData' => false, 'ready' => false, 'sumber' => null];
        }

        $form = $board?->borang14_form_id
            ? Borang14Form::find($board->borang14_form_id)
            : null;

        if (! $form) {
            return [
                'hasData' => true,
                'ready' => false,
                'needsBorang14' => true,
                'sumber' => null,
                'title' => $board?->title ?? 'SCOREBOARD',
                'status' => $board?->status ?? Scoreboard::STATUS_DRAF,
                'kod' => $board?->kod,
            ];
        }

        $penjuru = (int) $form->penjuru;
        $parties = $form->parties ?? [];
        $candidates = collect($board?->candidates ?? [])->keyBy('slot');
        $kami = array_map('intval', $board?->pihak_kami ?? []);

        $tally = array_fill(1, $penjuru, 0);
        $sums = $form->votes()->where('slot', '>=', 1)
            ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')->pluck('total', 'slot');
        foreach ($sums as $slot => $total) {
            if ($slot >= 1 && $slot <= $penjuru) {
                $tally[$slot] = (int) $total;
            }
        }

        $berdaftar = Borang14Reference::jumlahBerdaftar($reference);

        $rows = [];
        $undiKami = 0;
        foreach (range(1, $penjuru) as $slot) {
            $isKami = in_array($slot, $kami, true);
            $undi = $tally[$slot] ?? 0;
            if ($isKami) {
                $undiKami += $undi;
            }
            $rows[] = [
                'slot' => $slot,
                'parti' => $parties[$slot - 1]['nama'] ?? "Parti {$slot}",
                'is_kami' => $isKami,
                'calon' => $candidates[$slot]['nama'] ?? null,
                'undi' => $undi,
            ];
        }

        $totalKeluar = array_sum($tally);

        return [
            'hasData' => true,
            'ready' => true,
            'penjuru' => $penjuru,
            'penjuru_label' => self::PENJURU[$penjuru] ?? '',
            'title' => $board?->title ?? 'SCOREBOARD',
            'logo_url' => $board?->logo_path ? asset($board->logo_path) : asset('images/logo.png'),
            'minima' => $board?->minima,
            'kod' => $board?->kod,
            'status' => $board?->status ?? Scoreboard::STATUS_DRAF,
            'dun' => $reference['dun'] ?? null,
            'parlimen' => $reference['parlimen'] ?? null,
            'negeri' => $reference['negeri'] ?? null,
            'rows' => $rows,
            'undi_kami' => $undiKami,
            'total_keluar' => $totalKeluar,
            'total_berdaftar' => $berdaftar,
            'leader_slot' => $totalKeluar > 0 ? collect($rows)->sortByDesc('undi')->first()['slot'] : null,
            'sumber' => ['id' => $form->id, 'label' => self::labelSumber($form)],
            'dikemaskini' => self::dikemaskini($board),
        ];
    }

    /**
     * Unjuran AWAM: muatan yang sama, tolak kunci milik pemilik. Digunakan oleh
     * PublicScoreboardController sahaja — lihat KUNCI_PEMILIK.
     */
    public static function forPublicSeat(string $type, int $id): array
    {
        return Arr::except(self::forSeat($type, $id), self::KUNCI_PEMILIK);
    }

    /**
     * Siapa menyimpan tetapan terakhir. Papan DUN boleh disunting oleh pemilik
     * DUN DAN admin Parlimennya — tiada kunci, jadi perlanggaran dibuat
     * kelihatan sahaja.
     */
    private static function dikemaskini(?Scoreboard $board): ?array
    {
        if (! $board?->updated_by) {
            return null; // Belum pernah disimpan melalui borang — bukan "tiada suntingan".
        }

        $board->loadMissing('penyunting');

        return [
            'nama' => $board->penyunting?->name,
            'pada' => $board->updated_at?->toIso8601String(),
        ];
    }

    /** "PRN 2026 · 3 Penjuru" */
    public static function labelSumber(Borang14Form $form): string
    {
        return strtoupper((string) $form->jenis_pr).' '.$form->tahun.' · '.(self::PENJURU[(int) $form->penjuru] ?? $form->penjuru.' Penjuru');
    }
}
