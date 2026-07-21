<?php

namespace App\Support;

/**
 * Short party code (PH, BN, PSM…) for a party name from Data Induk.
 *
 * The keahlian_parti master table stores only a name, but the Simulasi
 * Pilihanraya table, its pie labels and the PDF all key off a short code. The
 * well-known coalitions keep their conventional codes — the same spellings
 * PartyLogo recognises, so logos and colours keep matching — and anything else
 * is reduced to a predictable abbreviation.
 */
class PartyCode
{
    /** Conventional codes; keys are uppercased names with punctuation intact. */
    private const KNOWN = [
        'PAKATAN HARAPAN' => 'PH',
        'HARAPAN' => 'PH',
        'BARISAN NASIONAL' => 'BN',
        'PERIKATAN NASIONAL' => 'PN',
        'GABUNGAN PARTI SARAWAK' => 'GPS',
        'GABUNGAN RAKYAT SABAH' => 'GRS',
        'PARTI KEADILAN RAKYAT' => 'PKR',
        'KEADILAN' => 'PKR',
        'CALON BEBAS' => 'BEBAS',
        'BEBAS' => 'BEBAS',
        'LAIN-LAIN' => 'LAIN',
    ];

    /** Words that carry no identity and are skipped when building initials. */
    private const NOISE = ['DAN', 'DI', 'THE', 'OF'];

    public static function fromName(?string $nama): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', (string) $nama));
        // Drop any parenthetical suffix: "Pakatan Harapan (PH)" -> "Pakatan Harapan".
        $clean = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $clean));
        $upper = mb_strtoupper($clean);

        if ($upper === '') {
            return '';
        }
        if (isset(self::KNOWN[$upper])) {
            return self::KNOWN[$upper];
        }

        $words = array_values(array_filter(
            preg_split('/[\s\-\/]+/', $upper) ?: [],
            fn ($w) => $w !== '' && ! in_array($w, self::NOISE, true),
        ));

        // A single word is already its own code (PEJUANG, MUDA, PAS…).
        if (count($words) <= 1) {
            return mb_substr($words[0] ?? $upper, 0, 12);
        }

        // Otherwise initials: "Parti Sosialis Malaysia" -> PSM.
        return mb_substr(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), $words)), 0, 12);
    }

    /**
     * Codes for a list of names, guaranteed unique and stable in order.
     * A collision gets a numeric suffix (PSM, PSM2) rather than silently
     * merging two different parties into one column.
     *
     * @param  iterable<string>  $names
     * @return array<int, array{kod:string, nama:string}>
     */
    public static function forNames(iterable $names): array
    {
        $out = [];
        $used = [];
        foreach ($names as $nama) {
            $nama = trim((string) $nama);
            if ($nama === '') {
                continue;
            }
            $kod = self::fromName($nama);
            if ($kod === '') {
                continue;
            }
            if (isset($used[$kod])) {
                $used[$kod]++;
                $kod .= $used[$kod];
            } else {
                $used[$kod] = 1;
            }
            $out[] = ['kod' => $kod, 'nama' => $nama];
        }

        return $out;
    }
}
