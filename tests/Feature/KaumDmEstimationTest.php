<?php

namespace Tests\Feature;

use App\Http\Controllers\PilihanrayaAnalisaController;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Locks the voter-name → race estimation that drives Kaum Mengikut DM:
 * BIN/BINTI = Melayu; A/L·A/P·S/O·D/O = India; ANAK = Lain-lain; the rest = Cina.
 * These numbers are shown as fact, so the buckets must be exactly right —
 * including the "BIN as a word, not a substring" edge (BINTANG ≠ Melayu).
 */
class KaumDmEstimationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoll(int $batchId, array $names, string $dm = 'DM SATU'): void
    {
        $i = 0;
        foreach ($names as $name) {
            PangkalanDataPengundi::create([
                'upload_batch_id' => $batchId,
                'no_ic' => str_pad((string) (850101010000 + $i++), 12, '0', STR_PAD_LEFT),
                'nama' => $name,
                'kadun' => 'TESTDUN',
                'daerah_mengundi' => $dm,
            ]);
        }
    }

    public function test_name_patterns_classify_into_the_right_buckets(): void
    {
        $uid = \App\Models\User::factory()->create(['telephone' => '0190001'])->id;
        $batch = UploadBatch::create([
            'nama_fail' => 'roll.xlsx', 'fail_path' => 'x', 'jumlah_rekod' => 0,
            'status' => 'completed', 'is_active' => true, 'uploaded_by' => $uid,
        ]);

        $this->seedRoll($batch->id, [
            'AHMAD BIN ALI',        // Melayu
            'SITI BINTI OSMAN',     // Melayu
            'RAJU A/L MUNIANDY',    // India
            'PRIYA A/P RAMAN',      // India
            'JELI ANAK USOP',       // Lain-lain
            'TAN AH KOW',           // Cina (residual)
            'BINTANG LEE',          // Cina — "BIN" is a substring, not a word
        ]);

        $method = new ReflectionMethod(PilihanrayaAnalisaController::class, 'kaumDmForDun');
        $method->setAccessible(true);
        [$rows, $totals] = $method->invoke(app(PilihanrayaAnalisaController::class), 'TESTDUN');

        $this->assertSame(2, $totals['melayu']);
        $this->assertSame(2, $totals['india']);
        $this->assertSame(1, $totals['lain']);
        $this->assertSame(2, $totals['cina']);   // TAN AH KOW + BINTANG LEE
        $this->assertSame(7, $totals['jumlah']);

        // Single DM row carries the same split.
        $this->assertCount(1, $rows);
        $this->assertSame('DM SATU', $rows[0]['dm']);
        $this->assertSame(2, $rows[0]['melayu']);
        $this->assertSame(2, $rows[0]['cina']);
    }

    public function test_only_active_batches_and_the_selected_dun_count(): void
    {
        $uid = \App\Models\User::factory()->create(['telephone' => '0190002'])->id;
        $active = UploadBatch::create(['nama_fail' => 'a', 'fail_path' => 'a', 'jumlah_rekod' => 0, 'status' => 'completed', 'is_active' => true, 'uploaded_by' => $uid]);
        $inactive = UploadBatch::create(['nama_fail' => 'b', 'fail_path' => 'b', 'jumlah_rekod' => 0, 'status' => 'completed', 'is_active' => false, 'uploaded_by' => $uid]);

        $this->seedRoll($active->id, ['AHMAD BIN ALI', 'SITI BINTI ALI']);          // counted
        $this->seedRoll($inactive->id, ['OMAR BIN KASSIM']);                        // inactive batch → excluded
        // Different DUN in the active batch → excluded from TESTDUN.
        PangkalanDataPengundi::create(['upload_batch_id' => $active->id, 'no_ic' => '850101019999', 'nama' => 'ALI BIN OMAR', 'kadun' => 'OTHERDUN', 'daerah_mengundi' => 'X']);

        $method = new ReflectionMethod(PilihanrayaAnalisaController::class, 'kaumDmForDun');
        $method->setAccessible(true);
        [, $totals] = $method->invoke(app(PilihanrayaAnalisaController::class), 'TESTDUN');

        $this->assertSame(2, $totals['jumlah']);   // only the 2 active TESTDUN rows
        $this->assertSame(2, $totals['melayu']);
    }
}
