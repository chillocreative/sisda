<?php

namespace Tests\Unit;

use App\Models\Bandar;
use App\Models\DaerahMengundi;
use App\Models\Negeri;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use App\Models\User;
use App\Support\Borang14Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14ReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_bandar_returns_null_when_no_dm_exists(): void
    {
        $this->assertNull(Borang14Reference::forBandar(999999));
    }

    public function test_for_bandar_groups_daerah_mengundi_under_parlimen(): void
    {
        // Jadual geografi kosong di bawah RefreshDatabase — sedia data ujian.
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        DaerahMengundi::create([
            'kod_dm' => '041/01',
            'nama' => 'DM Ujian',
            'bandar_id' => $bandar->id,
        ]);

        $user = User::factory()->create(['telephone' => '0123456789']);
        $batch = UploadBatch::create([
            'nama_fail' => 'ujian.csv',
            'fail_path' => 'ujian.csv',
            'jumlah_rekod' => 2,
            'status' => 'completed',
            'is_active' => true,
            'uploaded_by' => $user->id,
        ]);

        PangkalanDataPengundi::create([
            'upload_batch_id' => $batch->id,
            'no_ic' => '900101011234',
            'nama' => 'Pengundi Satu',
            'lokaliti' => 'Kampung Ujian',
            'daerah_mengundi' => 'DM Ujian',
            'parlimen' => 'Parlimen Ujian',
            'negeri' => 'Negeri Ujian',
        ]);
        PangkalanDataPengundi::create([
            'upload_batch_id' => $batch->id,
            'no_ic' => '900101011235',
            'nama' => 'Pengundi Dua',
            'lokaliti' => 'Kampung Ujian',
            'daerah_mengundi' => 'DM Ujian',
            'parlimen' => 'Parlimen Ujian',
            'negeri' => 'Negeri Ujian',
        ]);

        $found = Bandar::whereHas('daerahMengundi')->first();
        $this->assertNotNull($found, 'Bandar dengan daerah_mengundi sepatutnya wujud dalam data ujian.');

        $ref = Borang14Reference::forBandar($found->id);

        $this->assertIsArray($ref);
        $this->assertSame($found->nama, $ref['parlimen']);
        $this->assertNull($ref['dun']);
        $this->assertSame('dpt_estimate', $ref['source']);
        $this->assertNotEmpty($ref['daerah_mengundi']);
        $this->assertArrayHasKey('pusat_mengundi', $ref['daerah_mengundi'][0]);
        $this->assertSame('DM Ujian', $ref['daerah_mengundi'][0]['nama']);
        $this->assertSame('Kampung Ujian', $ref['daerah_mengundi'][0]['pusat_mengundi'][0]['nama']);
    }

    /**
     * Papan markah awam ditinjau setiap 4 saat oleh SETIAP penonton, dan setiap
     * pembinaan rujukan mengimbas seluruh pangkalan_data_pengundi. Rujukan
     * mesti dicache supaya tinjauan kedua tidak menyentuh pangkalan data
     * langsung. (Angka undi TIDAK dicache — lihat ScoreboardPayload.)
     */
    public function test_reference_is_cached_so_a_second_lookup_does_not_hit_the_database(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Cache']);
        $bandar = Bandar::create(['nama' => 'Parlimen Cache', 'negeri_id' => $negeri->id]);
        $kadun = \App\Models\Kadun::create(['nama' => 'DUN CACHE', 'bandar_id' => $bandar->id]);

        \Illuminate\Support\Facades\DB::table('pangkalan_data_pengundi')->insert([
            'no_ic' => '900101019999',
            'nama' => 'Pengundi Cache',
            'lokaliti' => 'Kampung Cache',
            'daerah_mengundi' => 'DM Cache',
            'kadun' => 'DUN CACHE',
            'parlimen' => 'Parlimen Cache',
            'negeri' => 'Negeri Cache',
            'is_deceased' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pertama = Borang14Reference::forKadun($kadun->id);
        $this->assertNotNull($pertama);

        // Kira pertanyaan yang laluan tinjauan KEDUA jalankan.
        $pertanyaan = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$pertanyaan) {
            $pertanyaan++;
        });

        $kedua = Borang14Reference::forKadun($kadun->id);

        $this->assertSame($pertama, $kedua, 'Rujukan dicache mesti sama bentuknya.');
        $this->assertSame(0, $pertanyaan, 'Tinjauan kedua mesti dilayan sepenuhnya daripada cache.');
    }

    /**
     * Kes terburuk ialah kerusi TANPA roll: Cache::remember() menganggap null
     * sebagai "tiada dalam cache" dan akan mengimbas jadual penuh setiap
     * tinjauan. Null mesti ikut dicache.
     */
    public function test_a_seat_with_no_roll_caches_its_null_result_too(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Kosong']);
        $bandar = Bandar::create(['nama' => 'Parlimen Kosong', 'negeri_id' => $negeri->id]);

        $this->assertNull(Borang14Reference::forBandar($bandar->id));

        $pertanyaan = 0;
        \Illuminate\Support\Facades\DB::listen(function () use (&$pertanyaan) {
            $pertanyaan++;
        });

        $this->assertNull(Borang14Reference::forBandar($bandar->id));
        $this->assertSame(0, $pertanyaan, 'Keputusan null mesti dicache — jika tidak, setiap tinjauan mengimbas jadual penuh.');
    }
}
