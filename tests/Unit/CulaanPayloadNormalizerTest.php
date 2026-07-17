<?php

namespace Tests\Unit;

use App\Services\CulaanPayloadNormalizer;
use PHPUnit\Framework\TestCase;

class CulaanPayloadNormalizerTest extends TestCase
{
    public function test_flattens_checkbox_array_to_comma_separated_string(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'jenis_sumbangan' => ['Tunai', 'Barangan'],
        ]);

        $this->assertSame('Tunai, Barangan', $out['jenis_sumbangan']);
    }

    public function test_substitutes_lain_free_text_and_drops_the_lain_key(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'jenis_sumbangan' => ['Tunai', 'Lain-lain'],
            'jenis_sumbangan_lain' => 'Baucar buku',
        ]);

        $this->assertSame('Tunai, Baucar buku', $out['jenis_sumbangan']);
        $this->assertArrayNotHasKey('jenis_sumbangan_lain', $out);
    }

    public function test_jenis_pekerjaan_uses_exact_match_not_fuzzy(): void
    {
        // 'Pelbagai lain' contains 'lain' but is NOT the Lain-lain option.
        // jenis_pekerjaan matches exactly, so it must survive untouched.
        $out = CulaanPayloadNormalizer::normalize([
            'jenis_pekerjaan' => ['Pelbagai lain'],
            'jenis_pekerjaan_lain' => 'IGNORED',
        ]);

        $this->assertSame('Pelbagai lain', $out['jenis_pekerjaan']);
    }

    public function test_pemilik_rumah_lain_replaces_value(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'pemilik_rumah' => 'Lain-lain',
            'pemilik_rumah_lain' => 'Rumah pusaka',
        ]);

        $this->assertSame('Rumah pusaka', $out['pemilik_rumah']);
        $this->assertArrayNotHasKey('pemilik_rumah_lain', $out);
    }

    public function test_lain_option_is_kept_when_free_text_is_empty(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'jenis_sumbangan' => ['Tunai', 'Lain-lain'],
            'jenis_sumbangan_lain' => '',
        ]);

        // Empty free-text means the Lain-lain entry stays as-is (current behaviour).
        $this->assertSame('Tunai, Lain-lain', $out['jenis_sumbangan']);
    }

    public function test_zpp_jenis_bantuan_flattens_without_lain_handling(): void
    {
        $out = CulaanPayloadNormalizer::normalize([
            'zpp_jenis_bantuan' => ['A', 'B'],
        ]);

        $this->assertSame('A, B', $out['zpp_jenis_bantuan']);
    }

    public function test_passes_through_unrelated_scalar_fields(): void
    {
        $out = CulaanPayloadNormalizer::normalize(['nama' => 'Ahmad', 'umur' => 40]);

        $this->assertSame('Ahmad', $out['nama']);
        $this->assertSame(40, $out['umur']);
    }
}
