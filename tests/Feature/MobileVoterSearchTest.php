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

    public function test_search_does_not_leak_the_submitters_account_fields(): void
    {
        // Finding 1 regression: the submitter's own user account (email,
        // telephone, role, status, last_login_ip, ...) must never appear
        // in the response; submitted_by must be a minimal {id, name} array.
        $viewer = $this->makeUser('user');
        $submitter = $this->makeUser('user');
        $submitter->update(['telephone' => '0199998888', 'email' => 'submitter-secret@example.com']);
        Sanctum::actingAs($viewer);

        DataPengundi::factory()->create([
            'nama' => 'Ahmad bin Ali',
            'kadun' => 'BULOH KASAP',
            'submitted_by' => $submitter->id,
        ]);

        $res = $this->getJson('/api/mobile/voters/search?q=Ahmad')->assertOk();

        $res->assertJsonPath('voters.0.submitted_by.id', $submitter->id);
        $res->assertJsonPath('voters.0.submitted_by.name', $submitter->name);
        $res->assertJsonMissingPath('voters.0.submitted_by.email');
        $res->assertJsonMissingPath('voters.0.submitted_by.telephone');
        $res->assertJsonMissingPath('voters.0.submitted_by.last_login_ip');

        $body = $res->getContent();
        $this->assertStringNotContainsString('submitter-secret@example.com', $body);
        $this->assertStringNotContainsString('0199998888', $body);
    }

    public function test_search_does_not_match_partial_ic_for_a_user_viewer(): void
    {
        // Finding 2: a 'user' viewer must not be able to use a partial IC
        // as a substring oracle to reconstruct a masked no_ic.
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['nama' => 'Zzz Nobody', 'no_ic' => '800101015555', 'kadun' => 'BULOH KASAP']);

        $this->getJson('/api/mobile/voters/search?q=80010101555')
            ->assertOk()
            ->assertJsonCount(0, 'voters');
    }

    public function test_search_matches_full_ic_for_a_user_viewer(): void
    {
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['nama' => 'Zzz Nobody', 'no_ic' => '800101015555', 'kadun' => 'BULOH KASAP']);

        $this->getJson('/api/mobile/voters/search?q=800101015555')
            ->assertOk()
            ->assertJsonCount(1, 'voters');
    }

    public function test_search_partial_ic_still_matches_for_an_unmasking_viewer(): void
    {
        // admin/super_user/super_admin already see real ICs, so the
        // substring behaviour is preserved for them (nothing to leak).
        Sanctum::actingAs($this->makeUser('admin'));
        DataPengundi::factory()->create(['nama' => 'Zzz Nobody', 'no_ic' => '800101015555', 'bandar' => 'SEGAMAT']);

        $this->getJson('/api/mobile/voters/search?q=80010101555')
            ->assertOk()
            ->assertJsonCount(1, 'voters');
    }

    public function test_search_escapes_like_wildcards(): void
    {
        // Finding 5: q=%%% must not act as a wildcard that matches every
        // in-scope row.
        Sanctum::actingAs($this->makeUser('user'));
        DataPengundi::factory()->create(['nama' => 'Ahmad bin Ali', 'kadun' => 'BULOH KASAP']);
        DataPengundi::factory()->create(['nama' => 'Siti binti Osman', 'kadun' => 'BULOH KASAP']);

        $this->getJson('/api/mobile/voters/search?q=%25%25%25')
            ->assertOk()
            ->assertJsonCount(0, 'voters');
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

    public function test_search_validation_errors_reflect_the_actual_failure(): void
    {
        // Finding 6: the errors envelope must carry the validator's real
        // per-field message, not a fixed "at least 3 characters" string
        // for every kind of failure (e.g. q missing, or q[]=x).
        Sanctum::actingAs($this->makeUser('user'));

        $missing = $this->getJson('/api/mobile/voters/search')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->json();
        $this->assertNotEmpty($missing['errors']['q']);

        $arrayForm = $this->getJson('/api/mobile/voters/search?q[]=x')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->json();
        $this->assertNotEmpty($arrayForm['errors']['q']);
        // Must differ from the min:3 message — it's a type failure, not a length one.
        $this->assertNotSame($missing['errors']['q'], $arrayForm['errors']['q']);
    }

    public function test_show_returns_masked_voter_for_a_user_viewer(): void
    {
        // Finding 4: show()'s 200 success path had zero coverage even
        // though it's the per-voter endpoint the field app hits, and it
        // carried Finding 1's relation leak undetected.
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

        $res = $this->getJson('/api/mobile/voters/800101015555')->assertOk();

        $res->assertJsonPath('success', true)
            ->assertJsonPath('voter.nama', 'Ahmad bin Ali')
            ->assertJsonPath('voter.no_ic', '****')
            ->assertJsonPath('voter.no_tel', '****');

        $body = $res->getContent();
        $this->assertStringNotContainsString('800101015555', $body);
        $this->assertStringNotContainsString('0123456789', $body);
    }

    public function test_show_does_not_leak_records_outside_the_users_kadun(): void
    {
        Sanctum::actingAs($this->makeUser('user'));

        DataPengundi::factory()->create([
            'nama' => 'Ahmad Luar',
            'no_ic' => '800101015555',
            'kadun' => 'JEMENTAH',
        ]);

        $this->getJson('/api/mobile/voters/800101015555')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_show_does_not_leak_the_submitters_account_fields(): void
    {
        // Finding 1 regression for show(): submitted_by must be a minimal
        // {id, name} array, never the full submitting staff account.
        $viewer = $this->makeUser('user');
        $submitter = $this->makeUser('user');
        $submitter->update(['telephone' => '0199998888', 'email' => 'submitter-secret@example.com']);
        Sanctum::actingAs($viewer);

        DataPengundi::factory()->create([
            'nama' => 'Ahmad bin Ali',
            'no_ic' => '800101015555',
            'kadun' => 'BULOH KASAP',
            'submitted_by' => $submitter->id,
        ]);

        $res = $this->getJson('/api/mobile/voters/800101015555')->assertOk();

        $res->assertJsonPath('voter.submitted_by.id', $submitter->id);
        $res->assertJsonPath('voter.submitted_by.name', $submitter->name);
        $res->assertJsonMissingPath('voter.submitted_by.email');
        $res->assertJsonMissingPath('voter.submitted_by.telephone');
        $res->assertJsonMissingPath('voter.submitted_by.last_login_ip');

        $body = $res->getContent();
        $this->assertStringNotContainsString('submitter-secret@example.com', $body);
        $this->assertStringNotContainsString('0199998888', $body);
    }
}
