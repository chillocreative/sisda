<?php

namespace App\Support;

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

    public static function hasData(int $kadunId): bool
    {
        if (is_file(resource_path("data/borang14/kadun-{$kadunId}.json"))) {
            return true;
        }

        return self::deriveFromDpt($kadunId) !== null;
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
}
