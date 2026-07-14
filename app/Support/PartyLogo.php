<?php

namespace App\Support;

/**
 * Maps a Borang 14 party/coalition name to its logo, embedded as a base64
 * data URI for the PDF export. Logos live under public/images/parti/{file};
 * names with no match simply render without one.
 */
class PartyLogo
{
    private const MAP = [
        'PAKATAN HARAPAN' => 'ph', 'PH' => 'ph', 'HARAPAN' => 'ph',
        'BARISAN NASIONAL' => 'bn', 'BN' => 'bn',
        'PERIKATAN NASIONAL' => 'pn', 'PN' => 'pn',
        'GABUNGAN PARTI SARAWAK' => 'gps', 'GPS' => 'gps',
        'GABUNGAN RAKYAT SABAH' => 'grs', 'GRS' => 'grs',
        'KEADILAN' => 'keadilan', 'PKR' => 'keadilan', 'PARTI KEADILAN RAKYAT' => 'keadilan',
        'PPBM' => 'bersatu', 'BERSATU' => 'bersatu',
        'UMNO' => 'umno',
        'DAP' => 'dap',
        'MIC' => 'mic',
        'MCA' => 'mca',
        'GERAKAN' => 'gerakan',
        'MUDA' => 'muda',
        'PEJUANG' => 'pejuang',
        'PAS' => 'pas',
        'AMANAH' => 'amanah',
    ];

    private static array $cache = [];

    public static function dataUri(?string $name): ?string
    {
        $key = strtoupper(trim((string) $name));
        if ($key === '' || ! isset(self::MAP[$key])) {
            return null;
        }

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $path = public_path('images/parti/'.self::MAP[$key].'.png');
        if (! is_file($path)) {
            return self::$cache[$key] = null;
        }

        return self::$cache[$key] = 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
