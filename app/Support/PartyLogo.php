<?php

namespace App\Support;

/**
 * Maps a Borang 14 party/coalition name to its logo. Logos live under
 * public/images/parti/{file}; names with no match simply render without one.
 *
 * Dua bentuk keluaran daripada SATU peta:
 *  - dataUri() menyematkan bait logo — eksport PDF tiada capaian HTTP.
 *  - url()     memulangkan URL awam — skrin (papan markah) memuatkannya biasa.
 * Kedua-duanya berkongsi slug() supaya nama yang dikenali PDF dan nama yang
 * dikenali skrin tidak boleh menyimpang.
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

    /** Nama fail logo (tanpa sambungan), atau null jika nama tidak dikenali. */
    public static function slug(?string $name): ?string
    {
        return self::MAP[strtoupper(trim((string) $name))] ?? null;
    }

    /**
     * URL awam logo, atau null jika nama tidak dikenali ATAU fail tiada.
     * Semakan is_file() disengajakan: <img> yang menunjuk fail yang hilang
     * memberi ikon rosak, sedangkan null membenarkan skrin jatuh balik kepada
     * lencana warna parti.
     */
    public static function url(?string $name): ?string
    {
        $slug = self::slug($name);

        if ($slug === null || ! is_file(public_path('images/parti/'.$slug.'.png'))) {
            return null;
        }

        return asset('images/parti/'.$slug.'.png');
    }

    public static function dataUri(?string $name): ?string
    {
        $slug = self::slug($name);
        if ($slug === null) {
            return null;
        }

        if (array_key_exists($slug, self::$cache)) {
            return self::$cache[$slug];
        }

        $path = public_path('images/parti/'.$slug.'.png');
        if (! is_file($path)) {
            return self::$cache[$slug] = null;
        }

        return self::$cache[$slug] = 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
