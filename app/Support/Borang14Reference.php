<?php

namespace App\Support;

use App\Models\Bandar;
use App\Models\Kadun;
use Illuminate\Support\Facades\DB;

/**
 * Loads the Borang 14 reference geography (Daerah Mengundi → Pusat Mengundi
 * → Saluran with registered-voter counts) for a given DUN.
 *
 * Two sources, in priority order:
 * 1. A curated JSON file under resources/data/borang14/kadun-{id}.json —
 *    the exact SPR-gazetted Pusat Mengundi/Saluran breakdown.
 * 2. A DPT-derived estimate, built by grouping uploaded voter-roll rows
 *    (pangkalan_data_pengundi) by Daerah Mengundi + Lokaliti and treating
 *    each Lokaliti as one Pusat Mengundi with a single Saluran. The DPT roll
 *    doesn't carry the official channel/Saluran split, so this is an
 *    approximation flagged via `source: 'dpt_estimate'` — callers should
 *    show a disclaimer wherever it's used.
 *
 * DUNs with neither source return null so the page can show a "data not yet
 * available" state.
 */
class Borang14Reference
{
    /** @return array<string,mixed>|null */
    public static function forKadun(int $kadunId): ?array
    {
        $path = resource_path("data/borang14/kadun-{$kadunId}.json");

        if (is_file($path)) {
            return json_decode(file_get_contents($path), true) ?: null;
        }

        return self::deriveFromDpt($kadunId);
    }

    /**
     * Struktur rujukan untuk kerusi Parlimen. daerah_mengundi.bandar_id sudah
     * menunjuk ke Parlimen secara langsung, jadi DM dikumpul terus daripada
     * pangkalan_data_pengundi tanpa join melalui kadun.
     *
     * @return array<string,mixed>|null
     */
    public static function forBandar(int $bandarId): ?array
    {
        $bandar = Bandar::with('negeri')->find($bandarId);
        if (! $bandar) {
            return null;
        }

        return self::deriveFromDptForBandar($bandar);
    }

    public static function hasData(int $kadunId): bool
    {
        if (is_file(resource_path("data/borang14/kadun-{$kadunId}.json"))) {
            return true;
        }

        return self::deriveFromDpt($kadunId) !== null;
    }

    /**
     * Jumlah pengundi berdaftar bagi sesuatu rujukan.
     *
     * Kelas ini memulangkan DUA bentuk berbeza, dan pemanggil tidak sepatutnya
     * perlu tahu yang mana satu:
     *   - Fail JSON terkurasi : daerah_mengundi[].jumlah_berdaftar WUJUD
     *   - Terbitan DPT        : TIADA jumlah_berdaftar; kiraan berada pada
     *                           daerah_mengundi[].pusat_mengundi[].saluran[].berdaftar
     *
     * Hanya satu DUN mempunyai fail terkurasi, jadi hampir setiap kerusi
     * menggunakan bentuk kedua. Kod lama menjumlahkan bentuk pertama sahaja
     * dengan `?? 0`, lalu memaparkan "% Keluar Mengundi: 0.0%" — angka direka.
     *
     * Memulangkan null apabila rujukan langsung TIDAK membawa sebarang angka
     * berdaftar. null bermaksud "tidak diketahui" dan mesti dipaparkan sebagai
     * "—". Sifar yang dilaporkan secara jujur oleh rujukan kekal sebagai 0.
     *
     * @param  array<string,mixed>  $reference
     */
    public static function jumlahBerdaftar(array $reference): ?int
    {
        $jumlah = 0;
        $adaAngka = false;

        foreach ($reference['daerah_mengundi'] ?? [] as $dm) {
            if (array_key_exists('jumlah_berdaftar', $dm) && $dm['jumlah_berdaftar'] !== null) {
                $jumlah += (int) $dm['jumlah_berdaftar'];
                $adaAngka = true;

                continue;
            }

            foreach ($dm['pusat_mengundi'] ?? [] as $pusat) {
                foreach ($pusat['saluran'] ?? [] as $saluran) {
                    if (array_key_exists('berdaftar', $saluran) && $saluran['berdaftar'] !== null) {
                        $jumlah += (int) $saluran['berdaftar'];
                        $adaAngka = true;
                    }
                }
            }
        }

        foreach (['undi_awal', 'undi_pos'] as $kunci) {
            $bahagian = $reference[$kunci] ?? [];
            if (array_key_exists('berdaftar', $bahagian) && $bahagian['berdaftar'] !== null) {
                $jumlah += (int) $bahagian['berdaftar'];
                $adaAngka = true;
            }
        }

        return $adaAngka ? $jumlah : null;
    }

