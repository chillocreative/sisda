<?php

namespace App\Support;

/**
 * Birth date decoding for Malaysian ICs (MyKad), in one place.
 *
 * A 12-digit IC starts with the birth date as YYMMDD. The century rule is the
 * long-standing one used across SISDA: a two-digit year of 25 or less is read
 * as 2000s, anything higher as 1900s.
 */
class MalaysianIc
{
    /**
     * The lowest age we will accept as a real birth date on a voter roll.
     *
     * Registration is automatic at 18, so a roll entry decoding to a child is
     * proof the IC's leading digits are not a birth date at all — this happens
     * with foreign-born ICs, whose state code occupies digits that would
     * otherwise read as a plausible date. Those stay unknown rather than being
     * published as a birth year.
     */
    private const MIN_VOTER_AGE = 17;

    /**
     * Birth date as [year, month, day], or null when the leading six digits
     * are not a valid calendar date.
     *
     * @return array{0:int, 1:int, 2:int}|null
     */
    public static function birthDate(string $ic): ?array
    {
        $digits = preg_replace('/\D/', '', $ic);
        if (! preg_match('/^[0-9]{6}/', $digits)) {
            return null;
        }
        $yy = (int) substr($digits, 0, 2);
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);
        $year = $yy <= 25 ? 2000 + $yy : 1900 + $yy;

        return checkdate($mm, $dd, $year) ? [$year, $mm, $dd] : null;
    }

    /** Birth year, or null when the IC carries no valid date. */
    public static function birthYear(string $ic): ?int
    {
        return self::birthDate($ic)[0] ?? null;
    }

    /** Age today, or null when the IC carries no valid date. */
    public static function age(string $ic): ?int
    {
        $date = self::birthDate($ic);
        if ($date === null) {
            return null;
        }
        [$year, $mm, $dd] = $date;

        $age = (int) date('Y') - $year;
        if ((int) date('n') < $mm || ((int) date('n') === $mm && (int) date('j') < $dd)) {
            $age--;
        }

        return ($age >= 0 && $age <= 150) ? $age : null;
    }

    /**
     * Birth year for a voter-roll record: the decoded year, but only when the
     * resulting age is plausible for someone on the roll. Returns null (unknown)
     * otherwise — never a guess.
     */
    public static function voterBirthYear(string $ic): ?int
    {
        $age = self::age($ic);
        if ($age === null || $age < self::MIN_VOTER_AGE) {
            return null;
        }

        return self::birthYear($ic);
    }
}
