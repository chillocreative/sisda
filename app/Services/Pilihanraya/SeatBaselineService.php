<?php

namespace App\Services\Pilihanraya;

use App\Models\Bandar;
use App\Models\ElectionSeat;
use App\Models\ElectionSeatResult;
use App\Models\Kadun;

/**
 * Garis dasar sejarah bagi satu kerusi — keputusan rasmi SPR LENGKAP terkini,
 * dibaca daripada jadual yang disegerakkan (tiada panggilan rangkaian di sini,
 * jadi ia selamat dipanggil semasa merender halaman).
 *
 * SETIAP angka boleh null dan mesti dipaparkan sebagai "—". Tiada `?? 0` di mana
 * pun dalam fail ini: kerusi tanpa data yang disegerakkan bukanlah kerusi dengan
 * sifar undi, dan pilihan raya AKAN DATANG dipulangkan oleh API dengan setiap
 * angka null.
 */
class SeatBaselineService
{
    public function __construct(protected ElectionDataService $api) {}

    /**
     * @return array{tersedia:bool, kerusi:?string, pilihanraya:?string, tarikh:?string,
     *     pemenang:?array, penyaing:?array, majoriti:?int, majoriti_perc:?float,
     *     pengundi_berdaftar:?int, keluar_mengundi:?int, keluar_mengundi_perc:?float,
     *     undi_ditolak:?int, ballot:array}
     */
    public function forKawasan(Kadun|Bandar $kawasan): array
    {
        $result = $this->resultFor($kawasan);

        if ($result === null) {
            return $this->kosong();
        }

        $ballot = collect(is_array($result->ballot) ? $result->ballot : [])
            ->filter(fn ($c) => is_array($c))
            ->map(fn ($c) => [
                'nama' => $c['name'] ?? $c['nama'] ?? null,
                'parti' => $c['party'] ?? null,
                // Disalin apa adanya — undi yang tiada kekal null, bukan 0.
                'undi' => isset($c['votes']) ? (int) $c['votes'] : null,
                'undi_perc' => isset($c['votes_perc']) ? (float) $c['votes_perc'] : null,
            ])
            ->sortByDesc(fn ($c) => $c['undi'] ?? -1)
            ->values();

        return [
            'tersedia' => true,
            'kerusi' => $result->seat?->nama,
            'pilihanraya' => $result->election_name,
            'tarikh' => $result->tarikh?->toDateString(),
            // Tanpa pecahan undi penuh, ringkasan kerusi masih tahu SIAPA yang
            // menang — tetapi bukan dengan berapa undi. Angka itu kekal null.
            'pemenang' => $ballot->first() ?? [
                'nama' => $result->candidate,
                'parti' => $result->party,
                'undi' => null,
                'undi_perc' => null,
            ],
            'penyaing' => $ballot->get(1),
            'majoriti' => $result->majority,
            'majoriti_perc' => $result->majority_perc !== null ? (float) $result->majority_perc : null,
            'pengundi_berdaftar' => $result->voters_total,
            'keluar_mengundi' => $result->voter_turnout,
            'keluar_mengundi_perc' => $result->voter_turnout_perc !== null ? (float) $result->voter_turnout_perc : null,
            'undi_ditolak' => $result->votes_rejected,
            'ballot' => $ballot->all(),
        ];
    }

    /**
     * Jumlah undi bagi satu gabungan/parti pada garis dasar, atau null apabila
     * parti itu tidak bertanding (atau tiada garis dasar langsung).
     *
     * null bermakna "tidak diketahui", BUKAN "sifar undi" — pemanggil mesti
     * memaparkannya sebagai "—" dan bukan mengiranya sebagai kekalahan.
     */
    public function partyVotes(array $baseline, string|array $party): ?int
    {
        // Data rasmi menamakan gabungan secara tidak konsisten merentas tahun
        // (PN / PERIKATAN NASIONAL / BERSATU / PAS), jadi pemanggil menghantar
        // setiap alias dan padanan PERTAMA menang. Ia tidak menjumlahkan alias:
        // satu gabungan mengemukakan satu calon per kerusi, jadi menjumlahkannya
        // akan mengira dua kali jika data menyenaraikan kedua-dua label.
        foreach ((array) $party as $alias) {
            $key = ElectionDataService::nameKey($alias);
            foreach ($baseline['ballot'] ?? [] as $c) {
                if (ElectionDataService::nameKey($c['parti'] ?? '') === $key) {
                    return $c['undi'];
                }
            }
        }

        return null;
    }

    /** Keputusan LENGKAP terkini bagi kawasan ini, atau null. */
    private function resultFor(Kadun|Bandar $kawasan): ?ElectionSeatResult
    {
        // Pautan cache dahulu (diisi semasa segerak), kemudian slug — kawasan
        // yang disegerakkan sebelum ia wujud dalam SISDA tiada pautan lagi.
        $seat = $kawasan instanceof Kadun
            ? ElectionSeat::where('kadun_id', $kawasan->id)->first()
            : ElectionSeat::where('bandar_id', $kawasan->id)->first();

        if (! $seat) {
            $slug = $this->api->slugFor($kawasan);
            $seat = $slug ? ElectionSeat::where('slug', $slug)->first() : null;
        }

        return $seat?->latestCompletedResult();
    }

    /** @return array<string, mixed> */
    public function kosong(): array
    {
        return [
            'tersedia' => false,
            'kerusi' => null,
            'pilihanraya' => null,
            'tarikh' => null,
            'pemenang' => null,
            'penyaing' => null,
            'majoriti' => null,
            'majoriti_perc' => null,
            'pengundi_berdaftar' => null,
            'keluar_mengundi' => null,
            'keluar_mengundi_perc' => null,
            'undi_ditolak' => null,
            'ballot' => [],
        ];
    }
}
