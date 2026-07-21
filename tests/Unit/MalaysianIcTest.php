<?php

namespace Tests\Unit;

use App\Support\MalaysianIc;
use PHPUnit\Framework\TestCase;

class MalaysianIcTest extends TestCase
{
    public function test_decodes_birth_year_from_both_centuries(): void
    {
        $this->assertSame(2008, MalaysianIc::birthYear('080304055012'));
        $this->assertSame(1980, MalaysianIc::birthYear('800101015001'));
        // Boundary of the century rule: 25 -> 2000s, 26 -> 1900s.
        $this->assertSame(2025, MalaysianIc::birthYear('250101015001'));
        $this->assertSame(1926, MalaysianIc::birthYear('260101015001'));
    }

    public function test_rejects_ics_without_a_valid_date(): void
    {
        $this->assertNull(MalaysianIc::birthYear('801301015001')); // month 13
        $this->assertNull(MalaysianIc::birthYear('800230015001')); // 30 Feb
        $this->assertNull(MalaysianIc::birthYear('abc'));
    }

    public function test_strips_punctuation(): void
    {
        $this->assertSame(1980, MalaysianIc::birthYear('800101-01-5001'));
    }

    /**
     * A roll entry decoding to a child means the leading digits are not a
     * birth date (foreign-born ICs). Unknown must stay unknown.
     */
    public function test_voter_birth_year_rejects_implausible_ages(): void
    {
        $childIc = str_pad((string) (((int) date('y') - 5 + 100) % 100), 2, '0', STR_PAD_LEFT).'1107745442';
        $this->assertNotNull(MalaysianIc::birthYear($childIc), 'decodes as a date');
        $this->assertNull(MalaysianIc::voterBirthYear($childIc), 'but is not a plausible voter');

        $this->assertSame(1980, MalaysianIc::voterBirthYear('800101015001'));
    }
}
