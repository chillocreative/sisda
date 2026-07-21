<?php

namespace Tests\Unit;

use App\Support\PartyCode;
use PHPUnit\Framework\TestCase;

class PartyCodeTest extends TestCase
{
    public function test_known_coalitions_keep_their_conventional_codes(): void
    {
        $this->assertSame('PH', PartyCode::fromName('Pakatan Harapan'));
        $this->assertSame('BN', PartyCode::fromName('BARISAN NASIONAL'));
        $this->assertSame('PN', PartyCode::fromName('Perikatan Nasional'));
        $this->assertSame('BEBAS', PartyCode::fromName('Calon Bebas'));
        $this->assertSame('PKR', PartyCode::fromName('Parti Keadilan Rakyat'));
    }

    public function test_single_word_names_are_their_own_code(): void
    {
        $this->assertSame('PEJUANG', PartyCode::fromName('PEJUANG'));
        $this->assertSame('MUDA', PartyCode::fromName('Muda'));
    }

    public function test_multi_word_names_become_initials(): void
    {
        $this->assertSame('PSM', PartyCode::fromName('Parti Sosialis Malaysia'));
        $this->assertSame('PBM', PartyCode::fromName('Parti Bangsa Malaysia'));
    }

    public function test_ignores_parenthetical_suffix_and_extra_spacing(): void
    {
        $this->assertSame('PH', PartyCode::fromName('  Pakatan   Harapan (PH) '));
    }

    public function test_blank_name_yields_no_code(): void
    {
        $this->assertSame('', PartyCode::fromName(''));
        $this->assertSame('', PartyCode::fromName(null));
    }

    /** Two different parties must never collapse into one column. */
    public function test_collisions_get_a_suffix(): void
    {
        $out = PartyCode::forNames(['Parti Sosialis Malaysia', 'Parti Sedar Malaysia']);

        $this->assertSame('PSM', $out[0]['kod']);
        $this->assertSame('PSM2', $out[1]['kod']);
        $this->assertSame('Parti Sedar Malaysia', $out[1]['nama']);
    }

    public function test_skips_blank_entries(): void
    {
        $this->assertSame(
            [['kod' => 'PH', 'nama' => 'Pakatan Harapan']],
            PartyCode::forNames(['', '  ', 'Pakatan Harapan']),
        );
    }
}
