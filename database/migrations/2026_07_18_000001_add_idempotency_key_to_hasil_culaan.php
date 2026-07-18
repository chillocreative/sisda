<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-generated idempotency key for offline mobile submissions.
 *
 * Additive and reversible: one nullable column + unique index. Nothing is
 * dropped or reshaped, so this is safe against live Borang 14 / voter data
 * under the deploy's `migrate --force`.
 *
 * Nullable is deliberate — every existing row and every web submission has
 * no key. NULLs are exempt from UNIQUE in both MySQL and SQLite, so any
 * number of keyless rows coexist with the index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_culaan', function (Blueprint $table) {
            // Drop the index before the column — MySQL error 1553 otherwise.
            // See 2026_07_16_100001_reshape_borang14_forms.php.
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
