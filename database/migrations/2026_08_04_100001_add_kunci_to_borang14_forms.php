<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci Borang 14 — pembekuan rekod yang sudah SIAP.
 *
 * Status ('draft' / 'published') menjawab soalan "adakah keputusan ini
 * disiarkan"; ia TIDAK menjawab "bolehkah ia disunting lagi". Borang draf yang
 * sudah siap dikira masih terbuka sepenuhnya, dan borang diterbitkan pun boleh
 * dinyahterbit lalu disunting semula. Kunci ialah paksi KEDUA yang berasingan:
 * sesiapa pun (termasuk pemilik kerusi) tidak boleh menulis pada borang yang
 * berkunci sehingga Super Admin/Admin membukanya semula.
 *
 * `locked_at` ialah SATU-SATUNYA sumber kebenaran — null bermakna terbuka.
 * `locked_by` semata-mata jejak audit (siapa mengunci), nullOnDelete supaya
 * memadam akaun tidak pernah membuka kunci rekod secara senyap.
 *
 * Tambahan TULEN (dua lajur nullable) — tiada penukaran bentuk jadual, jadi
 * perangkap MySQL 1553 yang didokumenkan dalam
 * 2026_07_16_100001_reshape_borang14_forms.php tidak terpakai di sini. Migrasi
 * berjalan pada setiap deploy, jadi kedua-dua arah berkunci pada kewujudan
 * lajur: larian separa mesti MENYAMBUNG, bukan meletup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('borang14_forms', 'locked_at')) {
            return;
        }

        Schema::table('borang14_forms', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('published_at');
            $table->foreignId('locked_by')->nullable()->after('locked_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Boleh dipatah balik: kedua-dua lajur ialah METADATA kunci sahaja — tiada
     * satu undi, struktur atau pemetaan parti pun hidup di dalamnya, jadi
     * menggugurkannya tidak menghilangkan data pilihan raya. Yang hilang hanya
     * keadaan kunci itu sendiri, dan rekod kembali kepada "terbuka" — keadaan
     * yang SAMA seperti sebelum ciri ini wujud.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('borang14_forms', 'locked_at')) {
            return;
        }

        Schema::table('borang14_forms', function (Blueprint $table) {
            // Gugur FK dahulu, kemudian lajur — pada MySQL index sokongan FK
            // menghalang padaman lajur secara terus (ralat 1553).
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn('locked_at');
        });
    }
};
