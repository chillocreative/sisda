<?php

namespace Tests\Unit;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Support\Borang14Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Struktur Borang 14 SEBENAR daripada muat naik DPPR/DPI.
 *
 * Apabila roll membawa `pusat_mengundi` DAN `saluran`, Borang14Reference mesti
 * membina pokok sebenar (DM > Pusat Mengundi > Saluran) dan bukan anggaran
 * lama (satu Lokaliti = satu Pusat Mengundi dengan satu Saluran).
 *
 * Baris di bawah DIREKA mengikut BENTUK fail DPI N34 Gemas — tiada data
 * pengundi sebenar disalin ke dalam repo.
 */
class Borang14StrukturDariDpiTest extends TestCase
{
    use RefreshDatabase;

    private int $ic = 500000000000;

    private function kadun(string $negeri, string $parlimen, string $dun): Kadun
    {
        $n = Negeri::create(['nama' => $negeri]);
        $b = Bandar::create(['nama' => $parlimen, 'negeri_id' => $n->id]);

        return Kadun::create(['nama' => $dun, 'bandar_id' => $b->id]);
    }

    /**
     * Masukkan $bilangan baris pengundi ke dalam satu saluran.
     *
     * @param  array<string,mixed>  $lebihan
     */
    private function pengundi(int $bilangan, array $lebihan): void
    {
        $baris = [];
        for ($i = 0; $i < $bilangan; $i++) {
            $baris[] = array_merge([
                'no_ic' => (string) ($this->ic++),
                'nama' => 'PENGUNDI UJIAN',
                'is_deceased' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ], $lebihan);
        }
        DB::table('pangkalan_data_pengundi')->insert($baris);
    }

    /** @return array<string,int> nama pusat => bilangan saluran */
    private function saluranSetiapPusat(array $ref): array
    {
        $keluaran = [];
        foreach ($ref['daerah_mengundi'] as $dm) {
            foreach ($dm['pusat_mengundi'] as $pusat) {
                $keluaran[$pusat['nama']] = count($pusat['saluran']);
            }
        }

        return $keluaran;
    }

    public function test_roll_dengan_pusat_dan_saluran_membina_struktur_sebenar(): void
    {
        $kadun = $this->kadun('NEGERI SEMBILAN', 'TAMPIN', 'GEMAS');

        $asas = ['kadun' => 'GEMAS', 'parlimen' => 'TAMPIN', 'negeri' => 'NEGERI SEMBILAN'];

        // DM 1 — dua Pusat Mengundi, 4 dan 5 saluran (seperti senarai gazet).
        foreach ([1 => 450, 2 => 450, 3 => 704, 4 => 704] as $no => $bil) {
            $this->pengundi($bil, $asas + [
                'daerah_mengundi' => 'FELDA JELAI 1 & 3',
                'lokaliti' => 'FELDA JELAI 1',
                'pusat_mengundi' => 'SEKOLAH KEBANGSAAN JELAI 1',
                'saluran' => (string) $no,
            ]);
        }
        foreach ([1, 2, 3, 4, 5] as $no) {
            $this->pengundi(10, $asas + [
                'daerah_mengundi' => 'FELDA JELAI 1 & 3',
                'lokaliti' => 'FELDA JELAI 3',
                'pusat_mengundi' => 'SEKOLAH KEBANGSAAN JELAI 3',
                'saluran' => (string) $no,
            ]);
        }
        // DM 2 — satu Pusat Mengundi, 4 saluran.
        foreach ([1, 2, 3, 4] as $no) {
            $this->pengundi(7, $asas + [
                'daerah_mengundi' => 'FELDA PASIR BESAR',
                'lokaliti' => 'FELDA PASIR BESAR',
                'pusat_mengundi' => 'SEKOLAH KEBANGSAAN FELDA PASIR BESAR',
                'saluran' => (string) $no,
            ]);
        }

        $ref = Borang14Reference::forKadun($kadun->id);

        $this->assertNotNull($ref);
        $this->assertSame('dpt_sebenar', $ref['source']);
        $this->assertCount(2, $ref['daerah_mengundi'], 'Dua Daerah Mengundi dijangka.');

        $this->assertSame([
            'SEKOLAH KEBANGSAAN JELAI 1' => 4,
            'SEKOLAH KEBANGSAAN JELAI 3' => 5,
            'SEKOLAH KEBANGSAAN FELDA PASIR BESAR' => 4,
        ], $this->saluranSetiapPusat($ref));

        // Berdaftar per saluran ialah kiraan SEBENAR, bukan agihan rata.
        $jelai1 = $ref['daerah_mengundi'][0]['pusat_mengundi'][0];
        $this->assertSame('SEKOLAH KEBANGSAAN JELAI 1', $jelai1['nama']);
        $this->assertSame([450, 450, 704, 704], array_column($jelai1['saluran'], 'berdaftar'));
        $this->assertSame([1, 2, 3, 4], array_column($jelai1['saluran'], 'no'));

        $this->assertSame(450 + 450 + 704 + 704 + (5 * 10) + (4 * 7), Borang14Reference::jumlahBerdaftar($ref));
    }

