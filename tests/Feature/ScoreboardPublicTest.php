<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Papan draf tidak boleh bocor. Kod tidak dikenali dan papan draf mesti 404
 * secara SAMA supaya tiada petunjuk kerusi mana yang wujud.
 */
class ScoreboardPublicTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dun;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $bandar = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $this->dun = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $bandar->id]);
    }

    private function board(string $status, ?string $kod = 'N27'): Scoreboard
    {
        return Scoreboard::create([
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dun->id,
            'title' => 'PILAH',
            'status' => $status,
            'kod' => $kod,
        ]);
    }

    public function test_published_board_is_visible_without_login(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/n27')->assertOk();
        $this->getJson('/scoreboard/n27/data')->assertOk();
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/N27')->assertOk();
    }

    public function test_draft_board_404s_exactly_like_an_unknown_code(): void
    {
        $this->board(Scoreboard::STATUS_DRAF);

        $this->get('/scoreboard/n27')->assertNotFound();
        $this->get('/scoreboard/n99')->assertNotFound();
        $this->getJson('/scoreboard/n27/data')->assertNotFound();
    }

    public function test_legacy_numeric_url_redirects_to_the_code_url(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $this->get('/scoreboard/'.$this->dun->id)
            ->assertRedirect('/scoreboard/n27');
    }

    public function test_legacy_numeric_url_for_an_unpublished_seat_404s(): void
    {
        $this->board(Scoreboard::STATUS_DRAF);

        $this->get('/scoreboard/'.$this->dun->id)->assertNotFound();
    }

    public function test_bare_index_lists_only_published_boards(): void
    {
        $this->board(Scoreboard::STATUS_TERSIAR);

        $lain = Kadun::create(['nama' => 'JOHOL', 'kod_dun' => 'N26', 'bandar_id' => $this->dun->bandar_id]);
        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $lain->id,
            'title' => 'JOHOL', 'status' => Scoreboard::STATUS_DRAF, 'kod' => null,
        ]);

        $response = $this->get('/scoreboard')->assertOk();
        $boards = $response->viewData('page')['props']['boards'];

        $this->assertCount(1, $boards);
        $this->assertSame('N27', $boards[0]['kod']);
    }
}
