<?php

namespace Tests\Feature;

use App\Models\Keanggotaan;
use App\Models\KeanggotaanBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Analisa KPI cards must all answer the same question. Before this test the
 * "Dalam Kawasan" / "Tiada DPPR/DPT" cards were counted Cabang-wide while
 * "Jumlah Ahli" honoured the DUN drill, so a DUN with 909 members could show
 * 1,807 "Dalam Kawasan" — a figure larger than the roster it sat next to.
 *
 * The Cabang-wide figure is still published, but under its own key
 * (cabang_total) so the "luar cabang / luar DUN" cards keep a denominator that
 * can legitimately exceed the DUN roster.
 */
class KeanggotaanAnalisaScopeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // Built by hand, not by factory: UserFactory doesn't set the NOT NULL
        // telephone column (the project's known 20-test baseline failure).
        return User::firstOrCreate(
            ['email' => 'penyelia@example.test'],
            [
                'name' => 'Penyelia',
                'telephone' => '0123456789',
                'password' => bcrypt('rahsia'),
                'role' => 'super_admin',
                'status' => 'approved',
            ],
        );
    }

    private function batch(): KeanggotaanBatch
    {
        return KeanggotaanBatch::create([
            'uploaded_by' => $this->admin()->id,
            'nama_fail' => 'ujian.xlsx',
            'fail_path' => 'keanggotaan-uploads/ujian.xlsx',
            'status' => 'completed',
        ]);
    }

    /**
     * A member of $cabang. $dun is the label its upload batch carried;
     * $matchedDun is where the voter roll says they are registered (defaults to
     * the same DUN).
     */
    private function member(KeanggotaanBatch $batch, string $cabang, string $dun, string $statusKawasan, ?string $matchedDun = null): void
    {
        static $n = 0;
        $n++;

        Keanggotaan::create([
            'batch_id' => $batch->id,
            'no_ic' => str_pad((string) $n, 12, '7', STR_PAD_LEFT),
            'nama' => 'AHLI '.$n,
            'cabang' => $cabang,
            'dun' => $dun,
            'matched_kadun' => $matchedDun ?? $dun,
            'matched_parlimen' => $cabang,
            'status_kawasan' => $statusKawasan,
        ]);
    }

    /** Cabang KUALA PILAH: 3 members in DUN PILAH, 5 in DUN JOHOL. */
    private function seedCabang(): void
    {
        $batch = $this->batch();
        $this->member($batch, 'KUALA PILAH', 'PILAH', 'dalam_kawasan');
        $this->member($batch, 'KUALA PILAH', 'PILAH', 'dalam_kawasan');
        $this->member($batch, 'KUALA PILAH', 'PILAH', 'tiada_dppr');
        foreach (range(1, 4) as $ignored) {
            $this->member($batch, 'KUALA PILAH', 'JOHOL', 'dalam_kawasan');
        }
        $this->member($batch, 'KUALA PILAH', 'JOHOL', 'tiada_dppr');
    }

    private function summary(array $query): array
    {
        $response = $this->actingAs($this->admin())
            ->get(route('keanggotaan.analisa', $query));

        $response->assertOk();

        return $response->viewData('page')['props']['summary'];
    }

    public function test_kawasan_cards_follow_the_dun_drill(): void
    {
        $this->seedCabang();

        $summary = $this->summary(['parlimen' => 'KUALA PILAH', 'dun' => 'PILAH']);

        $this->assertSame(3, $summary['total'], 'Jumlah Ahli is DUN-scoped.');
        $this->assertSame(2, $summary['dalam_kawasan'], 'Dalam Kawasan must not count the JOHOL members.');
        $this->assertSame(1, $summary['tiada_dppr'], 'Tiada DPPR/DPT must not count the JOHOL members.');
        $this->assertLessThanOrEqual(
            $summary['total'],
            $summary['dalam_kawasan'],
            'Dalam Kawasan can never exceed the roster it is displayed beside.',
        );
    }

    public function test_cabang_total_is_still_published_for_the_luar_cards(): void
    {
        $this->seedCabang();

        $summary = $this->summary(['parlimen' => 'KUALA PILAH', 'dun' => 'PILAH']);

        $this->assertSame(8, $summary['cabang_total'], 'The whole Cabang is still counted, under its own key.');
        $this->assertSame(5, $summary['luar_dun'], 'Members registered outside the focused DUN stay Cabang-scoped.');
    }

    public function test_kawasan_cards_cover_the_whole_cabang_when_no_dun_is_selected(): void
    {
        $this->seedCabang();

        $summary = $this->summary(['parlimen' => 'KUALA PILAH']);

        $this->assertSame(8, $summary['total']);
        $this->assertSame(6, $summary['dalam_kawasan']);
        $this->assertSame(2, $summary['tiada_dppr']);
        $this->assertSame(8, $summary['cabang_total']);
    }

    /**
     * A DUN's roster is the union of two independent sources — the DUN label on
     * the upload batch, and the voter-roll IC match — so it can hold more
     * members than the file uploaded for that DUN. The sumber_dun filter splits
     * the union so those extras can be identified.
     */
    public function test_sumber_dun_filter_isolates_members_the_file_did_not_carry(): void
    {
        $batch = $this->batch();
        // Two members uploaded under the PILAH file.
        $this->member($batch, 'KUALA PILAH', 'PILAH', 'dalam_kawasan');
        $this->member($batch, 'KUALA PILAH', 'PILAH', 'dalam_kawasan');
        // One uploaded under the JOHOL file, but registered to vote in PILAH.
        $this->member($batch, 'KUALA PILAH', 'JOHOL', 'dalam_kawasan', 'PILAH');

        $count = function (array $query) {
            $response = $this->actingAs($this->admin())->get(route('keanggotaan.senarai', $query));
            $response->assertOk();

            return $response->viewData('page')['props']['members']['total'];
        };

        $base = ['parlimen' => 'KUALA PILAH', 'dun' => 'PILAH'];

        $this->assertSame(3, $count($base), 'Unfiltered, the DUN holds the union of both sources.');
        $this->assertSame(2, $count($base + ['sumber_dun' => 'fail']), 'Only the members the PILAH file carried.');
        $this->assertSame(1, $count($base + ['sumber_dun' => 'dpt_sahaja']), 'Only the member the voter roll pulled in.');
    }
}
