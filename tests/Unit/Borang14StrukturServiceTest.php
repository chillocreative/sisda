<?php
// tests/Unit/Borang14StrukturServiceTest.php
//
// Logik BENTUK sahaja — tiada DB, tiada HTTP. Kelas ini yang memutuskan undi
// mana akan berpindah dan undi mana akan dipadam apabila struktur disunting,
// jadi setiap keputusan itu dikunci di sini.
namespace Tests\Unit;

use App\Services\Borang14StrukturService;
use PHPUnit\Framework\TestCase;

class Borang14StrukturServiceTest extends TestCase
{
    private Borang14StrukturService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new Borang14StrukturService;
    }

    public function test_expand_writes_one_row_per_saluran(): void
    {
        $out = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 3],
            ['row_id' => 'pm_b', 'dm' => 'JUASSEH', 'pusat' => 'DEWAN ORANG RAMAI', 'saluran_count' => 1],
        ], false, false);

        $this->assertSame('manual', $out['origin']);
        $this->assertSame([], $out['calon']);
        $this->assertCount(4, $out['rows']);
        $this->assertSame(
            ['1', '2', '3'],
            collect($out['rows'])->where('pusat', 'SK TENGKEK')->pluck('saluran')->all(),
        );
        $this->assertSame('pm_a', $out['rows'][0]['row_id']);
    }

    public function test_expand_never_fabricates_printed_figures(): void
    {
        $out = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK A', 'saluran_count' => 1],
        ], false, false);

        // Tiada sheet bercetak untuk dibaca — 'a'/'jumlah_undian'/'undi' MESTI
        // tidak wujud langsung, bukan 0. Sifar di sini akan menjadi angka yang
        // direka dan crosscheck akan menuduh pengguna berbohong.
        $this->assertSame(['row_id', 'dm', 'pusat', 'saluran'], array_keys($out['rows'][0]));
    }

    public function test_expand_adds_undi_awal_and_pos_rows_only_when_flagged(): void
    {
        $none = $this->svc->expand([], false, false);
        $this->assertSame([], $none['rows']);

        $both = $this->svc->expand([], true, true);
        $this->assertSame(['UNDI AWAL', 'UNDI POS'], collect($both['rows'])->pluck('saluran')->all());
        $this->assertSame('', $both['rows'][0]['pusat']);
    }

    public function test_collapse_is_the_inverse_of_expand(): void
    {
        $pusat = [
            ['row_id' => 'pm_a', 'dm' => 'KUALA JEMAPOH', 'pusat' => 'SK TENGKEK', 'saluran_count' => 3],
        ];
        $back = $this->svc->collapse($this->svc->expand($pusat, false, true));

        $this->assertSame($pusat, $back['pusat']);
        $this->assertFalse($back['undi_awal']);
        $this->assertTrue($back['undi_pos']);
    }

    public function test_collapse_derives_a_stable_row_id_for_legacy_structures(): void
    {
        // Struktur scoresheet/warisan tiada row_id. Membukanya untuk disunting
        // mesti memberi id yang SAMA setiap kali, jika tidak suntingan kedua
        // akan nampak setiap pusat sebagai "baharu" dan cascade akan memadam
        // undi yang sepatutnya berpindah.
        $legacy = ['rows' => [
            ['dm' => 'DM', 'pusat' => 'SK A', 'saluran' => '1', 'a' => 120],
            ['dm' => 'DM', 'pusat' => 'SK A', 'saluran' => '2', 'a' => 118],
        ]];

        $first = $this->svc->collapse($legacy);
        $second = $this->svc->collapse($legacy);

        $this->assertSame($first['pusat'][0]['row_id'], $second['pusat'][0]['row_id']);
        $this->assertNotSame('', $first['pusat'][0]['row_id']);
        $this->assertSame(2, $first['pusat'][0]['saluran_count']);
    }

    public function test_collapse_of_null_structure_is_empty_not_an_error(): void
    {
        $this->assertSame(
            ['pusat' => [], 'undi_awal' => false, 'undi_pos' => false],
            $this->svc->collapse(null),
        );
    }

    public function test_rename_map_matches_on_row_id_not_on_name(): void
    {
        $old = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK TENGKEK', 'saluran_count' => 1],
            ['row_id' => 'pm_b', 'dm' => 'DM', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ], false, false);

        $map = $this->svc->renameMap($old, [
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SEKOLAH KEBANGSAAN TENGKEK', 'saluran_count' => 1],
            ['row_id' => 'pm_b', 'dm' => 'DM', 'pusat' => 'SK JEMAPOH', 'saluran_count' => 1],
        ]);

        // Hanya yang benar-benar bertukar nama.
        $this->assertSame(['SK TENGKEK' => 'SEKOLAH KEBANGSAAN TENGKEK'], $map);
    }

    public function test_rename_map_ignores_rows_the_edit_dropped(): void
    {
        $old = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK A', 'saluran_count' => 1],
        ], false, false);

        $this->assertSame([], $this->svc->renameMap($old, []));
    }

    public function test_surviving_keys_lists_every_kept_cell(): void
    {
        $new = $this->svc->expand([
            ['row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK A', 'saluran_count' => 2],
        ], false, true);

        $this->assertSame(
            ['SK A|1', 'SK A|2', '|UNDI POS'],
            array_keys($this->svc->survivingKeys($new)),
        );
    }
}
