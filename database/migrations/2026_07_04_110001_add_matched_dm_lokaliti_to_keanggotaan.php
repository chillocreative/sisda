<?php

use App\Services\Keanggotaan\MemberMatchService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Both member tables share the cached-match columns, so add to both. */
    private array $tables = ['keanggotaan', 'keanggotaan_jawatankuasa'];

    public function up(): void
    {
        // Daerah Mengundi + Lokaliti, cached from the DPPR/DPT voter roll on the
        // IC match (alongside matched_kadun/parlimen/negeri). Reset & repopulated
        // on every sync.
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('matched_daerah_mengundi')->nullable()->after('matched_negeri');
                $t->string('matched_lokaliti')->nullable()->after('matched_daerah_mengundi');
            });
        }

        // Backfill existing members by re-running the roll cross-check (file
        // fields preserved).
        app(MemberMatchService::class)->syncTable('keanggotaan', keepFileFields: true);
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['matched_daerah_mengundi', 'matched_lokaliti']);
            });
        }
    }
};