    public function test_pengundi_meninggal_dikecualikan_daripada_struktur_sebenar(): void
    {
        $kadun = $this->kadun('NEGERI MATI', 'PARLIMEN MATI', 'DUN MATI');
        $asas = [
            'kadun' => 'DUN MATI', 'parlimen' => 'PARLIMEN MATI', 'negeri' => 'NEGERI MATI',
            'daerah_mengundi' => 'DM SATU', 'lokaliti' => 'KG SATU',
            'pusat_mengundi' => 'SK SATU', 'saluran' => '1',
        ];

        $this->pengundi(3, $asas);
        $this->pengundi(2, array_merge($asas, ['is_deceased' => true]));

        $ref = Borang14Reference::forKadun($kadun->id);

        $this->assertSame('dpt_sebenar', $ref['source']);
        $this->assertSame(3, $ref['daerah_mengundi'][0]['pusat_mengundi'][0]['saluran'][0]['berdaftar']);
    }

    /**
     * Susunan saluran mesti BERANGKA. Isihan rentetan naif meletakkan '10'
     * selepas '1' — grid kemasukan pun tersusun salah.
     */
    public function test_saluran_sepuluh_datang_selepas_sembilan(): void
    {
        $kadun = $this->kadun('NEGERI ISIH', 'PARLIMEN ISIH', 'DUN ISIH');
        $asas = [
            'kadun' => 'DUN ISIH', 'parlimen' => 'PARLIMEN ISIH', 'negeri' => 'NEGERI ISIH',
            'daerah_mengundi' => 'DM ISIH', 'lokaliti' => 'KG ISIH', 'pusat_mengundi' => 'SK ISIH',
        ];

        // Sengaja dimasukkan tidak mengikut urutan.
        foreach (['10', '2', '9', '1', '11'] as $no) {
            $this->pengundi(1, $asas + ['saluran' => $no]);
        }

        $ref = Borang14Reference::forKadun($kadun->id);

        $this->assertSame('dpt_sebenar', $ref['source']);
        $this->assertSame(
            [1, 2, 9, 10, 11],
            array_column($ref['daerah_mengundi'][0]['pusat_mengundi'][0]['saluran'], 'no')
        );
    }

    public function test_roll_tanpa_pusat_dan_saluran_kekal_anggaran(): void
    {
        $kadun = $this->kadun('NEGERI ANGGAR', 'PARLIMEN ANGGAR', 'DUN ANGGAR');
        $asas = [
            'kadun' => 'DUN ANGGAR', 'parlimen' => 'PARLIMEN ANGGAR', 'negeri' => 'NEGERI ANGGAR',
            'daerah_mengundi' => 'DM ANGGAR',
        ];

        $this->pengundi(3, $asas + ['lokaliti' => 'KG SATU']);
        $this->pengundi(2, $asas + ['lokaliti' => 'KG DUA']);

        $ref = Borang14Reference::forKadun($kadun->id);

        $this->assertSame('dpt_estimate', $ref['source']);
        $this->assertCount(1, $ref['daerah_mengundi']);
        $this->assertSame(['KG SATU' => 1, 'KG DUA' => 1], $this->saluranSetiapPusat($ref));
        $this->assertSame(5, Borang14Reference::jumlahBerdaftar($ref));
    }

