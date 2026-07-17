<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileVoterSearchTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $kadun;
    private Bandar $bandar;

    protected function setUp(): void
    {
        parent::setUp();
        $negeri = Negeri::create(['nama' => 'JOHOR']);
        $this->bandar = Bandar::create(['nama' => 'SEGAMAT', 'negeri_id' => $negeri->id]);
        $this->kadun = Kadun::create(['nama' => 'BULOH KASAP', 'bandar_id' => $this->bandar->id]);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'approved',
            'telephone' => '01'.fake()->unique()->numerify('########'),
            'negeri_id' => $this->bandar->negeri_id,
            'bandar_id' => $this->bandar->id,
            'kadun_id' => $this->kadun->id,
        ]);
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/mobile/voters/search?q=Ahmad')->assertStatus(401);
    }

    public function test_search_finds_voter_by_name_within_scope(): void
    {
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['nama' => 'Ahmad bin Ali', 'kadun' => 'BULOH KASAP']);

        $this->getJson('/api/mobile/voters/search?q=Ahmad')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'voters')
            ->assertJsonPath('voters.0.nama', 'Ahmad bin Ali');
    }

    public function test_search_finds_voter_by_ic(): void
    {
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['no_ic' => '800101015555', 'kadun' => 'BULOH KASAP']);

        $this->getJson('/api/mobile/voters/search?q=800101015555')
            ->assertOk()
            ->assertJsonCount(1, 'voters');
    }

    public function test_search_does_not_leak_records_outside_the_users_kadun(): void
    {
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['nama' => 'Ahmad Luar', 'kadun' => 'JEMENTAH']);

        $this->getJson('/api/mobile/voters/search?q=Ahmad')
            ->assertOk()
            ->assertJsonCount(0, 'voters');
    }

    public function test_sensitive_fields_are_masked_for_records_submitted_by_a_user(): void
    {
        $viewer = $this->makeUser('user');
        $submitter = $this->makeUser('user');
        Sanctum::actingAs($viewer);

        DataPengundi::factory()->create([
            'nama' => 'Ahmad bin Ali',
            'no_ic' => '800101015555',
            'no_tel' => '0123456789',
            'kadun' => 'BULOH KASAP',
            'submitted_by' => $submitter->id,
        ]);

        $res = $this->getJson('/api/mobile/voters/search?q=Ahmad')->assertOk();

        // Nama is never masked; the sensitive set always is.
        $res->assertJsonPath('voters.0.nama', 'Ahmad bin Ali');
        $res->assertJsonPath('voters.0.no_ic', '****');
        $res->assertJsonPath('voters.0.no_tel', '****');
        $res->assertJsonPath('voters.0.alamat', '****');

        // Belt and braces: the real values must not appear anywhere in the body.
        $body = $res->getContent();
        $this->assertStringNotContainsString('800101015555', $body);
        $this->assertStringNotContainsString('0123456789', $body);
    }

    public function test_admin_viewer_sees_unmasked_values(): void
    {
        $submitter = $this->makeUser('user');
        Sanctum::actingAs($this->makeUser('admin'));

        DataPengundi::factory()->create([
            'nama' => 'Ahmad bin Ali',
            'no_ic' => '800101015555',
            'bandar' => 'SEGAMAT',
            'submitted_by' => $submitter->id,
        ]);

        $this->getJson('/api/mobile/voters/search?q=Ahmad')
            ->assertOk()
            ->assertJsonPath('voters.0.no_ic', '800101015555');
    }

    public function test_show_returns_404_in_bm_when_ic_not_found(): void
    {
        Sanctum::actingAs($this->makeUser('user'));

        $this->getJson('/api/mobile/voters/999999999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.no_ic.0', 'Pengundi tidak dijumpai.');
    }

    public function test_search_requires_a_query_of_at_least_three_characters(): void
    {
        Sanctum::actingAs($this->makeUser('user'));

        $this->getJson('/api/mobile/voters/search?q=Ah')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
