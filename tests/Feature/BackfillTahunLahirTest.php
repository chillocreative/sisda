<?php

namespace Tests\Feature;

use App\Models\PangkalanDataPengundi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillTahunLahirTest extends TestCase
{
    use RefreshDatabase;

    private function voter(string $ic, ?string $tahunLahir = null): PangkalanDataPengundi
    {
        return PangkalanDataPengundi::create([
            'no_ic' => $ic,
            'nama' => 'PENGUNDI '.$ic,
            'tahun_lahir' => $tahunLahir,
        ]);
    }

    public function test_fills_blank_years_from_the_ic(): void
    {
        $a = $this->voter('800101015001');
        $b = $this->voter('650715085432', '');

        $this->artisan('pengundi:backfill-tahun-lahir')->assertSuccessful();

        $this->assertSame('1980', $a->fresh()->tahun_lahir);
        $this->assertSame('1965', $b->fresh()->tahun_lahir);
    }

    public function test_never_overwrites_a_year_that_came_from_a_file(): void
    {
        $v = $this->voter('800101015001', '1975');

        $this->artisan('pengundi:backfill-tahun-lahir')->assertSuccessful();

        $this->assertSame('1975', $v->fresh()->tahun_lahir, 'file value must win');
    }

    /** Unknown stays unknown — an undecodable IC must not be given a year. */
    public function test_leaves_implausible_ics_null(): void
    {
        $child = str_pad((string) (((int) date('y') - 5 + 100) % 100), 2, '0', STR_PAD_LEFT).'1107745442';
        $junk = $this->voter('000000000000');
        $kid = $this->voter($child);

        $this->artisan('pengundi:backfill-tahun-lahir')->assertSuccessful();

        $this->assertNull($junk->fresh()->tahun_lahir);
        $this->assertNull($kid->fresh()->tahun_lahir);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $v = $this->voter('800101015001');

        $this->artisan('pengundi:backfill-tahun-lahir --dry-run')->assertSuccessful();

        $this->assertNull($v->fresh()->tahun_lahir);
    }
}
