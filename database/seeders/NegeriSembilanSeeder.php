<?php

namespace Database\Seeders;

/**
 * Negeri Sembilan electoral hierarchy — 8 Parlimen (P126–P133) and 36 DUN
 * (N01–N36), from the current SPR delimitation (verified per federal-
 * constituency on Wikipedia; Juasseh (N15) sits under P129 Kuala Pilah, which
 * matches the live DPT roll). Daerah Mengundi come from DPT uploads, not here.
 *
 * See {@see StateElectoralSeeder} for the idempotent, duplicate-safe logic.
 */
class NegeriSembilanSeeder extends StateElectoralSeeder
{
    /** P-code => [parlimen name, [ [N-code, DUN name], ... ] ] */
    private const DATA = [
        'P126' => ['JELEBU', [['N01', 'CHENNAH'], ['N02', 'PERTANG'], ['N03', 'SUNGAI LUI'], ['N04', 'KLAWANG']]],
        'P127' => ['JEMPOL', [['N05', 'SERTING'], ['N06', 'PALONG'], ['N07', 'JERAM PADANG'], ['N08', 'BAHAU']]],
        'P128' => ['SEREMBAN', [['N09', 'LENGGENG'], ['N10', 'NILAI'], ['N11', 'LOBAK'], ['N12', 'TEMIANG'], ['N13', 'SIKAMAT'], ['N14', 'AMPANGAN']]],
        'P129' => ['KUALA PILAH', [['N15', 'JUASSEH'], ['N16', 'SERI MENANTI'], ['N17', 'SENALING'], ['N18', 'PILAH'], ['N19', 'JOHOL']]],
        'P130' => ['RASAH', [['N20', 'LABU'], ['N21', 'BUKIT KEPAYANG'], ['N22', 'RAHANG'], ['N23', 'MAMBAU'], ['N24', 'SEREMBAN JAYA']]],
        'P131' => ['REMBAU', [['N25', 'PAROI'], ['N26', 'CHEMBONG'], ['N27', 'RANTAU'], ['N28', 'KOTA']]],
        'P132' => ['PORT DICKSON', [['N29', 'CHUAH'], ['N30', 'LUKUT'], ['N31', 'BAGAN PINANG'], ['N32', 'LINGGI'], ['N33', 'SRI TANJUNG']]],
        'P133' => ['TAMPIN', [['N34', 'GEMAS'], ['N35', 'GEMENCHEH'], ['N36', 'REPAH']]],
    ];

    protected function negeriName(): string
    {
        return 'NEGERI SEMBILAN';
    }

    protected function data(): array
    {
        return self::DATA;
    }
}
