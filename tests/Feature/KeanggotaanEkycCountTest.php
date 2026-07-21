<?php

namespace Tests\Feature;

use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Aktif / EKYC" figure is published to users, so its rule is pinned here:
 * the file's own per-member STATUS EKYC wins, and only a silent file falls back
 * to the older batch-level rule.
 */
class KeanggotaanEkycCountTest extends TestCase
{
    use RefreshDatabase;

    private function batch(bool $isEkyc): KeanggotaanBatch
    {
        // Built by hand, not by factory: UserFactory doesn't set the NOT NULL
        // telephone column (the project's known 20-test baseline failure).
        $uploader = User::firstOrCreate(
            ['email' => 'penguji@example.test'],
            ['name' => 'Penguji', 'telephone' => '0123456789', 'password' => bcrypt('rahsia')],
        );

        return KeanggotaanBatch::create([
            'uploaded_by' => $uploader->id,
            'nama_fail' => 'ujian.xlsx',
            'fail_path' => 'keanggotaan-uploads/ujian.xlsx',
            'status' => 'completed',
            'is_ekyc' => $isEkyc,
        ]);
    }

    private function member(KeanggotaanBatch $batch, ?string $statusEkyc, ?string $statusAnggota = null): Keanggotaan
    {
        static $n = 0;
        $n++;

        return Keanggotaan::create([
            'batch_id' => $batch->id,
            'no_ic' => str_pad((string) $n, 12, '8', STR_PAD_LEFT),
            'nama' => 'AHLI '.$n,
            'status_ekyc' => $statusEkyc,
            'status_anggota' => $statusAnggota,
        ]);
    }

    public function test_pending_member_is_not_counted_even_in_an_ekyc_flagged_batch(): void
    {
        $batch = $this->batch(true);
        $this->member($batch, 'pending');
        $this->member($batch, 'pending', 'aktif');

        $this->assertSame(0, Keanggotaan::query()->ekycVerified([$batch->id])->count());
    }

    public function test_completed_member_is_counted_even_when_batch_is_not_flagged(): void
    {
        $batch = $this->batch(false);
        $this->member($batch, 'completed');
        $this->member($batch, 'pending');

        $this->assertSame(1, Keanggotaan::query()->ekycVerified([])->count());
    }

    public function test_null_member_falls_back_to_the_old_batch_rule(): void
    {
        $flagged = $this->batch(true);
        $plain = $this->batch(false);
        $this->member($flagged, null);              // counted via batch flag
        $this->member($plain, null, 'aktif');       // counted via status Aktif
        $this->member($plain, null);                // neither — not counted

        $this->assertSame(2, Keanggotaan::query()->ekycVerified([$flagged->id])->count());
    }

    public function test_raw_sql_expression_matches_the_eloquent_scope(): void
    {
        $flagged = $this->batch(true);
        $plain = $this->batch(false);
        $this->member($flagged, 'pending');
        $this->member($flagged, 'completed');
        $this->member($flagged, null);
        $this->member($plain, null, 'aktif');
        $this->member($plain, null);

        [$expr, $bind] = Keanggotaan::ekycSql([$flagged->id]);
        $viaSql = (int) Keanggotaan::query()
            ->selectRaw("SUM(CASE WHEN {$expr} THEN 1 ELSE 0 END) AS ekyc", $bind)
            ->value('ekyc');

        $this->assertSame(3, $viaSql);
        $this->assertSame(Keanggotaan::query()->ekycVerified([$flagged->id])->count(), $viaSql);
    }

    public function test_php_row_helper_matches_the_query_rule(): void
    {
        $set = array_flip([7]);

        $this->assertTrue(Keanggotaan::rowIsEkycVerified('completed', null, 99, $set));
        $this->assertFalse(Keanggotaan::rowIsEkycVerified('pending', 'aktif', 7, $set));
        $this->assertTrue(Keanggotaan::rowIsEkycVerified(null, 'aktif', 99, $set));
        $this->assertTrue(Keanggotaan::rowIsEkycVerified(null, null, 7, $set));
        $this->assertFalse(Keanggotaan::rowIsEkycVerified(null, null, 99, $set));
    }
}
