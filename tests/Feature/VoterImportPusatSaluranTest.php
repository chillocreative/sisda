<?php

namespace Tests\Feature;

use App\Imports\VoterDatabaseImport;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fail DPPR/DPI SUDAH membawa struktur Borang 14 sebenar dalam lajur
 * `Pusat Mengundi` dan `Saluran`. Sebelum ini importer membuang kedua-duanya,
 * jadi Borang14Reference terpaksa MENGANGGAR (satu Lokaliti = satu Pusat
 * Mengundi dengan satu Saluran).
 *
 * Ujian ini memaku tiga perkara:
 *   1. Kedua-dua lajur baharu wujud, nullable, dan baris lama kekal NULL
 *      (NULL = "tidak diketahui", BUKAN "tiada saluran").
 *   2. Importer menyimpan Pusat Mengundi + Saluran.
 *   3. Awalan kod berangka pada sel Lokaliti (`1333401001 FELDA JELAI 1`)
 *      dibuang, dan kodnya hanya mengisi kod_lokaliti apabila tiada lajur kod
 *      berasingan — lajur kod sebenar TIDAK PERNAH ditimpa.
 */
class VoterImportPusatSaluranTest extends TestCase
{
    use RefreshDatabase;

    private function batchId(): int
    {
        $uid = User::factory()->create(['telephone' => '019'.random_int(1000000, 9999999)])->id;

        return UploadBatch::create([
            'nama_fail' => 'dpi.xlsx', 'fail_path' => 'x', 'jumlah_rekod' => 0,
            'status' => 'processing', 'is_active' => false, 'uploaded_by' => $uid,
        ])->id;
    }

    /** Baris tajuk sebenar fail DPI (susunan lajur dikekalkan). */
    private function tajukDpi(): array
    {
        return ['Nama', 'No KP', 'No Rumah', 'Lokaliti', 'Jantina', 'Parlimen', 'DUN', 'DM',
            'Tahun Lahir', 'Bangsa', 'Umur', 'Telefon', 'Catatan', 'Pusat Mengundi', 'Saluran', 'NoSiri'];
    }

    public function test_lajur_pusat_mengundi_dan_saluran_wujud_dan_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('pangkalan_data_pengundi', 'pusat_mengundi'));
        $this->assertTrue(Schema::hasColumn('pangkalan_data_pengundi', 'saluran'));

        // Baris "lama" — dimasukkan tanpa menyebut lajur baharu langsung.
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '900101011111',
            'nama' => 'PENGUNDI LAMA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lama = DB::table('pangkalan_data_pengundi')->where('no_ic', '900101011111')->first();
        $this->assertNull($lama->pusat_mengundi, 'Baris sedia ada mesti kekal NULL — NULL bermaksud tidak diketahui.');
        $this->assertNull($lama->saluran);

        // NULL eksplisit juga mesti diterima (bukti lajur benar-benar nullable).
        DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '900101012222',
            'nama' => 'PENGUNDI NULL',
            'pusat_mengundi' => null,
            'saluran' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(1, DB::table('pangkalan_data_pengundi')->where('no_ic', '900101012222')->count());
    }

    public function test_import_menyimpan_pusat_mengundi_dan_saluran(): void
    {
        $import = new VoterDatabaseImport($this->batchId());
        $import->collection(new Collection([
            $this->tajukDpi(),
            ['A AZIZ BIN SAJI', '510217015765', '392', '1333401001 FELDA JELAI 1', 'L', 'TAMPIN', 'GEMAS',
                'FELDA JELAI 1 & 3', '', 'MELAYU', '75', '0142350997', '1951', 'SEKOLAH KEBANGSAAN JELAI 1', '1', '176'],
            ['SITI BINTI ALI', '700214065432', '12', '1333401003 FELDA JELAI 3', 'P', 'TAMPIN', 'GEMAS',
                'FELDA JELAI 1 & 3', '', 'MELAYU', '56', '', '1970', 'SEKOLAH KEBANGSAAN JELAI 3', '5', '9'],
        ]));

        $this->assertSame(2, $import->rowsDetected());

        $a = PangkalanDataPengundi::where('no_ic', '510217015765')->first();
        $this->assertSame('SEKOLAH KEBANGSAAN JELAI 1', $a->pusat_mengundi);
        $this->assertSame('1', $a->saluran);
        $this->assertSame('FELDA JELAI 1 & 3', $a->daerah_mengundi);
        $this->assertSame('GEMAS', $a->kadun);

        $b = PangkalanDataPengundi::where('no_ic', '700214065432')->first();
        $this->assertSame('SEKOLAH KEBANGSAAN JELAI 3', $b->pusat_mengundi);
        $this->assertSame('5', $b->saluran);
    }

    public function test_awalan_kod_pada_lokaliti_dibuang_dan_masuk_kod_lokaliti(): void
    {
        $import = new VoterDatabaseImport($this->batchId());
        $import->collection(new Collection([
            $this->tajukDpi(),
            ['A AZIZ BIN SAJI', '510217015765', '392', '1333401001 FELDA JELAI 1', 'L', 'TAMPIN', 'GEMAS',
                'FELDA JELAI 1 & 3', '', 'MELAYU', '75', '', '1951', 'SEKOLAH KEBANGSAAN JELAI 1', '1', '176'],
        ]));

        $row = PangkalanDataPengundi::where('no_ic', '510217015765')->first();
        $this->assertSame('FELDA JELAI 1', $row->lokaliti, 'Awalan kod berangka mesti dibuang daripada lokaliti.');
        $this->assertSame('1333401001', $row->kod_lokaliti, 'Kod itu mesti mengisi kod_lokaliti yang kosong.');
    }

    public function test_lokaliti_tanpa_awalan_kod_kekal_seadanya(): void
    {
        $import = new VoterDatabaseImport($this->batchId());
        $import->collection(new Collection([
            ['No KP', 'Nama', 'Lokaliti'],
            ['850101015523', 'AHMAD BIN ALI', 'KG KUALA JEMAPOH'],
        ]));

        $row = PangkalanDataPengundi::where('no_ic', '850101015523')->first();
        $this->assertSame('KG KUALA JEMAPOH', $row->lokaliti);
        $this->assertNull($row->kod_lokaliti);
    }

    public function test_kod_lokaliti_daripada_lajur_sendiri_tidak_ditimpa(): void
    {
        $import = new VoterDatabaseImport($this->batchId());
        $import->collection(new Collection([
            ['No KP', 'Nama', 'KodLokaliti', 'Lokaliti'],
            ['850101015524', 'AHMAD BIN BAKAR', '999888777', '1333401001 FELDA JELAI 1'],
        ]));

        $row = PangkalanDataPengundi::where('no_ic', '850101015524')->first();
        $this->assertSame('999888777', $row->kod_lokaliti, 'kod_lokaliti daripada lajurnya sendiri TIDAK boleh ditimpa.');
        $this->assertSame('FELDA JELAI 1', $row->lokaliti);
    }
}