    /** @return array<string,mixed>|null */
    private static function deriveFromDpt(int $kadunId): ?array
    {
        $kadun = Kadun::with('bandar.negeri')->find($kadunId);
        if (! $kadun) {
            return null;
        }

        $rows = DB::table('pangkalan_data_pengundi')
            ->whereRaw('UPPER(kadun) = ?', [strtoupper($kadun->nama)])
            ->where(function ($q) {
                $q->where('is_deceased', false)->orWhereNull('is_deceased');
            })
            ->select('daerah_mengundi', 'lokaliti')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // Group by DM -> Lokaliti (treated as Pusat Mengundi); the row count
        // per group becomes that Pusat Mengundi's Berdaftar.
        $grouped = [];
        foreach ($rows as $r) {
            $dm = trim((string) $r->daerah_mengundi) ?: 'TIADA DAERAH MENGUNDI';
            $lokaliti = trim((string) $r->lokaliti) ?: 'TIADA LOKALITI';
            $grouped[$dm][$lokaliti] = ($grouped[$dm][$lokaliti] ?? 0) + 1;
        }

        $daerahMengundi = [];
        foreach ($grouped as $dm => $lokalitiCounts) {
            $pusatMengundi = [];
            foreach ($lokalitiCounts as $lokaliti => $count) {
                $pusatMengundi[] = [
                    'nama' => $lokaliti,
                    'saluran' => [['no' => 1, 'berdaftar' => $count]],
                ];
            }
            $daerahMengundi[] = ['nama' => $dm, 'pusat_mengundi' => $pusatMengundi];
        }

        return [
            'negeri' => $kadun->bandar->negeri->nama ?? '',
            'parlimen' => $kadun->bandar->nama ?? '',
            'dun' => $kadun->nama,
            'daerah_mengundi' => $daerahMengundi,
            'undi_awal' => ['berdaftar' => 0],
            'undi_pos' => ['berdaftar' => 0],
            'source' => 'dpt_estimate',
        ];
    }

    /** @return array<string,mixed>|null */
    private static function deriveFromDptForBandar(Bandar $bandar): ?array
    {
        $rows = DB::table('pangkalan_data_pengundi')
            ->whereRaw('UPPER(parlimen) = ?', [strtoupper($bandar->nama)])
            ->where(function ($q) {
                $q->where('is_deceased', false)->orWhereNull('is_deceased');
            })
            ->select('daerah_mengundi', 'lokaliti')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // Group by DM -> Lokaliti (treated as Pusat Mengundi); the row count
        // per group becomes that Pusat Mengundi's Berdaftar.
        $grouped = [];
        foreach ($rows as $r) {
            $dm = trim((string) $r->daerah_mengundi) ?: 'TIADA DAERAH MENGUNDI';
            $lokaliti = trim((string) $r->lokaliti) ?: 'TIADA LOKALITI';
            $grouped[$dm][$lokaliti] = ($grouped[$dm][$lokaliti] ?? 0) + 1;
        }

        $daerahMengundi = [];
        foreach ($grouped as $dm => $lokalitiCounts) {
            $pusatMengundi = [];
            foreach ($lokalitiCounts as $lokaliti => $count) {
                $pusatMengundi[] = [
                    'nama' => $lokaliti,
                    'saluran' => [['no' => 1, 'berdaftar' => $count]],
                ];
            }
            $daerahMengundi[] = ['nama' => $dm, 'pusat_mengundi' => $pusatMengundi];
        }

        return [
            'negeri' => $bandar->negeri->nama ?? '',
            'parlimen' => $bandar->nama,
            'dun' => null,
            'daerah_mengundi' => $daerahMengundi,
            'undi_awal' => ['berdaftar' => 0],
            'undi_pos' => ['berdaftar' => 0],
            'source' => 'dpt_estimate',
        ];
    }
}
