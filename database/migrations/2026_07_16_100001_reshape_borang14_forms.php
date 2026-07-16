<?php
// database/migrations/2026_07_16_100001_reshape_borang14_forms.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Jadual asal yang digugurkan/dicipta semula oleh migrasi ini. */
    private const OLD_TABLES = ['scoreboards', 'borang14_votes', 'borang14_forms'];

    public function up(): void
    {
        // Reka bentuk asal mengandaikan ketiga-tiga jadual ini kosong (0 baris)
        // — tetapi repo ini AUTO-DEPLOY ke cPanel setiap kali push. Jika
        // sesiapa telah keyin borang sejak andaian itu dibuat, deploy akan
        // memusnahkan data itu secara senyap. Jangan benarkan — sahkan dahulu.
        $this->guardAgainstDataLoss(
            'Migrasi ini reka bentuk asal mengandaikan jadual %s kosong (0 baris) — andaian itu telah luput: '.
            'jadual ini kini mempunyai %d baris. Meneruskan migrasi ini akan MEMUSNAHKAN data borang yang '.
            'telah dimasukkan. SANDARKAN pangkalan data (contoh: mysqldump) dan jalankan migrasi data secara '.
            'manual sebelum meneruskan — JANGAN paksa migrasi ini berjalan begitu sahaja.'
        );

        Schema::dropIfExists('scoreboards');
        Schema::dropIfExists('borang14_votes');
        Schema::dropIfExists('borang14_forms');

        // Satu pilihanraya = satu borang. penjuru ialah atribut, bukan kunci.
        Schema::create('borang14_forms', function (Blueprint $table) {
            $table->id();
            // Polymorphic: tiada FK constraint kerana menunjuk ke bandar ATAU kadun.
            $table->string('kawasan_type', 10);            // 'parlimen' | 'dun'
            $table->unsignedBigInteger('kawasan_id');
            $table->string('jenis_pr', 4);                 // 'pru' | 'prn' | 'prk'
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('penjuru')->default(2);
            $table->json('parties')->nullable();           // [{slot, keahlian_parti_id, nama}]
            $table->json('structure')->nullable();         // DM/Pusat/Saluran dari scoresheet
            $table->string('status', 10)->default('draft');    // draft | published
            $table->string('source', 12)->default('manual');   // manual | scoresheet
            $table->string('source_filename')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['kawasan_type', 'kawasan_id', 'jenis_pr', 'tahun'], 'borang14_forms_election_unique');
            $table->index(['kawasan_type', 'kawasan_id']);
            $table->index(['status', 'tahun']);
        });

        // Tidak berubah dari asal — dicipta semula kerana FK menunjuk ke borang14_forms.
        Schema::create('borang14_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->string('pusat')->default('');
            $table->string('saluran');
            $table->unsignedTinyInteger('slot'); // 1..6 parti, 90 = ditolak (C), 91 = tidak dimasukkan (D)
            $table->unsignedInteger('undi')->default(0);
            $table->timestamps();

            $table->unique(['borang14_form_id', 'pusat', 'saluran', 'slot'], 'borang14_votes_cell_unique');
        });

        Schema::create('scoreboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borang14_form_id')->constrained('borang14_forms')->cascadeOnDelete();
            $table->string('title')->default('SCOREBOARD');
            $table->unsignedInteger('minima')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('candidates')->nullable();
            $table->timestamps();

            $table->unique('borang14_form_id');
        });
    }

    /**
     * down() mesti JUJUR: jika jadual skema BAHARU (kawasan_type/kawasan_id
     * polimorfik) sudah mempunyai data, TIADA cara automatik untuk menukar
     * balik ke skema lama (kadun_id sahaja) tanpa kehilangan data Parlimen
     * (skema lama tiada konsep Parlimen langsung) — jadi kita enggan dan
     * lemparkan mesej jelas, bukan separuh memusnahkan skema secara senyap.
     * Hanya apabila jadual BENAR-BENAR kosong adalah selamat untuk pulihkan
     * sepenuhnya skema asal yang digantikan oleh migrasi ini.
     */
    public function down(): void
    {
        $this->guardAgainstDataLoss(
            'Migrasi ini TIDAK BOLEH diterbalikkan (not reversible): jadual %s mempunyai %d baris dalam skema '.
            'BAHARU (kawasan_type/kawasan_id polimorfik). Tiada cara automatik untuk menukar balik rekod jenis '.
            '"parlimen" kepada skema lama (kadun_id sahaja) tanpa kehilangan data Parlimen sepenuhnya — skema '.
            'lama tiada konsep Parlimen langsung. SANDARKAN data dahulu dan tangani migrasi data secara manual '.
            'jika rollback benar-benar diperlukan.'
        );

        // Kosong — selamat untuk pulihkan sepenuhnya skema yang digantikan oleh up().
        Schema::dropIfExists('scoreboards');
        Schema::dropIfExists('borang14_votes');
        Schema::dropIfExists('borang14_forms');

        Schema::create('borang14_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kadun_id')->constrained('kadun')->cascadeOnDelete();
            $table->unsignedTinyInteger('penjuru');
            $table->json('parties')->nullable();
            $table->timestamps();

            $table->unique(['kadun_id', 'penjuru']);
        });

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

        Schema::create('scoreboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kadun_id')->constrained('kadun')->cascadeOnDelete();
            $table->unsignedTinyInteger('penjuru');
            $table->string('title')->default('SCOREBOARD');
            $table->unsignedInteger('minima')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('candidates')->nullable();
            $table->timestamps();

            $table->unique(['kadun_id', 'penjuru']);
        });
    }

    /** Sama untuk up() & down() — enggan teruskan jika mana-mana jadual sasaran bukan kosong. */
    private function guardAgainstDataLoss(string $messageTemplate): void
    {
        foreach (self::OLD_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = DB::table($table)->count();
            if ($count > 0) {
                throw new \RuntimeException(sprintf($messageTemplate, "'{$table}'", $count));
            }
        }
    }
};
