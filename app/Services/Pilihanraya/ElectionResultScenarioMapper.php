<?php

namespace App\Services\Pilihanraya;

use App\Models\ElectionSeatResult;
use RuntimeException;

/**
 * Keputusan rasmi SPR (electiondata.my) → satu senario Analisa.
 *
 * Memulangkan BENTUK YANG SAMA seperti Borang14ScenarioMapper::map() —
 * ['rows' => [...], 'totals' => [...]] — supaya setiap pengguna hiliran
 * (scenarioSummary(), deltas(), carta, PDF) membacanya tanpa cabang khas.
 * Dua bentuk berasingan akan hanyut, dan hanyut di sini bermakna naratif AI
 * memperihalkan angka yang berbeza daripada jadual di sebelahnya.
 *
 * DUA PERBEZAAN dengan senario Borang 14, kedua-duanya disengajakan:
 *
 *   1. Data rasmi ialah aras KERUSI. Tiada pecahan Daerah Mengundi, jadi
 *      `rows` sentiasa mengandungi TEPAT SATU baris. Memecahkannya kepada
 *      baris per-lokaliti bermakna mereka-reka taburan yang SPR tidak terbitkan.
 *
 *   2. Angka `pemilih` (pengundi berdaftar) BIASANYA ADA di sini, sedangkan
 *      scoresheet Borang 14 tidak pernah membawanya — lajur (A) ialah kertas
 *      undi dalam peti, bukan pendaftaran. Jadi senario rasmi lebih lengkap
 *      pada paksi itu. Apabila ia TIADA, ia kekal null: sifar akan menjadi
 *      "penurunan 100%" dalam deltas().
 */
class ElectionResultScenarioMapper
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>}
     *
     * @throws RuntimeException apabila keputusan itu tiada pecahan calon
     */
    public function map(ElectionSeatResult $result): array
    {
        $undi = $this->undiPerParti($result);

        if ($undi === []) {
            // Sync hanya mengambil `ballot` bagi keputusan LENGKAP TERKINI
            // setiap kerusi (mengambil semua = ~12,000 panggilan API), jadi
            // ~3,200 keputusan lama menyimpan ringkasan pemenang sahaja.
            // Memetakannya menghasilkan senario tanpa undi, dan deltas() akan
            // menerbitkan peratusan yang direka daripada kekosongan itu.
            throw new RuntimeException(
                'Keputusan rasmi ini tiada pecahan undi setiap calon, jadi ia tidak boleh dijadikan senario perbandingan. Hanya pilihan raya terkini setiap kerusi mempunyai pecahan penuh.'
            );
        }

        // Angka RASMI diguna apa adanya, bukan dikira semula daripada undi
        // parti: ballot mungkin tidak menyenaraikan setiap calon kecil, jadi
        // jumlah yang dikira sendiri akan TERKURANG berbanding angka SPR — dan
        // `keluar` ialah PENYEBUT bagi setiap peratus undi, jadi angka yang
        // terkurang melambungkan syer setiap parti sambil dipaparkan sebagai
        // angka rasmi.
        //
        // Apabila mana-mana angka tiada, ia kekal NULL. scenarioSummary()
        // sudah ada sandarannya sendiri bagi `keluar` yang kosong, jadi hiliran
        // mengendalikannya tanpa kita berpura-pura ia angka rasmi.
        $pemilih = $this->intOrNull($result->voters_total);
        $ditolak = $this->intOrNull($result->votes_rejected);
        $keluar = $this->intOrNull($result->voter_turnout);

        $row = [
            'kawasan' => $this->namaKerusi($result),
            'pemilih' => $pemilih,
            'keluar' => $keluar,
            'ditolak' => $ditolak,
            'undi' => $undi,
        ];

        return [
            'rows' => [$row],
            'totals' => [
                'pemilih' => $pemilih,
                'keluar' => $keluar,
                'ditolak' => $ditolak,
                'undi' => $undi,
                'parties' => array_keys($undi),
            ],
        ];
    }

    /**
     * @return array<string, int> nama parti (huruf besar) => undi
     */
    private function undiPerParti(ElectionSeatResult $result): array
    {
        $ballot = is_array($result->ballot) ? $result->ballot : [];
        $undi = [];

        foreach ($ballot as $calon) {
            if (! is_array($calon)) {
                continue;
            }

            // Calon tanpa parti dilangkau, BUKAN diteka. Menamakannya "BEBAS"
            // akan menggabungkan calon bebas yang tidak berkaitan menjadi satu
            // "parti" yang tidak pernah wujud.
            $parti = mb_strtoupper(trim((string) ($calon['party'] ?? '')));
            if ($parti === '' || ! isset($calon['votes'])) {
                continue;
            }

            $undi[$parti] = ($undi[$parti] ?? 0) + (int) $calon['votes'];
        }

        return $undi;
    }

    /** Huruf besar sepadan dengan konvensyen Borang14ScenarioMapper::partyNames(). */
    private function namaKerusi(ElectionSeatResult $result): string
    {
        $nama = trim((string) ($result->seat?->nama ?? ''));

        return $nama !== '' ? $nama : 'Seluruh kerusi';
    }

    /** Kekalkan null; JANGAN tukar "tidak diketahui" kepada sifar. */
    private function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
