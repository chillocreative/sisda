<?php

namespace Database\Seeders;

/**
 * Pulau Pinang electoral hierarchy — 13 Parlimen (P041–P053) and 40 DUN
 * (N01–N40), from the current SPR delimitation (verified per federal-
 * constituency on Wikipedia; Bertam (N02) sits under P041 Kepala Batas, which
 * matches the live DPT roll). Daerah Mengundi come from DPT uploads, not here.
 *
 * See {@see StateElectoralSeeder} for the idempotent, duplicate-safe logic.
 */
class PenangSeeder extends StateElectoralSeeder
{
    /** P-code => [parlimen name, [ [N-code, DUN name], ... ] ] */
    private const DATA = [
        'P041' => ['KEPALA BATAS', [['N01', 'PENAGA'], ['N02', 'BERTAM'], ['N03', 'PINANG TUNGGAL']]],
        'P042' => ['TASEK GELUGOR', [['N04', 'PERMATANG BERANGAN'], ['N05', 'SUNGAI DUA'], ['N06', 'TELOK AYER TAWAR']]],
        'P043' => ['BAGAN', [['N07', 'SUNGAI PUYU'], ['N08', 'BAGAN JERMAL'], ['N09', 'BAGAN DALAM']]],
        'P044' => ['PERMATANG PAUH', [['N10', 'SEBERANG JAYA'], ['N11', 'PERMATANG PASIR'], ['N12', 'PENANTI']]],
        'P045' => ['BUKIT MERTAJAM', [['N13', 'BERAPIT'], ['N14', 'MACHANG BUBUK'], ['N15', 'PADANG LALANG']]],
        'P046' => ['BATU KAWAN', [['N16', 'PERAI'], ['N17', 'BUKIT TENGAH'], ['N18', 'BUKIT TAMBUN']]],
        'P047' => ['NIBONG TEBAL', [['N19', 'JAWI'], ['N20', 'SUNGAI BAKAP'], ['N21', 'SUNGAI ACHEH']]],
        'P048' => ['BUKIT BENDERA', [['N22', 'TANJONG BUNGA'], ['N23', 'AIR PUTIH'], ['N24', 'KEBUN BUNGA'], ['N25', 'PULAU TIKUS']]],
        'P049' => ['TANJONG', [['N26', 'PADANG KOTA'], ['N27', 'PENGKALAN KOTA'], ['N28', 'KOMTAR']]],
        'P050' => ['JELUTONG', [['N29', 'DATOK KERAMAT'], ['N30', 'SUNGAI PINANG'], ['N31', 'BATU LANCANG']]],
        'P051' => ['BUKIT GELUGOR', [['N32', 'SERI DELIMA'], ['N33', 'AIR ITAM'], ['N34', 'PAYA TERUBONG']]],
        'P052' => ['BAYAN BARU', [['N35', 'BATU UBAN'], ['N36', 'PANTAI JEREJAK'], ['N37', 'BATU MAUNG']]],
        'P053' => ['BALIK PULAU', [['N38', 'BAYAN LEPAS'], ['N39', 'PULAU BETONG'], ['N40', 'TELOK BAHANG']]],
    ];

    protected function negeriName(): string
    {
        return 'PULAU PINANG';
    }

    protected function data(): array
    {
        return self::DATA;
    }
}
