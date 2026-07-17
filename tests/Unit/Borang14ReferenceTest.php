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
}
