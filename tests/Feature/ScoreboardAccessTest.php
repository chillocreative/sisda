<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\Scoreboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap hujung pemilik mesti memanggil SeatScope. Ujian ini memandu setiap
 * peranan melalui kerusi sendiri dan kerusi asing supaya pengawal tidak boleh
 * terlepas satu semakan (kelas IDOR Julai 2026).
 */
class ScoreboardAccessTest extends TestCase
{
    use RefreshDatabase;

    private Kadun $dunSendiri;
    private Kadun $dunAsing;
    private Bandar $parlimenSendiri;

    protected function setUp(): void
    {
        parent::setUp();

        $negeri = Negeri::create(['nama' => 'NEGERI SEMBILAN']);
        $this->parlimenSendiri = Bandar::create(['nama' => 'KUALA PILAH', 'kod_parlimen' => 'P129', 'negeri_id' => $negeri->id]);
        $lain = Bandar::create(['nama' => 'JEMPOL', 'kod_parlimen' => 'P130', 'negeri_id' => $negeri->id]);
        $this->dunSendiri = Kadun::create(['nama' => 'PILAH', 'kod_dun' => 'N27', 'bandar_id' => $this->parlimenSendiri->id]);
        $this->dunAsing = Kadun::create(['nama' => 'BAHAU', 'kod_dun' => 'N31', 'bandar_id' => $lain->id]);
    }

    private function user(string $role, ?int $bandarId = null, ?int $kadunId = null): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "Pengguna {$n}",
            'email' => "akses{$n}@example.test",
            'telephone' => '01400000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'password' => bcrypt('rahsia'),
            'role' => $role,
            'status' => 'approved',
            'bandar_id' => $bandarId,
            'kadun_id' => $kadunId,
        ]);
    }

    public function test_plain_user_can_open_the_scoreboard_page(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)->get(route('pilihanraya.scoreboard'))->assertOk();
    }

    public function test_ketua_paca_dun_is_refused(): void
    {
        $u = $this->user('ketua_paca_dun', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)->get(route('pilihanraya.scoreboard'))->assertForbidden();
    }

    public function test_user_may_read_own_seat_but_not_a_foreign_one(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)
            ->getJson(route('pilihanraya.scoreboard.data', ['kawasan_type' => 'dun', 'kawasan_id' => $this->dunSendiri->id]))
            ->assertOk();

        $this->actingAs($u)
            ->getJson(route('pilihanraya.scoreboard.data', ['kawasan_type' => 'dun', 'kawasan_id' => $this->dunAsing->id]))
            ->assertForbidden();
    }

    public function test_user_may_not_write_a_foreign_seat(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.settings'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunAsing->id,
            'title' => 'DIRAMPAS',
        ])->assertForbidden();

        $this->assertDatabaseMissing('scoreboards', ['kawasan_id' => $this->dunAsing->id]);
    }

    public function test_admin_may_write_a_dun_inside_own_parlimen(): void
    {
        $u = $this->user('admin', bandarId: $this->parlimenSendiri->id);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.settings'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
            'title' => 'PILAH 2026',
            'pihak_kami' => [1],
        ])->assertOk();

        $this->assertDatabaseHas('scoreboards', [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
            'title' => 'PILAH 2026',
        ]);
    }

    public function test_saving_settings_with_only_the_seat_keys_defaults_the_title(): void
    {
        // Simpanan separa yang sah — cth. muat naik logo sahaja, atau hanya
        // menetapkan pihak_kami — tidak menghantar 'title' langsung. 'title'
        // ialah 'nullable', jadi validated() tidak akan mempunyai kunci itu;
        // akses tanpa null-safe (?:) akan menyebabkan ralat 500.
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.settings'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
        ])->assertOk();

        $this->assertDatabaseHas('scoreboards', [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
            'title' => 'SCOREBOARD',
        ]);
    }

    public function test_publishing_requires_a_seat_code(): void
    {
        $tanpaKod = Kadun::create(['nama' => 'TIADA KOD', 'kod_dun' => null, 'bandar_id' => $this->parlimenSendiri->id]);
        $u = $this->user('admin', bandarId: $this->parlimenSendiri->id);

        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $tanpaKod->id,
            'title' => 'X', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.publish'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $tanpaKod->id,
            'status' => Scoreboard::STATUS_TERSIAR,
        ])->assertStatus(422);
    }

    public function test_publishing_stamps_the_uppercase_seat_code(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunSendiri->id,
            'title' => 'X', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.publish'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
            'status' => Scoreboard::STATUS_TERSIAR,
        ])->assertOk();

        $this->assertDatabaseHas('scoreboards', [
            'kawasan_id' => $this->dunSendiri->id,
            'status' => Scoreboard::STATUS_TERSIAR,
            'kod' => 'N27',
        ]);
    }

    /**
     * Pautan yang dipulangkan selepas menyiarkan MESTI membuka papan itu.
     *
     * Sebelum laluan awam dikunci semula pada kod kerusi, route() memulangkan
     * /scoreboard?kod=n27 — rentetan pertanyaan yang tidak membuka apa-apa.
     * Pemilik menyalin pautan ini untuk dikongsi, jadi ia diuji secara khusus
     * dan bukan diandaikan betul.
     */
    public function test_publishing_returns_a_link_that_actually_opens_the_board(): void
    {
        $u = $this->user('user', kadunId: $this->dunSendiri->id);

        Scoreboard::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $this->dunSendiri->id,
            'title' => 'X', 'status' => Scoreboard::STATUS_DRAF,
        ]);

        $url = $this->actingAs($u)->postJson(route('pilihanraya.scoreboard.publish'), [
            'kawasan_type' => 'dun',
            'kawasan_id' => $this->dunSendiri->id,
            'status' => Scoreboard::STATUS_TERSIAR,
        ])->assertOk()->json('url');

        $this->assertSame('/scoreboard/n27', parse_url($url, PHP_URL_PATH));
        $this->assertNull(parse_url($url, PHP_URL_QUERY), 'Kod mesti berada dalam laluan, bukan rentetan pertanyaan.');

        // Dan pautan itu benar-benar membuka papan.
        $this->get($url)->assertOk();
    }
}
