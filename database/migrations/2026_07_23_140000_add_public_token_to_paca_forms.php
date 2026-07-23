<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pautan awam PACA berpindah daripada per-Pusat Mengundi kepada per-DUN:
 * satu pautan bagi satu kerusi memaparkan SEMUA Pusat/Saluran/slot. Token
 * kini pada paca_forms.
 *
 * paca_pusat.public_token dibiarkan (tidak digugurkan — elak risiko error
 * 1553 pada data langsung); ia hanya tidak lagi digunakan oleh laluan awam.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tambah dahulu sebagai nullable, isi baris sedia ada, KEMUDIAN jadikan
        // unik — supaya baris lama tidak melanggar kekangan unik.
        Schema::table('paca_forms', function (Blueprint $t) {
            $t->string('public_token', 64)->nullable()->after('borang14_form_id');
        });

        foreach (DB::table('paca_forms')->whereNull('public_token')->pluck('id') as $id) {
            DB::table('paca_forms')->where('id', $id)->update(['public_token' => Str::random(32)]);
        }

        Schema::table('paca_forms', function (Blueprint $t) {
            $t->unique('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('paca_forms', function (Blueprint $t) {
            $t->dropUnique(['public_token']);
            $t->dropColumn('public_token');
        });
    }
};
