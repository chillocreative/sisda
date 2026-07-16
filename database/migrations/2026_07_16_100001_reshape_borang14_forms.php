<?php
// database/migrations/2026_07_16_100001_reshape_borang14_forms.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // borang14_forms dan scoreboards kosong (0 baris) — drop+recreate selamat.
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

    public function down(): void
    {
        Schema::dropIfExists('scoreboards');
        Schema::dropIfExists('borang14_votes');
        Schema::dropIfExists('borang14_forms');
    }
};
