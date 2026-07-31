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
