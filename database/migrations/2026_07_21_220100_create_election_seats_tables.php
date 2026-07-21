<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salinan tempatan keputusan rasmi SPR daripada electiondata.my.
 *
 * Kedua-dua jadual TAMBAHAN semata-mata — tiada jadual sedia ada disentuh, jadi
 * `migrate --force` semasa deploy tidak boleh menyentuh data undi Borang 14
 * yang sedang hidup di produksi.
 *
 * SETIAP lajur keputusan NULLABLE dengan sengaja. Pilihan raya yang AKAN DATANG
 * dipulangkan oleh API dengan party/majority null (cth SE-16 pada 2026-08-01).
 * Nilai lalai 0 di sini akan membaca sebagai "sifar undi" dan mereka-reka
 * kekalahan total yang tidak pernah berlaku.
 *
 * Pautan kepada geografi SISDA (kadun_id/bandar_id) ialah cache padanan, bukan
 * kunci asing berkuatkuasa — sama seperti lajur matched_* pada keanggotaan.
 * Geografi SISDA dipadan mengikut rentetan, jadi pautan ini boleh gagal dan
 * mesti dibenarkan null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_seats', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // 'n15-juasseh-negeri-sembilan'
            $table->string('nama');                    // 'Juasseh'
            $table->string('kod')->nullable();         // 'N15' / 'P129'
            $table->string('negeri')->nullable();
            $table->string('jenis', 16);               // 'dun' | 'parlimen'
            $table->foreignId('kadun_id')->nullable()->constrained('kadun')->nullOnDelete();
            $table->foreignId('bandar_id')->nullable()->constrained('bandar')->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['jenis', 'negeri']);
        });

        Schema::create('election_seat_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_seat_id')->constrained('election_seats')->cascadeOnDelete();
            $table->string('election_name')->nullable();     // 'SE-15'
            $table->date('tarikh');
            $table->string('party')->nullable();
            $table->string('party_uid')->nullable();
            $table->string('coalition')->nullable();
            $table->string('candidate')->nullable();
            $table->integer('majority')->nullable();
            $table->decimal('majority_perc', 8, 4)->nullable();
            $table->integer('voter_turnout')->nullable();
            $table->decimal('voter_turnout_perc', 8, 4)->nullable();
            $table->integer('voters_total')->nullable();
            $table->integer('votes_rejected')->nullable();
            $table->decimal('votes_rejected_perc', 8, 4)->nullable();
            $table->json('ballot')->nullable();              // senarai calon penuh
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // Satu keputusan per kerusi per tarikh — inilah yang menjadikan
            // penyegerakan boleh diulang tanpa menghasilkan pendua.
            $table->unique(['election_seat_id', 'tarikh']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_seat_results');
        Schema::dropIfExists('election_seats');
    }
};