    /**
     * CAMPURAN: sebahagian baris membawa pusat/saluran, sebahagian tidak.
     *
     * Keputusan — SELURUH kerusi jatuh kepada ANGGARAN. Baris tanpa
     * pusat/saluran ialah "tidak diketahui", bukan "tiada saluran"; membina
     * struktur sebenar daripada sebahagian baris akan MENYEMBUNYIKAN pengundi
     * selebihnya dan mengurangkan jumlah berdaftar secara senyap.
     */
    public function test_roll_campuran_jatuh_kepada_anggaran_dan_tiada_pengundi_hilang(): void
    {
        $kadun = $this->kadun('NEGERI CAMPUR', 'PARLIMEN CAMPUR', 'DUN CAMPUR');
        $asas = [
            'kadun' => 'DUN CAMPUR', 'parlimen' => 'PARLIMEN CAMPUR', 'negeri' => 'NEGERI CAMPUR',
            'daerah_mengundi' => 'DM CAMPUR',
        ];

        $this->pengundi(4, $asas + ['lokaliti' => 'KG BERSTRUKTUR', 'pusat_mengundi' => 'SK CAMPUR', 'saluran' => '1']);
        $this->pengundi(6, $asas + ['lokaliti' => 'KG TIADA STRUKTUR']);

        $ref = Borang14Reference::forKadun($kadun->id);

        $this->assertSame('dpt_estimate', $ref['source'], 'Data campuran mesti memilih SATU mod — anggaran.');
        $this->assertSame(['KG BERSTRUKTUR' => 1, 'KG TIADA STRUKTUR' => 1], $this->saluranSetiapPusat($ref));
        $this->assertSame(10, Borang14Reference::jumlahBerdaftar($ref), 'Tiada pengundi boleh hilang dalam mod anggaran.');
    }

    /** Saluran kosong (rentetan kosong) sama seperti tiada — bukan saluran "". */
    public function test_saluran_kosong_dikira_tidak_diketahui(): void
    {
        $kadun = $this->kadun('NEGERI KOSONG', 'PARLIMEN KOSONG', 'DUN KOSONG');
        $asas = [
            'kadun' => 'DUN KOSONG', 'parlimen' => 'PARLIMEN KOSONG', 'negeri' => 'NEGERI KOSONG',
            'daerah_mengundi' => 'DM KOSONG', 'lokaliti' => 'KG KOSONG',
        ];

        $this->pengundi(2, $asas + ['pusat_mengundi' => 'SK KOSONG', 'saluran' => '']);

        $ref = Borang14Reference::forKadun($kadun->id);
        $this->assertSame('dpt_estimate', $ref['source']);
    }

    public function test_parlimen_juga_membina_struktur_sebenar(): void
    {
        $n = Negeri::create(['nama' => 'NEGERI PAR']);
        $bandar = Bandar::create(['nama' => 'PARLIMEN PAR', 'negeri_id' => $n->id]);

        $asas = ['parlimen' => 'PARLIMEN PAR', 'negeri' => 'NEGERI PAR', 'daerah_mengundi' => 'DM PAR', 'lokaliti' => 'KG PAR'];

        $this->pengundi(5, $asas + ['pusat_mengundi' => 'SK PAR', 'saluran' => '1']);
        $this->pengundi(8, $asas + ['pusat_mengundi' => 'SK PAR', 'saluran' => '2']);

        $ref = Borang14Reference::forBandar($bandar->id);

        $this->assertSame('dpt_sebenar', $ref['source']);
        $this->assertNull($ref['dun']);
        $this->assertSame('PARLIMEN PAR', $ref['parlimen']);
        $this->assertSame([5, 8], array_column($ref['daerah_mengundi'][0]['pusat_mengundi'][0]['saluran'], 'berdaftar'));
    }
}
