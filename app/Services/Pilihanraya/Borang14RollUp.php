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
            // DUA soalan BERBEZA, jadi DUA pertanyaan berbeza — lihat docblock
            // jumlahSlot() dan sudahLapor(). Menggabungkannya (mengira "melapor"
            // daripada jumlah slot yang tidak kosong) memaksa satu peraturan
            // slot untuk kedua-dua kerja, dan mana-mana peraturan tunggal
            // memusnahkan salah satu daripadanya.
            if (self::sudahLapor($dun)) {
                $melapor++;
            }
            $slotDun = self::jumlahSlot($dun->votesFor(Borang14Vote::CONTEST_PARLIMEN));
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
     * JUMLAH undi mengikut slot — SEMUA slot dari 1 ke atas, TANPA had atas.
     *
     * Jangan sekali-kali mengehadkan hujung ATAS di sini. `penjuru` tidak
     * dikepit di mana-mana pada laluan muat naik: writeForm() menetapkan
     * `penjuru = max(2, count($extractedData['calon']))` dan putVote() menulis
     * slot `$i + 1` tanpa had, jadi scoresheet Parlimen dengan 7 calon
     * menghasilkan undi SEBENAR pada slot 7. Menapis 1..6 di sini akan
     * memaparkan undi calon itu sebagai 0 pada papan awam — sifar rekaan bagi
     * undi yang benar-benar wujud, iaitu tepat peraturan yang paling keras
     * dipegang projek ini. (Ditemui sebagai regresi yang diperkenalkan oleh
     * pembetulan liputan; jangan bawa balik.)
     *
     * Slot 90 (undi ditolak) dan 91 (undi tidak dimasukkan) TIDAK ditapis di
     * sini kerana ia sudah ditapis oleh pengguna tunggal array ini —
     * ScoreboardPayload::forSeat() hanya menerima `$slot >= 1 && $slot <=
     * $penjuru` ke dalam tally, dan `penjuru` ialah bilangan calon. Ini juga
     * menjadikan cabang Parlimen SEPADAN dengan cabang DUN dalam fail yang
     * sama, yang sentiasa menggunakan `slot >= 1` + kepitan penjuru itu.
     *
     * @return array<int,int>
     */
    private static function jumlahSlot($query): array
    {
        return $query->where('slot', '>=', Borang14Vote::SLOT_CALON_MIN)
            ->selectRaw('slot, SUM(undi) as total')->groupBy('slot')
            ->pluck('total', 'slot')
            ->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Adakah DUN ini sudah melaporkan KEPUTUSAN pertandingan Parlimen?
     *
     * Soalan ini BUKAN "adakah ada baris undi", dan itulah sebabnya ia tidak
     * boleh diterbitkan daripada jumlahSlot(). Slot 90 (undi ditolak) dan 91
     * (undi tidak dimasukkan) bukan undi mana-mana calon: satu DUN yang baru
     * mengunci angka ditolak belum melaporkan apa-apa keputusan. Mengiranya
     * sebagai melapor membenarkan melapor === jumlah pada kiraan yang masih
     * kehilangan SELURUH undi calon DUN itu, lalu papan awam memapar banner
     * HIJAU "LENGKAP · Semua N DUN telah melapor" di atas kiraan separa —
     * tepat perkara yang `liputan` wujud untuk menghalang.
     *
     * Had ATAS (SLOT_CALON_MAX) selamat DI SINI walaupun tidak selamat dalam
     * jumlahSlot(): penentuan liputan hanya berjalan pada cabang KUMPULAN,
     * dan `penjuru` borang takrifan di situ ditulis oleh saveParties() yang
     * mengesahkan `in:2,3,4,5,6`. Slot 7+ hanya boleh wujud melalui muat naik
     * scoresheet, yang SENTIASA turut menulis `structure` — dan struktur itu
     * menghantar borang ke cabang bacaan TERUS, yang tiada konsep liputan.
     * Jika andaian itu pernah tersasar, kegagalannya pun ke arah SELAMAT:
     * liputan terkurang kira, jadi papan kekal SEMENTARA (amber) dan tidak
     * pernah mendakwa LENGKAP secara palsu.
     */
    private static function sudahLapor(Borang14Form $dun): bool
    {
        return $dun->votesFor(Borang14Vote::CONTEST_PARLIMEN)
            ->whereBetween('slot', [Borang14Vote::SLOT_CALON_MIN, Borang14Vote::SLOT_CALON_MAX])
            ->exists();
    }
}
