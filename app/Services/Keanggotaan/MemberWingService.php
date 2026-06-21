<?php

namespace App\Services\Keanggotaan;

/**
 * Classifies a member into the party wings from age & gender:
 *  - AMK      = lelaki, youth (age <= 35).
 *  - Srikandi = perempuan, youth (age <= 35).
 *  - Wanita   = perempuan, ALL ages (women's wing — not age-capped).
 *
 * AMK & Srikandi eligibility is locked at the "Penggal Pemilihan Parti" start
 * year: a member who was <= 35 when the term began keeps youth status as they
 * age past 35 until the term ends — those years are flagged `grace` (light red).
 * Wanita is never grace (no age cap).
 *
 * Derived live (never stored) so it always reflects the current term & age.
 */
class MemberWingService
{
    public const MAX_AGE = 35;

    /**
     * @return array{wings: list<string>, graceWings: list<string>, grace: bool}
     */
    public static function classify(?int $umur, ?string $jantina, ?int $tahunMula, ?int $tahunTamat, int $currentYear): array
    {
        $none = ['wings' => [], 'graceWings' => [], 'grace' => false];

        if ($umur === null || $jantina === null || $jantina === '') {
            return $none;
        }

        // Youth eligibility (for AMK / Srikandi): true = valid, 'grace' = aging
        // out but still valid this term, false = not eligible.
        $youth = self::youthStatus($umur, $tahunMula, $tahunTamat, $currentYear);

        $wings = [];
        $graceWings = [];

        if (strtoupper($jantina) === 'LELAKI') {
            if ($youth !== false) {
                $wings[] = 'AMK';
                if ($youth === 'grace') {
                    $graceWings[] = 'AMK';
                }
            }
        } else {
            if ($youth !== false) {
                $wings[] = 'Srikandi';
                if ($youth === 'grace') {
                    $graceWings[] = 'Srikandi';
                }
            }
            // Women's wing — every female member, regardless of age.
            $wings[] = 'Wanita';
        }

        return ['wings' => $wings, 'graceWings' => $graceWings, 'grace' => $graceWings !== []];
    }

    /** @return bool|string true (valid), 'grace' (aging out, still valid), or false. */
    private static function youthStatus(int $umur, ?int $tahunMula, ?int $tahunTamat, int $currentYear): bool|string
    {
        if ($umur <= self::MAX_AGE) {
            return true;
        }
        if (! self::withinTerm($tahunMula, $tahunTamat, $currentYear)) {
            return false;
        }
        $ageAtStart = $umur - ($currentYear - $tahunMula);

        return $ageAtStart > self::MAX_AGE ? false : 'grace';
    }

    public static function withinTerm(?int $tahunMula, ?int $tahunTamat, int $currentYear): bool
    {
        return $tahunMula !== null && $tahunTamat !== null
            && $currentYear >= $tahunMula && $currentYear <= $tahunTamat;
    }
}
