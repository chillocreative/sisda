<?php

namespace App\Support;

use App\Models\Bandar;
use App\Models\Kadun;
use Illuminate\Support\Facades\Cache;
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
    /**
     * Tempoh cache rujukan, dalam SAAT.
     *
     * deriveFromDpt()/deriveFromDptForBandar() mengimbas SELURUH
     * pangkalan_data_pengundi kebangsaan (UPPER(kadun)/UPPER(parlimen)
     * mematikan index) dan memuatkan setiap baris pengundi kerusi itu ke dalam
     * PHP. Papan markah AWAM meninjau setiap 4 saat, tanpa log masuk, dan
     * setiap penonton meninjau sendiri — pada malam keputusan itu bermakna
     * ribuan imbasan penuh seminit yang boleh menumbangkan kemasukan Borang 14.
     *
     * Yang dicache di sini ialah STRUKTUR rujukan sahaja (Daerah Mengundi →
     * Pusat Mengundi → Saluran + bilangan berdaftar) — data yang berubah hanya
     * apabila roll DPT dimuat naik semula atau fail JSON terkurasi ditukar,
     * iaitu berbulan sekali. ANGKA UNDI LANGSUNG TIDAK dicache: ia dibaca
     * terus daripada borang14_votes dalam ScoreboardPayload::forSeat(), jadi
     * kemasukan undi tetap muncul dalam satu tinjauan.
     *
     * 45 saat dipilih: cukup panjang untuk meruntuhkan ~11 tinjauan setiap
     * penonton kepada SATU pertanyaan, cukup pendek supaya muat naik roll DPT
     * baharu kelihatan dalam masa kurang seminit tanpa perlu membatalkan cache
     * secara eksplisit di setiap laluan muat naik.
     */
    private const CACHE_TTL = 45;

    /** @return array<string,mixed>|null */
    public static function forKadun(int $kadunId): ?array
    {
        return self::cached("dun:{$kadunId}", fn () => self::bacaKadun($kadunId));
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
        return self::cached("parlimen:{$bandarId}", function () use ($bandarId) {
            $bandar = Bandar::with('negeri')->find($bandarId);

            return $bandar ? self::deriveFromDptForBandar($bandar) : null;
        });
    }

    public static function hasData(int $kadunId): bool
    {
        return self::forKadun($kadunId) !== null;
    }

    /** @return array<string,mixed>|null */
    private static function bacaKadun(int $kadunId): ?array
    {
        $path = resource_path("data/borang14/kadun-{$kadunId}.json");

        if (is_file($path)) {
            return json_decode(file_get_contents($path), true) ?: null;
        }

        return self::deriveFromDpt($kadunId);
    }

    /**
     * Cache::remember() menganggap null sebagai "tiada dalam cache" dan akan
     * membina semula setiap kali — tepat pada kes terburuk (kerusi tanpa roll
     * masih mengimbas jadual penuh setiap tinjauan). Bungkus hasil dalam array
     * supaya null pun tersimpan.
     *
     * @param  callable():(array<string,mixed>|null)  $bina
     * @return array<string,mixed>|null
     */
    private static function cached(string $suffix, callable $bina): ?array
    {
        $bungkus = Cache::remember(self::kunci($suffix), self::CACHE_TTL, fn () => ['ref' => $bina()]);

        return is_array($bungkus) ? ($bungkus['ref'] ?? null) : null;
    }

    private static function kunci(string $suffix): string
    {
        return 'borang14ref:v1:'.$suffix;
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
