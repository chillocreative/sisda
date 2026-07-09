<?php

namespace App\Support\Pilihanraya;

/**
 * DUN N01 Buloh Kasap (Segamat, Johor) election dataset.
 *
 * Digitised from the "simulasi-PH-vs-BN-v2" workbook. These figures seed the
 * Pilihanraya → Analisa pages so the tables and charts render with real data
 * out of the box; the Keputusan page also accepts an uploaded scoresheet that
 * overrides this baseline at runtime.
 */
class JohorElectionData
{
    public const DUN = 'N01 BULOH KASAP';
    public const PARLIMEN = 'P140 SEGAMAT';
    public const NEGERI = 'JOHOR';

    /**
     * KEPUTUSAN PRN Johor ke-15 (2022) — ringkasan Daerah Mengundi.
     * Ethnic composition (%) is from DPPR 2026; votes are the sah tally.
     */
    public static function keputusan2022(): array
    {
        // [dm, pemilih, melayu%, cina%, india%, keluar, ph, pejuang, pn, bn, ditolak]
        $raw = [
            ['MENSUDOT LAMA', 560, 0.9768, 0.0214, 0.0018, 382, 5, 1, 93, 283, 4],
            ['BALAI BADANG', 1087, 0.8951, 0.0451, 0.0598, 701, 16, 6, 197, 482, 10],
            ['PALONG TIMOR', 5363, 0.9851, 0.0149, 0.0000, 3400, 50, 14, 622, 2714, 105],
            ['SEPANG LOI', 1041, 0.8002, 0.1988, 0.0010, 635, 65, 4, 219, 347, 15],
            ['MENSUDOT PINDAH', 627, 0.9825, 0.0175, 0.0000, 440, 4, 3, 89, 344, 10],
            ['AWAT', 529, 0.9905, 0.0095, 0.0000, 357, 6, 9, 69, 273, 11],
            ['PEKAN GEMAS BAHRU', 3515, 0.1030, 0.6219, 0.2751, 1483, 776, 24, 187, 496, 76],
            ['GOMALI', 403, 0.3548, 0.0893, 0.5558, 217, 57, 2, 16, 142, 8],
            ['TAMBANG', 289, 0.4256, 0.1626, 0.4118, 166, 59, 1, 35, 71, 8],
            ['PAYA LANG', 1093, 0.7365, 0.0887, 0.1747, 681, 82, 15, 169, 415, 21],
            ['LADANG SUNGAI MUAR', 663, 0.6184, 0.1207, 0.2609, 387, 43, 7, 80, 257, 9],
            ['KUALA PAYA', 1126, 0.9689, 0.0258, 0.0053, 752, 15, 8, 220, 509, 17],
            ['BANDAR BULOH KASAP UTARA', 865, 0.1087, 0.8671, 0.0243, 417, 276, 3, 22, 116, 18],
            ['BANDAR BULOH KASAP SELATAN', 2540, 0.1441, 0.8071, 0.0488, 1111, 659, 21, 89, 342, 44],
            ['BULOH KASAP', 7635, 0.3840, 0.4930, 0.1230, 3237, 1374, 59, 535, 1269, 60],
            ['GELANG CHINCHIN', 936, 0.9679, 0.0235, 0.0085, 597, 19, 5, 148, 425, 22],
            ['SEPINANG', 701, 0.9429, 0.0243, 0.0328, 453, 13, 6, 144, 290, 6],
            ['UNDI POS', null, null, null, null, 298, 60, 2, 64, 172, 20],
            ['UNDI AWAL', null, null, null, null, 10, 0, 0, 1, 9, 0],
        ];

        return array_map(fn ($r) => [
            'dm' => $r[0],
            'pemilih' => $r[1],
            'melayu' => $r[2],
            'cina' => $r[3],
            'india' => $r[4],
            'keluar' => $r[5],
            'ph' => $r[6],
            'pejuang' => $r[7],
            'pn' => $r[8],
            'bn' => $r[9],
            'ditolak' => $r[10],
        ], $raw);
    }

    public static function keputusan2022Totals(): array
    {
        return [
            'pemilih' => 28973,
            'keluar' => 15724,
            'ph' => 3579,
            'pejuang' => 190,
            'pn' => 2999,
            'bn' => 8956,
            'ditolak' => 464,
            'majoriti_bn' => 5377,
            'undi_pn' => 2999,
        ];
    }

