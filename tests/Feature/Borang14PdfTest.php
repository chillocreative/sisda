<?php
// tests/Feature/Borang14PdfTest.php
namespace Tests\Feature;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser as PdfParser;
use Tests\TestCase;

/**
 * Task 11 gap 1: a Parlimen-type Borang 14 form must be able to export PDF,
 * not just DUN. Covers: parlimen PDF, DUN PDF, wrong-table id rejection, and
 * the Buloh Kasap Undi Awal/Pos merge staying DUN-only even when a Parlimen
 * happens to share id 41.
 */
class Borang14PdfTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin', 'telephone' => '0123456789']);
    }

    /** Seeds pangkalan_data_pengundi so Borang14Reference::forBandar()/forKadun() resolve non-null. */
    private function seedDpt(string $negeriNama, string $parlimenNama, ?string $kadunNama, string $dm, string $lokaliti): void
    {
        $user = User::factory()->create(['telephone' => '0123456781']);
        $batch = UploadBatch::create([
            'nama_fail' => 'ujian.csv', 'fail_path' => 'ujian.csv',
            'jumlah_rekod' => 1, 'status' => 'completed', 'is_active' => true,
            'uploaded_by' => $user->id,
        ]);

        PangkalanDataPengundi::create([
            'upload_batch_id' => $batch->id,
            'no_ic' => '900101011234',
            'nama' => 'Pengundi Ujian',
            'lokaliti' => $lokaliti,
            'daerah_mengundi' => $dm,
            'kadun' => $kadunNama,
            'parlimen' => $parlimenNama,
            'negeri' => $negeriNama,
        ]);
    }

    public function test_pdf_works_for_dun_type_form(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);
        $this->seedDpt('Negeri Ujian', 'Parlimen Ujian', 'Dun Ujian', 'DM Ujian', 'Kg Ujian');

        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2,
        ]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_pdf_works_for_parlimen_type_form(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $this->seedDpt('Negeri Ujian', 'Parlimen Ujian', null, 'DM Ujian', 'Kg Ujian');

        Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2,
        ]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_kawasan_id_from_wrong_table_is_rejected(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        // Sengaja TIDAK cipta sebarang kadun — bandar->id tidak wujud dalam jadual kadun.

        $res = $this->actingAs($this->adminUser())->getJson(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2,
        ]));

        $res->assertStatus(422);
    }

    public function test_buloh_kasap_merge_does_not_apply_to_parlimen_with_same_id(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        // Cipta bandar (Parlimen) dengan ID 41 secara eksplisit — sama seperti
        // BULOH_KASAP_KADUN_ID — untuk buktikan flag tersebut DUN-sahaja.
        $bandar = Bandar::create(['nama' => 'Parlimen 41', 'negeri_id' => $negeri->id]);
        \DB::table('bandar')->where('id', $bandar->id)->update(['id' => 41]);
        $bandar = Bandar::find(41);
        $this->seedDpt('Negeri Ujian', 'Parlimen 41', null, 'DM Ujian', 'Kg Ujian');

        Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => 41,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'parlimen', 'kawasan_id' => 41,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2,
        ]));

        $res->assertOk();

        $parser = new PdfParser();
        $text = $parser->parseContent($res->getContent())->getText();

        $this->assertStringNotContainsString('UNDI AWAL & POS', $text, 'Parlimen ID 41 TIDAK sepatutnya menggabungkan Undi Awal & Pos — itu kekecualian khas DUN Buloh Kasap sahaja.');
        $this->assertStringContainsString('UNDI AWAL', $text);
        $this->assertStringContainsString('UNDI POS', $text);
    }

    /**
     * Finding 4 (Important): pdf() still aborted 404 whenever
     * Borang14Reference::forKadun()/forBandar() returned null — but data()
     * already falls back to referenceFromStructure() for scoresheet-only
     * seats (no curated reference, no DPT roll uploaded). Every seat this
     * feature creates via upload is exactly that case, so "Muat Turun PDF"
     * always failed for them. pdf() must get the same fallback; the 404
     * should only remain for the genuinely-no-data case (no reference AND no
     * saved structure).
     */
    public function test_pdf_falls_back_to_structure_reference_for_scoresheet_only_seat(): void
    {
        // Deliberately NO seedDpt() call — this seat has no DPT roll and no
        // curated reference JSON, exactly like every seat this feature creates.
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Juasseh Ujian', 'bandar_id' => $bandar->id]);

        $form = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'draft',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'A'], ['nama' => 'B']],
                'rows' => [[
                    'pusat' => 'PM Ujian', 'dm' => 'DM Ujian', 'saluran' => '1',
                    'a' => 10, 'undi' => [6, 4], 'jumlah_undian' => 10, 'ditolak' => 0, 'tidak_dimasukkan' => 0,
                ]],
            ],
        ]);
        $form->votes()->create(['pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 1, 'undi' => 6]);
        $form->votes()->create(['pusat' => 'PM Ujian', 'saluran' => '1', 'slot' => 2, 'undi' => 4]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
        ]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    /**
     * Finding 1 (warisi-fix): data() gained a THIRD fallback — inherit the
     * structure from the most recent OTHER election of the same seat when
     * neither curated JSON/DPT nor this election's own scoresheet structure
     * exists. pdf() never got that third fallback, so a user who keys votes
     * on an inherited grid (saveParties/saveVote — which never write
     * form.structure) got a 404 "Data Borang 14 belum tersedia" the moment
     * they clicked "Muat Turun PDF", even though the on-screen grid worked
     * fine. pdf() must resolve the SAME chain as data().
     */
    public function test_pdf_falls_back_to_inherited_structure_for_a_freshly_keyed_election(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Juasseh Ujian', 'bandar_id' => $bandar->id]);
        // Deliberately no seedDpt() / curated reference — this seat only has
        // a PRIOR election's scoresheet-derived structure to inherit from.

        // The source (2023) election: has its OWN structure from a scoresheet upload.
        $source = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2, 'status' => 'published',
            'source' => 'scoresheet',
            'structure' => [
                'calon' => [['nama' => 'Calon A'], ['nama' => 'Calon B']],
                'rows' => [[
                    'pusat' => 'SK Juasseh', 'dm' => 'DM Juasseh', 'saluran' => '1',
                    'a' => 500, 'undi' => [300, 190], 'jumlah_undian' => 490,
                    'ditolak' => 5, 'tidak_dimasukkan' => 5,
                ]],
            ],
        ]);
        $source->votes()->create(['pusat' => 'SK Juasseh', 'saluran' => '1', 'slot' => 1, 'undi' => 300]);
        $source->votes()->create(['pusat' => 'SK Juasseh', 'saluran' => '1', 'slot' => 2, 'undi' => 190]);

        // The TARGET (2026) election: a user keyed votes on the inherited
        // grid via saveParties()/saveVote(), which NEVER write form.structure
        // — exactly reproducing the bug (structure stays null on this row).
        $target = Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2026, 'penjuru' => 2, 'status' => 'draft',
            'parties' => [['slot' => 1, 'nama' => 'Parti A'], ['slot' => 2, 'nama' => 'Parti B']],
        ]);
        $target->votes()->create(['pusat' => 'SK Juasseh', 'saluran' => '1', 'slot' => 1, 'undi' => 10]);
        $target->votes()->create(['pusat' => 'SK Juasseh', 'saluran' => '1', 'slot' => 2, 'undi' => 5]);

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2026, 'penjuru' => 2,
        ]));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));

        // The PDF must carry the SAME provenance disclosure as the screen —
        // a printed Borang 14 that silently presents a 2023 layout as 2026's
        // official structure is a trust failure on paper.
        $parser = new PdfParser();
        $text = $parser->parseContent($res->getContent())->getText();
        $this->assertStringContainsString('diwarisi', $text);
        $this->assertStringContainsString('PRN 2023', $text);

        // And the votes printed must be the TARGET's own (10/5), never the
        // source election's (300/190) — no vote leakage across elections.
        $this->assertStringContainsString('10', $text);
        $this->assertStringNotContainsString('300', $text);
    }

    public function test_pdf_still_404s_when_there_is_genuinely_no_data(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);
        // No DPT, no curated reference, no form/structure at all.

        $res = $this->actingAs($this->adminUser())->get(route('pilihanraya.borang-14.pdf', [
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2023, 'penjuru' => 2,
        ]));

        $res->assertStatus(404);
    }

    /** Renders the blade DIRECTLY (bypassing dompdf) and returns a table's rows as plain cell-text arrays. */
    /**
     * Baris bagi jadual pecahan SALURAN ke-$tableIndex.
     *
     * Dipilih mengikut class="saluran" dan bukan kedudukan mutlak dalam
     * dokumen: muka ringkasan mempunyai jadualnya sendiri di HADAPAN jadual
     * saluran, jadi indeks mutlak akan bergeser setiap kali sesuatu ditambah
     * pada bahagian atas cetakan.
     */
    private function tableRows(string $html, int $tableIndex = 0): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $table = $xpath->query('//table[contains(@class, "saluran")]')->item($tableIndex);

        $rows = [];
        foreach ($xpath->query('.//tr', $table) as $tr) {
            $cells = [];
            foreach ($xpath->query('./td|./th', $tr) as $cell) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * Finding 6 (Important): the PDF never got slots 90/91 (Ditolak/Tidak
     * Dimasukkan) columns, so its "Jumlah Keluar" summed only party undi
     * while the on-screen Borang14Form.jsx sums undi + C + D — the SAME
     * published form printed a turnout figure that disagreed with the screen
     * by C+D. The PDF must gain both columns and match the screen's formula.
     */
    public function test_pdf_gains_ditolak_tidak_dimasukkan_columns_and_matches_screen_jumlah_keluar_formula(): void
    {
        $reference = [
            'negeri' => 'Negeri Ujian', 'parlimen' => 'Parlimen Ujian', 'dun' => 'Dun Ujian',
            'daerah_mengundi' => [[
                'nama' => 'DM Ujian',
                'pusat_mengundi' => [[
                    'nama' => 'PM Ujian', 'jumlah_berdaftar' => 20,
                    'saluran' => [['no' => 1, 'berdaftar' => 20]],
                ]],
            ]],
            'undi_awal' => ['berdaftar' => 0], 'undi_pos' => ['berdaftar' => 0],
            'source' => 'dpt_estimate',
        ];
        // Party undi: 6 + 4 = 10 (Jumlah Undian). Ditolak (C) = 2, Tidak
        // Dimasukkan (D) = 1. Screen's Jumlah Keluar = undi + C + D = 13.
        // Kunci sel membawa contest sebagai komponen pertama — sama seperti
        // Borang14Controller::cellKey().
        $votes = ['dun|PM Ujian|1|1' => 6, 'dun|PM Ujian|1|2' => 4, 'dun|PM Ujian|1|90' => 2, 'dun|PM Ujian|1|91' => 1];

        $html = view('pdf.borang14', [
            'reference' => $reference, 'penjuru' => 2, 'penjuruLabel' => '1 vs 1',
            'parties' => [['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B']],
            'votes' => $votes, 'contest' => 'dun', 'logo' => null, 'isBulohKasap' => false,
        ])->render();

        $rows = $this->tableRows($html, 0);

        $this->assertSame(
            ['Saluran', 'PARTI A', 'PARTI B', 'Ditolak (C)', 'Tak Dimasukkan (D)', 'Jumlah Undian', 'Jumlah Keluar', 'Berdaftar', '% Turnout', 'Tak Keluar', '% Tak Keluar'],
            $rows[0],
        );

        // undian = 6+4 = 10; keluar = 10+2+1 = 13 (matches Borang14Form.jsx's
        // `keluar = undian + ditolak + tidakMasuk`); berdaftar=20 known ->
        // takKeluar = 20-13 = 7; %turnout = 65.0%; %takKeluar = 35.0%.
        $this->assertSame(['Saluran 1', '6', '4', '2', '1', '10', '13', '20', '65.0%', '7', '35.0%'], $rows[1]);
    }

    /**
     * Finding 6 (second part): berdaftar ?? 0 and Tak Keluar with no max(0, …)
     * guard. Once scoresheet-only seats reach the PDF (finding 4), berdaftar
     * is genuinely UNKNOWN (the scoresheet has no registered-voter column —
     * column (A) is ballots in the box), so printing 0 is a lie and
     * "Tak Keluar" can go negative. Unknown berdaftar must print '—', never 0
     * — while a genuinely-zero Ditolak/Tidak Dimasukkan must still print 0.
     */
    public function test_pdf_prints_dash_not_zero_for_unknown_berdaftar_and_never_shows_negative_tak_keluar(): void
    {
        $reference = [
            'negeri' => 'Negeri Ujian', 'parlimen' => 'Parlimen Ujian', 'dun' => 'Dun Ujian',
            'daerah_mengundi' => [[
                'nama' => 'DM Ujian',
                'pusat_mengundi' => [[
                    'nama' => 'PM Ujian', 'jumlah_berdaftar' => null,
                    'saluran' => [['no' => 1, 'berdaftar' => null]],
                ]],
            ]],
            'undi_awal' => ['berdaftar' => null], 'undi_pos' => ['berdaftar' => null],
            'source' => 'scoresheet',
        ];
        // No Ditolak/Tidak Dimasukkan votes given — those cells are a REAL
        // zero and must still print "0", not "—".
        $votes = ['dun|PM Ujian|1|1' => 6, 'dun|PM Ujian|1|2' => 4];

        $html = view('pdf.borang14', [
            'reference' => $reference, 'penjuru' => 2, 'penjuruLabel' => '1 vs 1',
            'parties' => [['slot' => 1, 'nama' => 'PARTI A'], ['slot' => 2, 'nama' => 'PARTI B']],
            'votes' => $votes, 'contest' => 'dun', 'logo' => null, 'isBulohKasap' => false,
        ])->render();

        $rows = $this->tableRows($html, 0);

        // Real zeros (Ditolak, Tidak Dimasukkan) print "0"; unknown berdaftar
        // (and everything derived from it — % Turnout, Tak Keluar, % Tak Keluar)
        // prints '—', never a fabricated 0 or a negative number.
        $this->assertSame(['Saluran 1', '6', '4', '0', '0', '10', '10', '—', '—', '—', '—'], $rows[1]);
    }

    public function test_senarai_returns_kawasan_and_geography_ids(): void
    {
        $negeri = Negeri::create(['nama' => 'Negeri Ujian']);
        $bandar = Bandar::create(['nama' => 'Parlimen Ujian', 'negeri_id' => $negeri->id]);
        $kadun = Kadun::create(['nama' => 'Dun Ujian', 'bandar_id' => $bandar->id]);

        Borang14Form::create([
            'kawasan_type' => 'parlimen', 'kawasan_id' => $bandar->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);
        Borang14Form::create([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun->id,
            'jenis_pr' => 'prn', 'tahun' => 2022, 'penjuru' => 2, 'status' => 'draft',
        ]);

        $res = $this->actingAs($this->adminUser())->getJson(route('pilihanraya.borang-14.senarai', [
            'negeri_id' => $negeri->id,
        ]));

        $res->assertOk()->assertJsonCount(2, 'rows');

        $rows = collect($res->json('rows'));
        $parlimenRow = $rows->firstWhere('kawasan_type', 'parlimen');
        $dunRow = $rows->firstWhere('kawasan_type', 'dun');

        $this->assertSame($bandar->id, $parlimenRow['kawasan_id']);
        $this->assertSame($negeri->id, $parlimenRow['negeri_id']);
        $this->assertSame($bandar->id, $parlimenRow['bandar_id']);

        $this->assertSame($kadun->id, $dunRow['kawasan_id']);
        $this->assertSame($negeri->id, $dunRow['negeri_id']);
        $this->assertSame($bandar->id, $dunRow['bandar_id']);
    }
}
