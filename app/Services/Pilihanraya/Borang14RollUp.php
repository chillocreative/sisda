<?php

namespace App\Services\Pilihanraya;

use App\Models\Borang14Form;
use App\Models\Borang14Vote;

/**
 * Menjawab satu soalan: apakah keputusan pertandingan PARLIMEN?
 *
 * Dua senario, dibezakan oleh `structure` borang Parlimen:
 *  1. Ada struktur sendiri  -> PRU sahaja; baca terus daripada borang itu.
 *  2. Tiada struktur        -> pilihanraya serentak; borang itu hanyalah
 *     TAKRIFAN calon, dan undi sebenar berada pada borang DUN yang memautinya.
 *
 * Kerana senarai calon ditakrifkan SEKALI pada borang Parlimen, slot 1
 * bermakna orang yang SAMA di setiap DUN — jumlah sentiasa serupa-dengan-serupa.
 */
class Borang14RollUp
{
    /**
     * @return array{form_id:int, parties:array, penjuru:int, undi:array<int,int>, sumber:string, liputan:?array{melapor:int,jumlah:int}}|null
     */
    public static function forParlimen(int $bandarId, ?int $tahun = null): ?array
    {
        $form = Borang14Form::where('kawasan_type', Borang14Form::KAWASAN_PARLIMEN)
            ->where('kawasan_id', $bandarId)
            ->when($tahun, fn ($q) => $q->where('tahun', $tahun))
            ->latest('tahun')->first();

        // Tiada borang Parlimen langsung — TIDAK DIKETAHUI, bukan sifar undi.
        if (! $form) {
            return null;
        }

        $asas = [
            'form_id' => $form->id,
            'parties' => $form->parties ?? [],
            'penjuru' => (int) $form->penjuru,
        ];

        if (! empty($form->structure)) {
            return $asas + [
                'undi' => self::jumlahSlot($form->votesFor(Borang14Vote::CONTEST_PARLIMEN)),
                'sumber' => 'borang',
                'liputan' => null,
            ];
        }

        $borangDun = $form->borangDun()->get();
        $undi = [];
        $melapor = 0;

        foreach ($borangDun as $dun) {
            $slotDun = self::jumlahSlot($dun->votesFor(Borang14Vote::CONTEST_PARLIMEN));
            if ($slotDun !== []) {
                $melapor++;
            }
            foreach ($slotDun as $slot => $nilai) {
                $undi[$slot] = ($undi[$slot] ?? 0) + $nilai;
            }
        }

        ksort($undi);

        return $asas + [
            'undi' => $undi,
            'sumber' => 'kumpulan',
            // Kumpulan SEPARA pada malam keputusan tidak boleh kelihatan
            // seperti keputusan muktamad — liputan sentiasa dilaporkan.
            'liputan' => ['melapor' => $melapor, 'jumlah' => $borangDun->count()],
        ];
    }

    /**
     * Jumlah undi mengikut slot CALON SAHAJA (1..6).
     *
     * Slot 90 (undi ditolak) dan 91 (undi tidak dimasukkan) SENGAJA dikecualikan,
     * dan bukan sekadar untuk kebersihan jumlah:
     *
     *  1. Jumlah — 90/91 bukan undi mana-mana calon. Ia tidak pernah masuk ke
     *     dalam tally (ScoreboardPayload menapis $slot <= $penjuru), jadi
     *     mengecualikannya di sini tidak mengubah satu angka pun yang dipapar.
     *
     *  2. LIPUTAN — inilah sebab sebenar. forParlimen() mengira satu DUN sebagai
     *     "melapor" apabila jumlah slotnya TIDAK KOSONG. Dengan `slot >= 1`,
     *     satu DUN yang baru mengunci angka undi DITOLAK sahaja (slot 90) sudah
     *     dikira melapor — dan jika ia DUN terakhir, melapor === jumlah lalu
     *     papan awam memapar banner HIJAU "LENGKAP · Semua N DUN telah melapor"
     *     di atas jumlah yang KEHILANGAN seluruh undi calon DUN itu. Kiraan
     *     separa yang menyamar sebagai muktamad ialah TEPAT perkara yang
     *     `liputan` wujud untuk menghalang.
     *
     * @return array<int,int>
     */
    private static function jumlahSlot($query): array
    {
        return $query->whereBetween('slot', [Borang14Vote::SLOT_CALON_MIN, Borang14Vote::SLOT_CALON_MAX])
            ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')
            ->pluck('total', 'slot')
            ->map(fn ($v) => (int) $v)->all();
    }
}