    /**
     * Analisa Kaum Mengikut Daerah Mengundi (anggaran kaum dari corak nama, DPPR).
     */
    public static function kaumDm(): array
    {
        // [dm, melayu, cina, india, lain]
        $raw = [
            ['BULOH KASAP', 2997, 3694, 944, 0],
            ['PALONG TIMOR', 5352, 9, 2, 0],
            ['PEKAN GEMAS BAHRU', 369, 2176, 968, 2],
            ['BANDAR BULOH KASAP SELATAN', 373, 2041, 126, 0],
            ['KUALA PAYA', 1112, 8, 6, 0],
            ['PAYA LANG', 821, 79, 193, 0],
            ['BALAI BADANG', 994, 23, 69, 1],
            ['SEPANG LOI', 842, 197, 1, 1],
            ['GELANG CHINCHIN', 920, 8, 8, 0],
            ['BANDAR BULOH KASAP UTARA', 97, 744, 24, 0],
            ['SEPINANG', 668, 10, 23, 0],
            ['LADANG SUNGAI MUAR', 417, 69, 177, 0],
            ['MENSUDOT PINDAH', 624, 3, 0, 0],
            ['MENSUDOT LAMA', 556, 3, 1, 0],
            ['AWAT', 528, 1, 0, 0],
            ['GOMALI', 145, 31, 226, 1],
            ['TAMBANG', 128, 40, 121, 0],
        ];

        return array_map(function ($r, $i) {
            $jumlah = $r[1] + $r[2] + $r[3] + $r[4];

            return [
                'bil' => $i + 1,
                'dm' => $r[0],
                'melayu' => $r[1],
                'cina' => $r[2],
                'india' => $r[3],
                'lain' => $r[4],
                'jumlah' => $jumlah,
            ];
        }, $raw, array_keys($raw));
    }

    public static function kaumDmTotals(): array
    {
        return [
            'melayu' => 16943,
            'cina' => 9136,
            'india' => 2889,
            'lain' => 5,
            'jumlah' => 28973,
        ];
    }

    /**
     * MINIMA untuk PH menang — andaian + tiga jadual sensitiviti.
     */
    public static function minima(): array
    {
        return [
            'andaian' => [
                'pengundi_melayu' => 16668,
                'pengundi_cina' => 9443,
                'pengundi_india' => 2862,
                'turnout_melayu' => 0.68,
                'sokongan_ph_cina' => 0.90,
                'sokongan_ph_india' => 0.40,
            ],
            // Jadual 1: sokongan Melayu minimum ikut turnout Cina+India
            'jadual1' => array_map(fn ($r) => [
                'turnout_ci' => $r[0], 'sokongan_min' => $r[1], 'anjakan' => $r[2], 'status' => $r[3],
            ], [
                [0.50, 0.3460, 0.3260, 'SANGAT SUKAR'],
                [0.60, 0.3152, 0.2952, 'SANGAT SUKAR'],
                [0.70, 0.2844, 0.2644, 'SANGAT SUKAR'],
                [0.75, 0.2690, 0.2490, 'SUKAR'],
                [0.80, 0.2536, 0.2336, 'SUKAR'],
                [0.85, 0.2382, 0.2182, 'SUKAR'],
                [0.90, 0.2228, 0.2028, 'SUKAR'],
            ]),
            // Jadual 2: turnout Cina+India minimum ikut sokongan Melayu
            'jadual2' => array_map(fn ($r) => [
                'sokongan_melayu' => $r[0], 'turnout_min' => $r[1], 'status' => $r[2],
            ], [
                [0.15, 1.1363, 'TIDAK REALISTIK'],
                [0.20, 0.9740, 'TIDAK REALISTIK'],
                [0.25, 0.8117, 'SUKAR'],
                [0.27, 0.7467, 'BOLEH DICAPAI'],
                [0.30, 0.6493, 'BOLEH DICAPAI'],
                [0.35, 0.4870, 'BOLEH DICAPAI'],
            ]),
            // Jadual 3: kesan peralihan undi PN 2022 (±2,999 undi)
            'jadual3' => array_map(fn ($r) => [
                'pn_bn' => $r[0], 'pn_ph' => $r[1], 'pn_tak_keluar' => $r[2],
                'undi_ph' => $r[3], 'undi_bn' => $r[4], 'keputusan' => $r[5],
            ], [
                [0.90, 0.00, 0.10, 7611.225, 12627.825, 'BN MENANG'],
                [0.70, 0.10, 0.20, 7911.125, 12028.025, 'BN MENANG'],
                [0.60, 0.15, 0.25, 8061.075, 11728.125, 'BN MENANG'],
                [0.50, 0.20, 0.30, 8211.025, 11428.225, 'BN MENANG'],
                [0.40, 0.25, 0.35, 8360.975, 11128.325, 'BN MENANG'],
                [0.30, 0.30, 0.40, 8510.925, 10828.425, 'BN MENANG'],
            ]),
        ];
    }

    /**
     * SIMULASI PRN ke-16 (2026) — baseline andaian untuk kalkulator 1 lawan 1.
     */
    public static function simulasi2026(): array
    {
        return [
            'pengundi' => [
                'melayu' => 16668,
                'cina' => 9443,
                'india' => 2862,
            ],
            'andaian' => [
                'melayu' => ['turnout' => 0.68, 'sokongan_ph' => 0.228],
                'cina' => ['turnout' => 0.85, 'sokongan_ph' => 0.795],
                'india' => ['turnout' => 0.85, 'sokongan_ph' => 0.795],
            ],
        ];
    }
}
