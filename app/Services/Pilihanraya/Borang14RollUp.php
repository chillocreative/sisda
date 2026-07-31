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

    /** @return array<int,int> */
    private static function jumlahSlot($query): array
    {
        return $query->where('slot', '>=', 1)
            ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')
            ->pluck('total', 'slot')
            ->map(fn ($v) => (int) $v)->all();
    }
}
