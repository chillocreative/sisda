<?php

namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\HasilCulaan;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\TujuanSumbangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCulaanReadTest extends TestCase
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

    private function makeUser(string $role = 'user'): User
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

    public function test_options_requires_authentication(): void
    {
        $this->getJson('/api/mobile/culaan/options')->assertStatus(401);
    }

    public function test_options_returns_every_taxonomy_the_form_needs(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/mobile/culaan/options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['options' => [
                'pekerjaan',
                'jenis_pekerjaan',
                'jenis_sumbangan',
                'tujuan_sumbangan',
                'bantuan_lain',
                'pemilik_rumah',
            ]]);
    }

    public function test_pekerjaan_options_match_the_servers_validation_rule(): void
    {
        Sanctum::actingAs($this->makeUser());

        // If these drift from StoreMobileCulaanRequest's in: rule, the app
        // offers choices the server will reject with a 422 the user cannot fix.
        $this->getJson('/api/mobile/culaan/options')
            ->assertOk()
            ->assertJsonPath('options.pekerjaan', ['Kerajaan', 'Swasta', 'Bekerja Sendiri', 'Tidak Bekerja']);
    }

    /**
     * Finding 3 (final-review, MINOR): only options.pekerjaan's VALUES were
     * ever locked down; the other five lists were checked with
     * assertJsonStructure, which only asserts the KEYS exist — any of the
     * actual string values could silently drift (or, per the finding, be
     * wrong from day one — the plan's own taxonomy was hand-invented and
     * wrong on 3 of 6 lists before being corrected by transcribing
     * Create.jsx verbatim) and no test would notice.
     *
     * This locks jenis_sumbangan, bantuan_lain and pemilik_rumah as fixed
     * snapshots (they are hardcoded literal arrays in the controller, same
     * as pekerjaan). jenis_pekerjaan is also hardcoded but is a nested,
     * pekerjaan-keyed structure (see options()'s docblock) — asserted here
     * as a full snapshot rather than picking one branch, since drift in any
     * category under any pekerjaan is exactly the risk this finding flags.
     */
    public function test_hardcoded_taxonomy_lists_match_the_web_forms_exact_values(): void
    {
        Sanctum::actingAs($this->makeUser());

        $options = $this->getJson('/api/mobile/culaan/options')
            ->assertOk()
            ->json('options');

        $this->assertSame([
            'Barangan Keperluan Dapur',
            'Hamper / Sumbangan Perayaan',
            'Wang Tunai / Kewangan',
            'Bantuan Perumahan (baik pulih)',
            'Bantuan Perumahan (bina baharu)',
            'Bantuan Pendidikan (yuran / kelengkapan sekolah)',
            'Bantuan Perubatan / Kesihatan',
            'Bantuan Perniagaan / Ekonomi (modal / peralatan)',
            'Bantuan Bencana / Kecemasan',
            'Lain-lain',
        ], $options['jenis_sumbangan']);

        $this->assertSame([
            'Jabatan Kebajikan Masyarakat (JKM)',
            'i-Sejahtera',
            'Zakat Pulau Pinang (ZPP)',
            'PERKESO',
            'Tiada',
            'Lain-lain',
        ], $options['bantuan_lain']);

        $this->assertSame(['Sendiri', 'Sewa', 'Keluarga', 'Lain-lain'], $options['pemilik_rumah']);

        $lainLain = ['category' => 'Lain-lain', 'items' => ['Lain-lain']];

        $this->assertSame([
            'Kerajaan' => [
                [
                    'category' => 'Jenis Perkhidmatan',
                    'items' => [
                        'Perkhidmatan Awam Persekutuan (Kementerian / Jabatan)',
                        'Perkhidmatan Awam Negeri',
                        'Pihak Berkuasa Tempatan (PBT)',
                    ],
                ],
                [
                    'category' => 'Agensi & Badan',
                    'items' => [
                        'Badan Berkanun (MARA, LHDN, KWSP, dll)',
                        'Syarikat Berkaitan Kerajaan (GLC)',
                    ],
                ],
                [
                    'category' => 'Keselamatan & Penguatkuasaan',
                    'items' => [
                        'Angkatan Tentera Malaysia (ATM)',
                        'Polis Diraja Malaysia (PDRM)',
                        'Agensi Penguatkuasaan (APMM, JPJ, Imigresen, dll)',
                    ],
                ],
                [
                    'category' => 'Pendidikan & Kesihatan',
                    'items' => [
                        'Pendidikan Awam (Guru Sekolah Kerajaan)',
                        'Pendidikan Tinggi Awam (Pensyarah IPTA)',
                        'Kesihatan Awam (Hospital / Klinik Kerajaan)',
                    ],
                ],
                $lainLain,
            ],
            'Swasta' => [
                [
                    'category' => 'Korporat & Profesional',
                    'items' => [
                        'Syarikat Korporat / Multinasional',
                        'Profesional (Jurutera, Akauntan, Arkitek, dll)',
                        'Eksekutif / Pengurusan',
                    ],
                ],
                [
                    'category' => 'Perdagangan & Perkhidmatan',
                    'items' => [
                        'Peruncitan / Jualan (Retail)',
                        'Perkhidmatan (Servis – bengkel, salon, dll)',
                        'Perhotelan & Pelancongan',
                    ],
                ],
                [
                    'category' => 'Industri & Teknikal',
                    'items' => [
                        'Perkilangan / Industri',
                        'Pembinaan / Kontraktor',
                        'Logistik & Pengangkutan',
                    ],
                ],
                [
                    'category' => 'Sektor Moden',
                    'items' => [
                        'Teknologi Maklumat / Digital',
                        'Kewangan / Perbankan / Insurans',
                    ],
                ],
                [
                    'category' => 'Sosial & Lain-lain',
                    'items' => [
                        'Pendidikan Swasta',
                        'Kesihatan Swasta',
                    ],
                ],
                $lainLain,
            ],
            'Bekerja Sendiri' => [
                [
                    'category' => 'Perniagaan & Jualan',
                    'items' => [
                        'Peniaga Kecil (gerai, pasar, online)',
                        'Usahawan / Pemilik Syarikat',
                        'E-dagang (Shopee, TikTok Shop, dll)',
                    ],
                ],
                [
                    'category' => 'Perkhidmatan',
                    'items' => [
                        'Freelance (design, IT, content creator, dll)',
                        'Servis (bengkel, tukang, plumbing, wiring, dll)',
                        'Ejen (insurans, hartanah, dll)',
                    ],
                ],
                [
                    'category' => 'Pengangkutan & Gig Economy',
                    'items' => [
                        'Pemandu e-hailing (Grab, dll)',
                        'Rider penghantaran (Foodpanda, GrabFood, dll)',
                        'Lori / Van persendirian',
                    ],
                ],
                [
                    'category' => 'Sektor Asas',
                    'items' => [
                        'Pertanian',
                        'Penternakan',
                        'Perikanan',
                    ],
                ],
                $lainLain,
            ],
            'Tidak Bekerja' => [
                [
                    'category' => 'Status',
                    'items' => [
                        'Pelajar Sekolah',
                        'Pelajar IPT (IPTA / IPTS)',
                        'Suri Rumah',
                        'Pesara Kerajaan',
                        'Pesara Swasta',
                        'Tidak Bekerja / Menganggur',
                    ],
                ],
                $lainLain,
            ],
        ], $options['jenis_pekerjaan']);
    }

    /**
     * Finding 3 continued: tujuan_sumbangan is the one list that is
     * genuinely dynamic (TujuanSumbangan::pluck('nama'), ordered by
     * sort_order — see options()'s docblock), so it cannot be pinned to a
     * fixed snapshot the way the other five can without the test itself
     * drifting from Master Data. Instead this seeds known rows and asserts
     * the endpoint reflects exactly what was seeded, in sort_order — i.e.
     * it asserts the SOURCE (real DB query, correctly ordered) rather than
     * a hardcoded value list.
     */
    public function test_tujuan_sumbangan_reflects_master_data_in_sort_order(): void
    {
        Sanctum::actingAs($this->makeUser());

        // 2026_04_08_000001_update_tujuan_sumbangan_items.php seeds this
        // table directly during migrate(), so a fresh RefreshDatabase run
        // already has rows in it. Clear them so this test's snapshot is
        // exactly and only what it seeded — asserting the live SOURCE
        // (real query, real ordering), not a value list frozen in the test.
        TujuanSumbangan::query()->delete();

        TujuanSumbangan::create(['nama' => 'Kesihatan', 'sort_order' => 2]);
        TujuanSumbangan::create(['nama' => 'Pendidikan', 'sort_order' => 1]);
        TujuanSumbangan::create(['nama' => 'Kecemasan', 'sort_order' => 3]);

        $this->getJson('/api/mobile/culaan/options')
            ->assertOk()
            ->assertJsonPath('options.tujuan_sumbangan', ['Pendidikan', 'Kesihatan', 'Kecemasan']);
    }

    public function test_mine_returns_only_records_submitted_by_the_caller(): void
    {
        $me = $this->makeUser();
        $other = $this->makeUser();
        Sanctum::actingAs($me);

        HasilCulaan::factory()->create(['nama' => 'Rekod Saya', 'submitted_by' => $me->id]);
        HasilCulaan::factory()->create(['nama' => 'Rekod Orang Lain', 'submitted_by' => $other->id]);

        $this->getJson('/api/mobile/culaan/mine')
            ->assertOk()
            ->assertJsonCount(1, 'culaan')
            ->assertJsonPath('culaan.0.nama', 'Rekod Saya');
    }

    public function test_mine_is_newest_first(): void
    {
        $me = $this->makeUser();
        Sanctum::actingAs($me);

        $old = HasilCulaan::factory()->create(['nama' => 'Lama', 'submitted_by' => $me->id]);
        // created_at is not in HasilCulaan::$fillable, so a plain update()
        // silently no-ops on it (mass-assignment guard drops the attribute).
        // forceFill() bypasses that guard — without it both rows share the
        // same created_at second and the ordering assertion below would
        // pass or fail depending on insertion order rather than on the
        // controller actually sorting by created_at.
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        HasilCulaan::factory()->create(['nama' => 'Baru', 'submitted_by' => $me->id]);

        $this->getJson('/api/mobile/culaan/mine')
            ->assertOk()
            ->assertJsonPath('culaan.0.nama', 'Baru');
    }

    /**
     * mine/ must never leak the submitting staff account's PII. Task 4
     * shipped exactly this bug (email/telephone/role/last_login_ip via an
     * unscoped submittedBy eager-load) — this test locks the fix in for
     * this endpoint too. A HasilCulaan whose submitter is a plain 'user'
     * is locked, so sensitive voter fields must come back masked, but the
     * submitted_by projection must stay to {id, name} only.
     */
    public function test_mine_does_not_leak_submitter_account_details(): void
    {
        $me = $this->makeUser();
        Sanctum::actingAs($me);

        HasilCulaan::factory()->create(['submitted_by' => $me->id]);

        $response = $this->getJson('/api/mobile/culaan/mine')->assertOk();

        $row = $response->json('culaan.0');

        $this->assertSame(['id' => $me->id, 'name' => $me->name], $row['submitted_by']);
        $this->assertArrayNotHasKey('email', $row['submitted_by']);
        $this->assertArrayNotHasKey('telephone', $row['submitted_by']);

        // Own record submitted by a plain 'user' role is locked; sensitive
        // fields must be masked, not the real value.
        $this->assertSame('****', $row['no_ic']);
        $this->assertSame('****', $row['alamat']);
    }
}
