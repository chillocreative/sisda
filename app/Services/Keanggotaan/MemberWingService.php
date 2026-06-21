<?php

namespace App\Services\Keanggotaan;

/**
 * Classifies a member into the party youth wings — AMK (lelaki), Srikandi &
 * Wanita (perempuan) — from their age and gender, relative to the active
 * "Penggal Pemilihan Parti" (party-election term).
 *
 * Eligibility is locked at the term's start year: a member qualifies for the
 * term if they were <= 35 when it began. Rule:
 *  - age <= 35                                   → wing member, valid.
 *  - age >  35, but was <= 35 at the term's start
 *    and today is within the term                → still a wing member, flagged
 *    `grace` (front-end shows light red): ages out when the term ends.
 *  - already > 35 at the term's start, or the
 *    term has ended/is unset                     → no wing.
 *
 * Derived live (never stored) so it always reflects the current term & age.
 */
class MemberWingService
{
    public const MAX_AGE = 35;

    /**
     * @return array{wings: list<string>, grace: bool}
     */
    public static function classify(?int $umur, ?string $jantina, ?int $tahunMula, ?int $tahunTamat, int $currentYear): array
    {
        $none = ['wings' => [], 'grace' => false];

        if ($umur === null || $jantina === null || $jantina === '') {
            return $none;
        }

        if ($umur <= self::MAX_AGE) {
            $grace = false;
        } else {
            // Over-age: only valid if the term is running and they were still
            // within the age limit when it started.
            if (! self::withinTerm($tahunMula, $tahunTamat, $currentYear)) {
                return $none;
            }
            $ageAtStart = $umur - ($currentYear - $tahunMula);
            if ($ageAtStart > self::MAX_AGE) {
                return $none;
            }
            $grace = true;
        }

        $wings = strtoupper($jantina) === 'LELAKI' ? ['AMK'] : ['Srikandi', 'Wanita'];

        return ['wings' => $wings, 'grace' => $grace];
    }

    public static function withinTerm(?int $tahunMula, ?int $tahunTamat, int $currentYear): bool
    {
        return $tahunMula !== null && $tahunTamat !== null
            && $currentYear >= $tahunMula && $currentYear <= $tahunTamat;
    }
}
