<?php

namespace Tests\Feature;

use App\Jobs\ProcessVoterUpload;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the seat-stamping invariant for uploads with no geography columns:
 * an assigned seat fills ONLY the blank/empty geo cells and NEVER overwrites
 * real geography already present in the file. This runs on live voter data,
 * so mislabeling a row would be worse than the bug it fixes.
 */
class UploadAssignedSeatTest extends TestCase
{
    use RefreshDatabase;

    private int $telSeq = 0;

    private function userId(): int
    {
        // UserFactory omits the NOT NULL telephone column (known baseline), so set
        // it — unique per call to avoid the telephone unique-index collision.
        return \App\Models\User::factory()->create(['telephone' => '019000'.str_pad((string) $this->telSeq++, 4, '0', STR_PAD_LEFT)])->id;
    }

    private function stamp(UploadBatch $batch): void
    {
        $job = new ProcessVoterUpload($batch->id, 'voter-uploads/x.xlsx');
        $m = new ReflectionMethod($job, 'applyAssignedSeat');
        $m->setAccessible(true);
        $m->invoke($job, $batch);
    }

    public function test_assigned_seat_fills_blank_rows_only(): void
    {
        $batch = UploadBatch::create([
            'nama_fail' => 'Juasseh TERAS.xlsx', 'fail_path' => 'voter-uploads/x.xlsx',
            'jumlah_rekod' => 0, 'status' => 'processing', 'is_active' => false, 'uploaded_by' => $this->userId(),
            'assign_negeri' => 'NEGERI SEMBILAN', 'assign_parlimen' => 'P.129', 'assign_kadun' => 'JUASSEH',
        ]);

        // Blank-geo rows (null and empty-string) — should be stamped.
        $blankNull = PangkalanDataPengundi::create(['upload_batch_id' => $batch->id, 'no_ic' => '850101015523', 'nama' => 'A']);
        $blankEmpty = PangkalanDataPengundi::create(['upload_batch_id' => $batch->id, 'no_ic' => '850101015524', 'nama' => 'B', 'negeri' => '', 'parlimen' => '', 'kadun' => '']);

        // A row that already carries real geography — must NOT be overwritten.
        $real = PangkalanDataPengundi::create(['upload_batch_id' => $batch->id, 'no_ic' => '850101015525', 'nama' => 'C', 'negeri' => 'JOHOR', 'parlimen' => 'P.999', 'kadun' => 'ELSEWHERE']);

        // A row in a different batch — must be untouched.
        $otherBatch = UploadBatch::create([
            'nama_fail' => 'other.xlsx', 'fail_path' => 'voter-uploads/z.xlsx',
            'jumlah_rekod' => 0, 'status' => 'processing', 'is_active' => false, 'uploaded_by' => $this->userId(),
        ]);
        $other = PangkalanDataPengundi::create(['upload_batch_id' => $otherBatch->id, 'no_ic' => '850101015526', 'nama' => 'D']);

        $this->stamp($batch);

        foreach ([$blankNull, $blankEmpty] as $r) {
            $r->refresh();
            $this->assertSame('NEGERI SEMBILAN', $r->negeri);
            $this->assertSame('P.129', $r->parlimen);
            $this->assertSame('JUASSEH', $r->kadun);
        }

        $real->refresh();
        $this->assertSame('JOHOR', $real->negeri);      // preserved
        $this->assertSame('ELSEWHERE', $real->kadun);   // preserved

        $other->refresh();
        $this->assertNull($other->negeri);              // different batch untouched
    }

    public function test_no_assignment_is_a_noop(): void
    {
        $batch = UploadBatch::create([
            'nama_fail' => 'roll.xlsx', 'fail_path' => 'voter-uploads/y.xlsx',
            'jumlah_rekod' => 0, 'status' => 'processing', 'is_active' => false, 'uploaded_by' => $this->userId(),
        ]);
        $row = PangkalanDataPengundi::create(['upload_batch_id' => $batch->id, 'no_ic' => '850101015523', 'nama' => 'A']);

        $this->stamp($batch);

        $row->refresh();
        $this->assertNull($row->negeri); // nothing assigned → nothing stamped
    }
}
