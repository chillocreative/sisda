<?php
// tests/Unit/Borang14FormTest.php
namespace Tests\Unit;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Borang14FormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jadual geografi (bandar/kadun) kosong di bawah RefreshDatabase — sedia data ujian.
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);
    }

    public function test_kawasan_resolves_to_kadun_for_dun_type(): void
    {
        $kadun = \App\Models\Kadun::first();
        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 3,
        ]);

        $this->assertInstanceOf(\App\Models\Kadun::class, $form->kawasan());
        $this->assertSame($kadun->nama, $form->kawasanNama());
    }

    public function test_kawasan_resolves_to_bandar_for_parlimen_type(): void
    {
        $bandar = \App\Models\Bandar::first();
        $form = Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'pru', 'tahun' => 2022, 'penjuru' => 2,
        ]);

        $this->assertInstanceOf(\App\Models\Bandar::class, $form->kawasan());
    }

    public function test_published_scope_excludes_drafts(): void
    {
        $kadun = \App\Models\Kadun::first();
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 3, 'status' => 'draft',
        ]);
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2026, 'penjuru' => 3, 'status' => 'published',
        ]);

        $this->assertSame(1, Borang14Form::published()->count());
    }
}
