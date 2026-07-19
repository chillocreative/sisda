<?php

namespace Tests\Feature;

use App\Imports\VoterDatabaseImport;
use App\Models\PangkalanDataPengundi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Reproduces the "Voter Export Report" workbook shape: a metadata cover sheet
 * first, then the real data (header + rows) on the next sheet. The importer must
 * skip the cover, find the header on the data sheet, and capture EVERY column
 * (Daerah Mengundi, Race, geography) — not just IC + name.
 */
class VoterImportMultiSheetTest extends TestCase
{
    use RefreshDatabase;

    private function batchId(): int
    {
        $uid = \App\Models\User::factory()->create(['telephone' => '019'.random_int(1000000, 9999999)])->id;

        return \App\Models\UploadBatch::create([
            'nama_fail' => 'export.xlsx', 'fail_path' => 'x', 'jumlah_rekod' => 0,
            'status' => 'processing', 'is_active' => false, 'uploaded_by' => $uid,
        ])->id;
    }

    public function test_header_on_second_sheet_is_found_and_all_columns_captured(): void
    {
        $import = new VoterDatabaseImport($this->batchId());

        // Sheet 0 — metadata cover (no header). Maatwebsite hands each sheet's
        // chunk to collection(); this one must contribute zero rows.
        $import->collection(new Collection([
            ['Party', ''],
            ['Voter Export Report', ''],
            ['', ''],
            ['Generated', '7/19/2026, 11:42:48 AM'],
            ['Scope — State', 'NEGERI SEMBILAN'],
        ]));

        $this->assertSame(0, $import->rowsDetected(), 'cover sheet must yield no rows');

        // Sheet 1 — real data: header row then voters (matches the live file).
        $import->collection(new Collection([
            ['IC Number', 'Full Name', 'Gender', 'Phone', 'Address', 'Postcode', 'City', 'Daerah Mengundi', 'Pusat Mengundi', 'Confirmed Alignment', 'Race', 'Locality', 'Parliament', 'DUN', 'State'],
            ['080304055012', 'SAFIAH BT JAALAM', 'Female', '', '-', '', 'KUALA PILAH', 'PELANGAI', 'SK PELANGAI', '', 'MELAYU', 'KG KUALA JEMAPOH', 'KUALA PILAH', 'JUASSEH', 'NEGERI SEMBILAN'],
            ['121107745442', 'HO FONG YEH', 'Female', '', '-', '', '', 'KAMPONG GENTAM', 'SK TUNKU MUNAWIR', '', 'CINA', 'DESA PARIT TINGGI', 'KUALA PILAH', 'JUASSEH', 'NEGERI SEMBILAN'],
        ]));

        $this->assertSame(2, $import->rowsDetected());

        $row = PangkalanDataPengundi::where('no_ic', '080304055012')->first();
        $this->assertNotNull($row);
        $this->assertSame('SAFIAH BT JAALAM', $row->nama);
        $this->assertSame('PELANGAI', $row->daerah_mengundi);   // the DM the user wants
        $this->assertSame('MELAYU', $row->bangsa);              // real race column
        $this->assertSame('KG KUALA JEMAPOH', $row->lokaliti);
        $this->assertSame('KUALA PILAH', $row->parlimen);
        $this->assertSame('JUASSEH', $row->kadun);
        $this->assertSame('NEGERI SEMBILAN', $row->negeri);
        $this->assertSame('PEREMPUAN', $row->jantina);          // Female → PEREMPUAN
    }

    public function test_two_data_sheets_with_different_layouts_each_use_own_mapping(): void
    {
        // Batch-18 shape: a full DPT sheet, then a subset sheet with a DIFFERENT
        // column order. Reading sheet 2 through sheet 1's map scrambles columns
        // (e.g. a polling-centre name landing in `parlimen`); each sheet must map
        // itself.
        $import = new VoterDatabaseImport($this->batchId());

        // Sheet A — NamaDUN present at col 4.
        $import->collection(new Collection([
            ['NoKp', 'Nama', 'NamaParlimen', 'NamaDM', 'NamaDUN', 'Negeri'],
            ['641218016241', 'MOHD SAFRI BIN ALI', 'SEGAMAT', 'MENSUDOT LAMA', 'BULOH KASAP', 'JOHOR'],
        ]));

        // Sheet B — different layout, no NamaDUN; col 5 is a polling-centre name.
        $import->collection(new Collection([
            ['NoKp', 'Nama', 'NamaLokaliti', 'NamaParlimen', 'NamaDM', 'NamaPusatMengundi'],
            ['680121016415', 'ZULKEPLY BIN SAMAD', 'KG MENSUDUT', 'SEGAMAT', 'MENSUDOT LAMA', 'SEKOLAH KEBANGSAAN X'],
        ]));

        $a = PangkalanDataPengundi::where('no_ic', '641218016241')->first();
        $this->assertSame('SEGAMAT', $a->parlimen);
        $this->assertSame('MENSUDOT LAMA', $a->daerah_mengundi);
        $this->assertSame('BULOH KASAP', $a->kadun);

        $b = PangkalanDataPengundi::where('no_ic', '680121016415')->first();
        $this->assertSame('SEGAMAT', $b->parlimen);            // its OWN NamaParlimen, not the school
        $this->assertNotSame('SEKOLAH KEBANGSAAN X', $b->parlimen);
        $this->assertSame('MENSUDOT LAMA', $b->daerah_mengundi);
        $this->assertSame('KG MENSUDUT', $b->lokaliti);
        $this->assertNull($b->kadun);                          // sheet B has no NamaDUN column
    }

    public function test_single_sheet_with_header_on_row_zero_still_works(): void
    {
        $import = new VoterDatabaseImport($this->batchId());
        $import->collection(new Collection([
            ['No IC', 'Nama', 'DUN', 'Daerah Mengundi'],
            ['850101015523', 'AHMAD BIN ALI', 'JUASSEH', 'PELANGAI'],
        ]));

        $this->assertSame(1, $import->rowsDetected());
        $row = PangkalanDataPengundi::where('no_ic', '850101015523')->first();
        $this->assertSame('JUASSEH', $row->kadun);
        $this->assertSame('PELANGAI', $row->daerah_mengundi);
    }
}
