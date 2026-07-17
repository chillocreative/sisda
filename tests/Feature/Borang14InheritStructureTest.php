<?php
// tests/Feature/Borang14InheritStructureTest.php
//
// The gap: on counting night for a NEW election, there is no scoresheet yet —
// the scoresheet is the OUTPUT, not the input. Borang14Controller::data()
// only ever resolved a structure from (1) curated JSON / DPT roll or (2) the
// CURRENT election's own form.structure. For a brand-new seat/year with
// neither, the page showed "belum tersedia" even though the same seat's
// PREVIOUS election already holds a complete, essentially-stable Pusat
// Mengundi / Saluran tree (uploaded via that election's own scoresheet).
//
// This adds fallback #3: inherit the structure from the most recent OTHER
// election of the SAME kawasan (kawasan_type + kawasan_id), never votes,
// never berdaftar, and always disclosed via `inherited_from` in the JSON so
// a user keying figures never mistakes a 2023 structure for 2026 official
// data.
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Borang14InheritStructureTest extends TestCase
{
    use RefreshDatabase;

    private function seedGeography(): array
    {
        $negeri = Negeri::create(['nama' => 'Negeri Sembilan Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Juasseh Ujian', 'bandar_id' => $bandar->id]);

        return [$negeri, $bandar, $kadun];
    }

    private function user(string $phone = '0123450099'): User
    {
        // UserFactory has a pre-existing bug (does not set the NOT NULL
        // `telephone` column) — worked around locally like every other
        // Borang14 test, per repo convention (out of scope to fix here).
        return User::factory()->create(['role' => 'admin', 'telephone' => $phone]);
    }

    private function sourceStructure(): array
    {
        return [
            'calon' => [['nama' => 'Calon A'], ['nama' => 'Calon B']],
            'rows' => [
                [
                    'pusat' => 'SK Juasseh', 'dm' => 'DM Juasseh', 'saluran' => '1',
                    'a' => 500, 'undi' => [300, 190], 'jumlah_undian' => 490,
                    'ditolak' => 5, 'tidak_dimasukkan' => 5,
                ],
                [
                    'pusat' => 'SK Juasseh', 'dm' => 'DM Juasseh', 'saluran' => '2',
                    'a' => 450, 'undi' => [250, 180], 'jumlah_undian' => 430,
                    'ditolak' => 10, 'tidak_dimasukkan' => 10,
                ],
                [
                    'pusat' => '', 'dm' => '', 'saluran' => 'UNDI POS',
                    'a' => 20, 'undi' => [12, 8], 'jumlah_undian' => 20,
                    'ditolak' => 0, 'tidak_dimasukkan' => 0,
                ],
            ],
        ];
    }

    private function createSourceForm(string $kawasanType, int $kawasanId, int $tahun, string $jenisPr = 'prn'): Borang14Form
    {
        $form = Borang14Form::create([
            'kawasan_type' => $kawasanType, 'kawasan_id' => $kawasanId,
            'jenis_pr' => $jenisPr, 'tahun' => $tahun, 'penjuru' => 2, 'status' => 'published',
            'source' => 'scoresheet', 'structure' => $this->sourceStructure(),
        ]);

        // Real votes for the SOURCE election — these must never leak into a
        // fresh election that merely inherits the empty structure.
        foreach ([1, 2] as $slot) {
            $form->votes()->create(['pusat' => 'SK Juasseh', 'saluran' => '1', 'slot' => $slot, 'undi' => $slot === 1 ? 300 : 190]);
            $form->votes()->create(['pusat' => 'SK Juasseh', 'saluran' => '2', 'slot' => $slot, 'undi' => $slot === 1 ? 250 : 180]);
        }

        return $form;
    }

    public function test_new_election_with_no_structure_inherits_the_previous_elections_structure(): void
    {
        [, , $kadun] = $this->seedGeography();
        $this->createSourceForm('dun', $kadun->id, 2023);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'), '2026 must inherit the 2023 structure instead of showing "no data".');
        $this->assertNotEmpty($res->json('reference.daerah_mengundi'));
        $this->assertSame('DM Juasseh', $res->json('reference.daerah_mengundi.0.nama'));
        $this->assertSame('SK Juasseh', $res->json('reference.daerah_mengundi.0.pusat_mengundi.0.nama'));
    }

    public function test_inherited_response_carries_inherited_from(): void
    {
        [, , $kadun] = $this->seedGeography();
        $this->createSourceForm('dun', $kadun->id, 2023, 'prn');

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertSame(['tahun' => 2023, 'jenis_pr' => 'prn'], $res->json('inherited_from'));
    }

    public function test_own_structure_still_wins_and_carries_no_inherited_from(): void
    {
        [, , $kadun] = $this->seedGeography();
        $this->createSourceForm('dun', $kadun->id, 2023);
        $this->createSourceForm('dun', $kadun->id, 2026); // this election has its OWN structure

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'));
        $this->assertArrayNotHasKey('inherited_from', $res->json());
    }

    public function test_most_recent_other_election_wins_when_several_exist(): void
    {
        [, , $kadun] = $this->seedGeography();
        $this->createSourceForm('dun', $kadun->id, 2018, 'prn');
        $this->createSourceForm('dun', $kadun->id, 2023, 'prn'); // most recent — must be the one inherited
        $this->createSourceForm('dun', $kadun->id, 2013, 'prn');

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertSame(['tahun' => 2023, 'jenis_pr' => 'prn'], $res->json('inherited_from'));
    }

    public function test_inheritance_does_not_leak_votes_from_the_source_election(): void
    {
        [, , $kadun] = $this->seedGeography();
        $this->createSourceForm('dun', $kadun->id, 2023); // has real, non-zero votes

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        // No form exists yet for 2026 -> no votes of ANY kind should be returned,
        // and in particular none of the 2023 source form's non-zero figures.
        $this->assertSame([], $res->json('votes'));
        $this->assertNull($res->json('form'), 'A 2026 form must not silently exist/be created merely by reading structure.');
    }

    /**
     * Finding 3 (warisi-fix): the test above only covers the case where NO
     * 2026 form exists yet, so $votes is trivially an empty collection — it
     * would NOT catch a regression that started reading votes from the
     * SOURCE form once a 2026 form does exist. This covers that case: BOTH
     * a source election (with structure AND votes) and a target election
     * (its own form, its own DIFFERENT votes at the very SAME cell key) —
     * the target must return only its own vote (77), never the source's
     * (300) for that identical (pusat, saluran, slot) key.
     */
    public function test_inheritance_does_not_leak_votes_from_source_when_target_form_already_has_its_own_votes(): void
    {
        [, , $kadun] = $this->seedGeography();
        $this->createSourceForm('dun', $kadun->id, 2023); // status published; SK Juasseh|1|1 = 300

        // Target (2026) has its OWN form already (e.g. from keying votes on
        // the inherited grid) but no OWN structure — so structure must still
        // be inherited from the source, while votes must come from the
        // target row only.
        $target = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2026, 'penjuru' => 2, 'status' => 'draft', 'parties' => [],
        ]);
        $target->votes()->create(['pusat' => 'SK Juasseh', 'saluran' => '1', 'slot' => 1, 'undi' => 77]);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertSame(
            ['SK Juasseh|1|1' => 77],
            $res->json('votes'),
            'Must return only the TARGET election own votes at this cell, never the source election value (300).',
        );
        $this->assertNotNull($res->json('form'), 'The target form DOES exist here — unlike the no-form case above.');
        $this->assertSame(['tahun' => 2023, 'jenis_pr' => 'prn'], $res->json('inherited_from'), 'Structure must still be inherited even though the target already has its own votes.');
    }

    /**
     * Finding 2 (warisi-fix): the source-form query had no `status` filter,
     * so an abandoned draft — or a scoresheet flagged needs_review because
     * the AI misread it — could become the structure a new election
     * inherits. This asserts a PUBLISHED source is preferred over a more
     * recent DRAFT one.
     */
    public function test_prefers_published_source_over_a_more_recent_draft(): void
    {
        [, , $kadun] = $this->seedGeography();
        $this->createSourceForm('dun', $kadun->id, 2018, 'prn'); // published (helper default), older

        // More recent, but an ABANDONED/unreviewed draft — must lose to the
        // older published election, not win purely on recency.
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'X'], ['nama' => 'Y']],
                'rows' => [[
                    'pusat' => 'PM DRAF', 'dm' => 'DM DRAF', 'saluran' => '1',
                    'a' => 1, 'undi' => [1, 0], 'jumlah_undian' => 1, 'ditolak' => 0, 'tidak_dimasukkan' => 0,
                ]],
            ],
        ]);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertSame(['tahun' => 2018, 'jenis_pr' => 'prn'], $res->json('inherited_from'), 'The PUBLISHED 2018 election must win over the more recent but still-draft 2023 upload.');
        $this->assertSame('DM Juasseh', $res->json('reference.daerah_mengundi.0.nama'), 'Must inherit the published election structure, not the draft.');
    }

    /**
     * Finding 2, other half: a seat whose ONLY prior election is still a
     * draft must still inherit SOMETHING rather than show "belum tersedia"
     * — published is PREFERRED, not REQUIRED outright (see report for the
     * reasoning).
     */
    public function test_falls_back_to_a_draft_source_when_no_published_source_exists(): void
    {
        [, , $kadun] = $this->seedGeography();
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet', 'structure' => $this->sourceStructure(),
        ]);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'), 'With no published prior election, the only draft available must still be inherited.');
        $this->assertSame(['tahun' => 2023, 'jenis_pr' => 'prn'], $res->json('inherited_from'));
    }

    public function test_inherited_berdaftar_stays_null(): void
    {
        [, , $kadun] = $this->seedGeography();
        $this->createSourceForm('dun', $kadun->id, 2023);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $reference = $res->json('reference');
        foreach ($reference['daerah_mengundi'] as $dm) {
            foreach ($dm['pusat_mengundi'] as $pm) {
                $this->assertNull($pm['jumlah_berdaftar'], 'A scoresheet has no registered-voter column — inherited berdaftar must stay null, never a stale 2023 figure.');
                foreach ($pm['saluran'] as $s) {
                    $this->assertNull($s['berdaftar'], 'Per-saluran berdaftar must stay null when inherited from a scoresheet-derived structure.');
                }
            }
        }
        $this->assertArrayHasKey('undi_pos', $reference);
        $this->assertArrayHasKey('berdaftar', $reference['undi_pos']);
        $this->assertNull($reference['undi_pos']['berdaftar'], 'Undi Pos berdaftar must stay null when inherited.');
    }

    /** Curated reference JSON must ALWAYS win — Buloh Kasap must not start inheriting from a fabricated prior election. */
    public function test_curated_reference_wins_over_inheritance_for_buloh_kasap(): void
    {
        $negeriId = DB::table('negeri')->insertGetId(['nama' => 'Johor', 'created_at' => now(), 'updated_at' => now()]);
        $bandarId = DB::table('bandar')->insertGetId(['nama' => 'Segamat', 'negeri_id' => $negeriId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('kadun')->insert(['id' => 41, 'nama' => 'Buloh Kasap', 'bandar_id' => $bandarId, 'created_at' => now(), 'updated_at' => now()]);

        // A prior election with a DIFFERENT (fabricated) structure — if inheritance
        // ever wins here, the reference would show "DM Ujian" instead of the real
        // curated kadun-41.json content (e.g. "AWAT").
        $this->createSourceForm('dun', 41, 2023);

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => 41, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('hasData'));
        $this->assertArrayNotHasKey('inherited_from', $res->json(), 'Buloh Kasap has curated JSON — it must never fall through to inheritance.');
        $this->assertNotSame('DM Juasseh', $res->json('reference.daerah_mengundi.0.nama'), 'Curated JSON content must win, not the fabricated source election structure.');
    }

    public function test_seat_with_no_prior_election_at_all_still_returns_no_data_state(): void
    {
        [, , $kadun] = $this->seedGeography(); // no form of any kind created for this kadun

        $res = $this->actingAs($this->user())->getJson(route('pilihanraya.borang-14.data', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id, 'jenis_pr' => 'prn', 'tahun' => 2026,
        ]));

        $res->assertOk();
        $this->assertFalse($res->json('hasData'));
        $this->assertNull($res->json('reference'));
        $this->assertArrayNotHasKey('inherited_from', $res->json());
    }
}
