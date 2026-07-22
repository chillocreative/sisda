<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StickyFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        // UserFactory tidak menetapkan lajur NOT NULL `telephone` (pepijat sedia ada).
        return User::factory()->create([
            'role' => 'super_admin',
            'telephone' => '01277'.random_int(10000, 99999),
        ]);
    }

    /** Laluan ujian yang memantulkan apa yang pengawal SEBENARNYA nampak. */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sticky_filters.ujian', [
            'routes' => ['ujian.penapis'],
            'keys' => ['negeri_id', 'bandar_id'],
        ]);

        Route::middleware(['web', 'auth'])->get('/ujian-penapis', function () {
            return response()->json([
                'negeri_id' => request()->input('negeri_id'),
                'bandar_id' => request()->input('bandar_id'),
                'penceroboh' => request()->input('penceroboh'),
            ]);
        })->name('ujian.penapis');
    }

    public function test_filters_are_remembered_and_merged_into_a_bare_request(): void
    {
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=5&bandar_id=40')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => '40']);

        // Navigasi biasa: TIADA parameter langsung -> pulihkan.
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'bandar_id' => '40']);
    }

    public function test_clearing_a_filter_is_remembered_as_cleared(): void
    {
        $this->actingAs($this->user())->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        // "Set Semula" menghantar kunci HADIR-TETAPI-KOSONG.
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=&bandar_id=')
            ->assertJson(['negeri_id' => '', 'bandar_id' => '']);

        // Lawatan seterusnya mesti memulihkan TIADA APA-APA, bukan nilai lama.
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '', 'bandar_id' => '']);
    }

    public function test_a_key_outside_the_whitelist_is_never_merged(): void
    {
        $this->actingAs($this->user())
            ->getJson('/ujian-penapis?negeri_id=5&penceroboh=jahat');

        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => '5', 'penceroboh' => null]);
    }

    public function test_two_users_do_not_share_remembered_filters(): void
    {
        $this->actingAs($this->user())->getJson('/ujian-penapis?negeri_id=5&bandar_id=40');

        $this->actingAs($this->user())
            ->getJson('/ujian-penapis')
            ->assertJson(['negeri_id' => null, 'bandar_id' => null]);
    }
}
