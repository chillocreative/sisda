<?php

namespace Tests\Feature;

use App\Models\Borang14Vote;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Apabila PRU dan PRN diadakan serentak, SATU saluran menghasilkan DUA set
 * undi. Slot 1 pada saluran yang sama ialah calon BN dalam KEDUA-DUA
 * pertandingan, jadi kunci sel lama (form, pusat, saluran, slot) membuatkan
 * kedua-duanya berlanggar. Ujian ini memaku pembetulan kunci itu.
 */
class Borang14SerentakMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function form(string $kawasanType, int $kawasanId, string $jenisPr): int
    {
        return DB::table('borang14_forms')->insertGetId([
            'kawasan_type' => $kawasanType,
            'kawasan_id' => $kawasanId,
            'jenis_pr' => $jenisPr,
            'tahun' => 2027,
            'penjuru' => 3,
            'parties' => json_encode([]),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_votes_table_has_the_contest_column(): void
    {
        $this->assertTrue(Schema::hasColumn('borang14_votes', 'contest'));
    }

    public function test_forms_table_can_link_to_a_parlimen_form(): void
    {
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'borang14_form_parlimen_id'));
    }

    public function test_both_contests_can_hold_the_same_saluran_and_slot(): void
    {
        $form = $this->form('dun', 34, 'prn');

        // Slot 1 = BN dalam kedua-dua pertandingan pada saluran yang SAMA.
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 224,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'parlimen',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 93,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, DB::table('borang14_votes')->where('borang14_form_id', $form)->count());
        $this->assertSame(224, (int) DB::table('borang14_votes')
            ->where(['borang14_form_id' => $form, 'contest' => 'dun', 'saluran' => '3', 'slot' => 1])->value('undi'));
        $this->assertSame(93, (int) DB::table('borang14_votes')
            ->where(['borang14_form_id' => $form, 'contest' => 'parlimen', 'saluran' => '3', 'slot' => 1])->value('undi'));
    }

    public function test_the_same_cell_within_one_contest_is_still_unique(): void
    {
        $form = $this->form('dun', 34, 'prn');

        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 224,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'dun',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 999,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_down_refuses_rather_than_lose_data(): void
    {
        $migration = require database_path('migrations/2026_08_01_100001_add_contest_to_borang14_votes.php');

        $form = $this->form('dun', 34, 'prn');
        DB::table('borang14_votes')->insert([
            'borang14_form_id' => $form, 'contest' => 'parlimen',
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 93,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    /**
     * RefreshDatabase sudah menjalankan migrasi 2026_08_01 ini (ia migrasi
     * TERAKHIR), jadi setiap ujian lain di atas berjalan pada pangkalan data
     * yang SUDAH mempunyai `contest` dan kunci unik baharu — tiada satu pun
     * daripadanya benar-benar menjalankan satu-satunya statement yang akan
     * menyentuh baris undi PRODUKSI sebenar: backfill dalam up().
     *
     * Bina semula bentuk PRA-migrasi borang14_votes (tiada `contest`, kunci
     * unik lama) meniru resetToOldSchema() dalam Borang14MigrationGuardTest,
     * sisipkan baris pada KEDUA-DUA jenis borang (DUN dan Parlimen), jalankan
     * up(), dan sahkan setiap baris menerima `contest` yang DITERBITKAN
     * daripada kawasan_type borangnya SENDIRI — bukan nilai tunggal yang
     * kebetulan betul kerana pangkalan data ujian biasanya kosong.
     */
    public function test_backfill_derives_contest_from_each_rows_own_form(): void
    {
        $dunForm = $this->form('dun', 34, 'prn');
        $parlimenForm = $this->form('parlimen', 12, 'pru');

        // Gugurkan pengawal larian-ulang migrasi ini (lihat docblock up()), dan
        // bina semula borang14_votes dalam bentuk SEBELUM migrasi ini wujud —
        // barulah up() sebenarnya menjalankan backfill, bukan pulang awal.
        $this->resetKepadaSkemaLama();

        DB::table('borang14_votes')->insert([
            ['borang14_form_id' => $dunForm, 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 111, 'created_at' => now(), 'updated_at' => now()],
            ['borang14_form_id' => $dunForm, 'pusat' => 'SK GEMAS', 'saluran' => '2', 'slot' => 2, 'undi' => 222, 'created_at' => now(), 'updated_at' => now()],
            ['borang14_form_id' => $parlimenForm, 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 333, 'created_at' => now(), 'updated_at' => now()],
        ]);

        (require database_path('migrations/2026_08_01_100001_add_contest_to_borang14_votes.php'))->up();

        $this->assertSame('dun', DB::table('borang14_votes')
            ->where(['borang14_form_id' => $dunForm, 'saluran' => '1'])->value('contest'));
        $this->assertSame('dun', DB::table('borang14_votes')
            ->where(['borang14_form_id' => $dunForm, 'saluran' => '2'])->value('contest'));
        $this->assertSame('parlimen', DB::table('borang14_votes')
            ->where(['borang14_form_id' => $parlimenForm, 'saluran' => '1'])->value('contest'));

        $this->assertSame(
            3,
            DB::table('borang14_votes')->whereNotNull('contest')->count(),
            'Tiada baris sepatutnya hilang — backfill mesti berjaya bagi kedua-dua jenis borang.'
        );
    }

    /**
     * Bawa borang14_forms + borang14_votes kembali ke bentuk PRA-migrasi supaya
     * up() benar-benar berjalan (bukan pulang awal pada pengawal larian-ulang).
     *
     * Dipanggil SEBELUM mana-mana jadual anak disemai: menggugurkan lajur
     * borang14_form_parlimen_id sendiri membina semula borang14_forms, jadi
     * apa-apa yang disemai lebih awal akan dimusnahkan oleh PERSEDIAAN ujian
     * dan bukan oleh perkara yang diuji.
     */
    private function resetKepadaSkemaLama(): void
    {
        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->dropForeign(['borang14_form_parlimen_id']);
        });
        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->dropColumn('borang14_form_parlimen_id');
        });

        Schema::dropIfExists('borang14_votes');
        Schema::create('borang14_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->string('pusat')->default('');
            $table->string('saluran');
            $table->unsignedTinyInteger('slot');
            $table->unsignedInteger('undi')->default(0);
            $table->timestamps();

            $table->unique(['borang14_form_id', 'pusat', 'saluran', 'slot'], 'borang14_votes_cell_unique');
        });
    }

    /**
     * Semakan akhir cawangan ini MEMBUKTIKAN, dengan menjalankan up() pada
     * pangkalan data SQLite yang berisi, bahawa pengawal asal (yang hanya
     * melindungi borang14_votes) tidak mencukupi:
     *   - borang14_snapshots 1 -> 0 (cascadeOnDelete: setiap titik pemulihan)
     *   - scoreboards.borang14_form_id -> NULL (nullOnDelete: setiap papan)
     *   - borang14_uploads + paca_forms membawa risiko nullOnDelete yang sama
     *
     * Ujian backfill sedia ada terlepas semuanya kerana ia hanya mengira undi.
     * Ujian ini menyemai SETIAP jadual anak (dan cucu PACA di bawah paca_forms,
     * yang akan lenyap sekiranya pengawal menggugurkan FK paca_forms) dan
     * menuntut semuanya SELAMAT.
     */
    public function test_the_sqlite_rebuild_does_not_destroy_any_child_table(): void
    {
        $dunForm = $this->form('dun', 34, 'prn');

        $this->resetKepadaSkemaLama();

        // --- anak: cascadeOnDelete ---
        DB::table('borang14_votes')->insert([
            ['borang14_form_id' => $dunForm, 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 111, 'created_at' => now(), 'updated_at' => now()],
            ['borang14_form_id' => $dunForm, 'pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 90, 'undi' => 7, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('borang14_snapshots')->insert([
            'borang14_form_id' => $dunForm, 'structure' => json_encode(['rows' => []]),
            'votes' => json_encode([['pusat' => 'SK GEMAS', 'saluran' => '1', 'slot' => 1, 'undi' => 111]]),
            'parties' => json_encode([]), 'reason' => 'before_structure_edit', 'created_at' => now(),
        ]);

        // --- anak: nullOnDelete ---
        DB::table('borang14_uploads')->insert([
            'borang14_form_id' => $dunForm, 'nama_fail' => 'scoresheet.pdf',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $negeri = DB::table('negeri')->insertGetId(['nama' => 'NEGERI SEMBILAN', 'created_at' => now(), 'updated_at' => now()]);
        $bandar = DB::table('bandar')->insertGetId(['nama' => 'JEMPOL', 'kod_parlimen' => 'P133', 'negeri_id' => $negeri, 'created_at' => now(), 'updated_at' => now()]);
        $kadun = DB::table('kadun')->insertGetId(['nama' => 'GEMAS', 'kod_dun' => 'N34', 'bandar_id' => $bandar, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('scoreboards')->insert([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun,
            'borang14_form_id' => $dunForm, 'title' => 'SCOREBOARD',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pacaForm = DB::table('paca_forms')->insertGetId([
            'kawasan_type' => 'dun', 'kawasan_id' => $kadun, 'jenis_pr' => 'prn', 'tahun' => 2027,
            'borang14_form_id' => $dunForm, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Cucu PACA: inilah yang akan MUSNAH sekiranya pengawal memilih jalan
        // mudah dan menggugurkan FK paca_forms (dropForeign membina semula
        // paca_forms, dan DROP itu cascade ke bawah).
        $pacaPusat = DB::table('paca_pusat')->insertGetId([
            'paca_form_id' => $pacaForm, 'pusat' => 'SK GEMAS', 'public_token' => 'tok-uji-1',
            'urutan' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pacaSaluran = DB::table('paca_saluran')->insertGetId([
            'paca_pusat_id' => $pacaPusat, 'label' => '1', 'urutan' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('paca_slot')->insert([
            'paca_saluran_id' => $pacaSaluran, 'jawatan' => 'PA1', 'urutan' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (require database_path('migrations/2026_08_01_100001_add_contest_to_borang14_votes.php'))->up();

        // cascadeOnDelete — baris mesti masih ada.
        $this->assertSame(2, DB::table('borang14_votes')->where('borang14_form_id', $dunForm)->count(),
            'Undi mesti selamat merentas pembinaan semula borang14_forms.');
        $this->assertSame(1, DB::table('borang14_snapshots')->where('borang14_form_id', $dunForm)->count(),
            'Setiap titik pemulihan akan hilang tanpa pengawal — inilah kes yang dibuktikan penyemak.');

        // nullOnDelete — pautan mesti kekal, bukan sekadar barisnya.
        $this->assertSame($dunForm, (int) DB::table('borang14_uploads')->value('borang14_form_id'));
        $this->assertSame($dunForm, (int) DB::table('scoreboards')->value('borang14_form_id'),
            'Papan markah kehilangan borangnya tanpa pengawal.');
        $this->assertSame($dunForm, (int) DB::table('paca_forms')->value('borang14_form_id'));

        // Cucu PACA — dimusnahkan oleh pengawal "gugurkan setiap FK" yang naif.
        $this->assertSame(1, DB::table('paca_pusat')->count(), 'Roster PACA tidak boleh disentuh langsung.');
        $this->assertSame(1, DB::table('paca_saluran')->count());
        $this->assertSame(1, DB::table('paca_slot')->count());

        // Dan skema akhir mesti tetap lengkap.
        $this->assertTrue(Schema::hasColumn('borang14_forms', 'borang14_form_parlimen_id'));
        $this->assertTrue(Schema::hasColumn('borang14_votes', 'contest'));
    }

    /**
     * Model::booted() jaring lalai `contest` untuk penulis lama yang belum
     * dikemas kini (lihat docblock Borang14Vote::booted()). Lalai itu
     * mesti DITERBITKAN daripada kawasan_type borang, bukan dikodkan keras.
     */
    public function test_creating_a_vote_on_a_dun_form_without_contest_defaults_to_dun(): void
    {
        $form = $this->form('dun', 34, 'prn');

        $vote = Borang14Vote::create([
            'borang14_form_id' => $form,
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 224,
        ]);

        $this->assertSame(Borang14Vote::CONTEST_DUN, $vote->contest);
        $this->assertSame('dun', DB::table('borang14_votes')->where('id', $vote->id)->value('contest'));
    }

    public function test_creating_a_vote_on_a_parlimen_form_without_contest_defaults_to_parlimen(): void
    {
        $form = $this->form('parlimen', 12, 'pru');

        $vote = Borang14Vote::create([
            'borang14_form_id' => $form,
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 93,
        ]);

        $this->assertSame(Borang14Vote::CONTEST_PARLIMEN, $vote->contest);
        $this->assertSame('parlimen', DB::table('borang14_votes')->where('id', $vote->id)->value('contest'));
    }

    /**
     * Kes serentak: borang DUN yang turut merekod undi Parlimen. `contest`
     * yang dihantar secara eksplisit mesti MENANG berbanding lalai borang
     * itu sendiri — inilah sebab jaring keselamatan itu tidak boleh
     * digunakan oleh penulis serentak (lihat docblock model).
     */
    public function test_explicit_contest_always_wins_over_the_form_default(): void
    {
        $form = $this->form('dun', 34, 'prn');

        $vote = Borang14Vote::create([
            'borang14_form_id' => $form, 'contest' => Borang14Vote::CONTEST_PARLIMEN,
            'pusat' => 'SK GEMAS', 'saluran' => '3', 'slot' => 1, 'undi' => 93,
        ]);

        $this->assertSame(Borang14Vote::CONTEST_PARLIMEN, $vote->contest);
        $this->assertSame('parlimen', DB::table('borang14_votes')->where('id', $vote->id)->value('contest'));
    }
}
