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

        // collapse() membawa balik label saluran mentah supaya suntingan
        // seterusnya boleh memancarkannya semula tanpa menghanyutkan kunci undi.
        $this->assertSame(
            [$pusat[0] + ['saluran_labels' => ['1', '2', '3']]],
            $back['pusat'],
        );
        $this->assertFalse($back['undi_awal']);
        $this->assertTrue($back['undi_pos']);
        $this->assertSame('UNDI POS', $back['undi_pos_label']);
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
            [
                'pusat' => [], 'undi_awal' => false, 'undi_pos' => false,
                'undi_awal_label' => null, 'undi_pos_label' => null,
            ],
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

    // ---------------------------------------------------------------------
    // Struktur berbentuk SCORESHEET, bukan keluaran expand() sendiri.
    //
    // Ujian round-trip yang sedia ada hanya menyuap collapse() dengan output
    // expand() — satu-satunya bentuk yang identitinya benar secara remeh.
    // Struktur scoresheet TIDAK berbentuk begitu: labelnya rentetan sebenar
    // yang dibaca daripada sheet, dan undi dikunci pada rentetan itu. Kalau
    // collapse()/expand() mengkanonkan semula label, kunci undi hanyut dan
    // survivingKeys() memadamnya sebagai yatim.
    // ---------------------------------------------------------------------

    public function test_collapse_preserves_a_non_canonical_postal_label(): void
    {
        // Yang dibaca AI daripada sheet sebenar, bukan literal berkanun.
        $scoresheet = ['rows' => [
            ['dm' => 'DM', 'pusat' => 'SK A', 'saluran' => '1'],
            ['dm' => '', 'pusat' => '', 'saluran' => 'UNDI POS AWAL'],
        ]];

        $out = $this->svc->collapse($scoresheet);

        $this->assertTrue($out['undi_awal']);
        $this->assertTrue($out['undi_pos']);
        // Rentetan mentah mesti dibawa keluar, bukan hanya dua boolean.
        $this->assertSame('UNDI POS AWAL', $out['undi_awal_label']);
        $this->assertSame('UNDI POS AWAL', $out['undi_pos_label']);
    }

    public function test_expand_reemits_a_preserved_postal_label_verbatim(): void
    {
        $new = $this->svc->expand([], true, false, 'UNDI POS AWAL');

        $this->assertSame(
            ['|UNDI POS AWAL'],
            array_keys($this->svc->survivingKeys($new)),
        );
    }

    public function test_collapse_and_expand_preserve_scoresheet_saluran_labels(): void
    {
        // Saluran KOSONG ialah kes produksi sebenar (rujuk
        // Borang14Controller.php:1178-1185). Undi dikunci 'SK A|'.
        $scoresheet = ['rows' => [
            ['dm' => 'DM', 'pusat' => 'SK A', 'saluran' => ''],
            ['dm' => 'DM', 'pusat' => 'SK A', 'saluran' => '2A'],
        ]];

        $out = $this->svc->collapse($scoresheet);
        $this->assertSame(['', '2A'], $out['pusat'][0]['saluran_labels']);

        // Tanpa suntingan, kunci mesti kembali SAMA — bukan '1','2'.
        $again = $this->svc->expand($out['pusat'], false, false);
        $this->assertSame(['SK A|', 'SK A|2A'], array_keys($this->svc->survivingKeys($again)));
    }

    public function test_expand_numbers_only_saluran_added_beyond_the_preserved_labels(): void
    {
        $pusat = [[
            'row_id' => 'pm_a', 'dm' => 'DM', 'pusat' => 'SK A',
            'saluran_count' => 3, 'saluran_labels' => ['', '2A'],
        ]];

        $new = $this->svc->expand($pusat, false, false);

        // Dua yang sedia ada kekal; yang KETIGA sahaja bernombor.
        $this->assertSame(['SK A|', 'SK A|2A', 'SK A|3'], array_keys($this->svc->survivingKeys($new)));
    }
}
