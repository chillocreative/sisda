<?php

namespace Tests\Unit;

use App\Imports\KeanggotaanImport;
use PHPUnit\Framework\TestCase;

class KeanggotaanImportEkycTest extends TestCase
{
    /** The real Juasseh layout: EKYC sits between STATUS KEANGGOTAAN and PARLIMEN. */
    public function test_extracts_status_ekyc_from_real_header_layout(): void
    {
        $rows = [
            ['NAMA', 'NO KAD PENGENALAN', 'NO ANGGOTA', 'NO TELEFON', 'E-MEL', 'CABANG', 'NEGERI', 'ALAMAT', 'BANDAR', 'POSKOD', 'NEGERI (ALAMAT)', 'TARIKH LAHIR', 'JANTINA', 'KAUM', 'STATUS KEANGGOTAAN', 'STATUS EKYC', 'PARLIMEN', 'DUN'],
            ['MAIZATUL HANA BINTI MOHD', '000110050368', 'N129001872', '01112801063', 'a@b.com', 'Kuala Pilah', 'Negeri Sembilan', 'NO 37', 'KUALA PILAH', '72500', 'N9', '2000-01-10', 'wanita', 'Melayu', 'Approved', 'Completed', 'KUALA PILAH', 'JUASSEH'],
            ['NOR BARIZAH BINTI NORDIN', '000206050274', 'N129028402', '0182394667', 'c@d.com', 'Kuala Pilah', 'Negeri Sembilan', '17B', 'Juasseh', '72500', 'N9', '2000-02-06', 'wanita', 'Melayu', 'Approved', 'Pending', 'KUALA PILAH', 'JUASSEH'],
        ];

        $out = KeanggotaanImport::extract($rows);

        $this->assertCount(2, $out);
        $this->assertSame('completed', $out[0]['ekyc']);
        $this->assertSame('pending', $out[1]['ekyc']);

        // The neighbouring STATUS KEANGGOTAAN column must not be mistaken for
        // the EKYC one, and maps to the app's own status values.
        $this->assertSame('aktif', $out[0]['status']);
        $this->assertSame('aktif', $out[1]['status']);
    }

    /** No EKYC column at all — unknown must stay null, never 'pending'. */
    public function test_missing_ekyc_column_yields_null(): void
    {
        $rows = [
            ['NAMA', 'NO KAD PENGENALAN', 'NO TELEFON'],
            ['ALI BIN ABU', '800101015001', '0123456789'],
        ];

        $out = KeanggotaanImport::extract($rows);

        $this->assertCount(1, $out);
        $this->assertNull($out[0]['ekyc']);
        $this->assertNull($out[0]['status']);
    }

    /**
     * @dataProvider ekycValues
     */
    public function test_normalises_ekyc_values(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, KeanggotaanImport::normaliseEkyc($raw));
    }

    /**
     * @dataProvider statusValues
     */
    public function test_normalises_status_keanggotaan(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, KeanggotaanImport::normaliseStatusAnggota($raw));
    }

    public static function statusValues(): array
    {
        return [
            ['Approved', 'aktif'],
            ['  AKTIF ', 'aktif'],
            ['Rejected', 'tidak_aktif'],
            ['Tidak Aktif', 'tidak_aktif'],
            ['', null],
            [null, null],
            // Unknown wording must not silently deactivate a member.
            ['Dalam Proses', null],
        ];
    }

    public static function ekycValues(): array
    {
        return [
            ['Completed', 'completed'],
            ['  COMPLETE ', 'completed'],
            ['Selesai', 'completed'],
            ['Ya', 'completed'],
            ['Pending', 'pending'],
            ['BELUM', 'pending'],
            ['Tidak', 'pending'],
            ['', null],
            [null, null],
            ['entah apa', null],
        ];
    }
}
