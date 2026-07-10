<?php

namespace App\Support;

/**
 * Loads the fixed Borang 14 reference geography (Daerah Mengundi → Pusat
 * Mengundi → Saluran with registered-voter counts) for a given DUN.
 *
 * The data is curated per-DUN under resources/data/borang14/kadun-{id}.json.
 * Only DUNs with a data file return a structure; the rest return null so the
 * page can show a "data not yet available" state.
 */
class Borang14Reference
{
    /** @return array<string,mixed>|null */
    public static function forKadun(int $kadunId): ?array
    {
        $path = resource_path("data/borang14/kadun-{$kadunId}.json");

        if (! is_file($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), true) ?: null;
    }

    public static function hasData(int $kadunId): bool
    {
        return is_file(resource_path("data/borang14/kadun-{$kadunId}.json"));
    }
}
