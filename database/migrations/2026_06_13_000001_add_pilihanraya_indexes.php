<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->index('no_ic', 'data_pengundi_no_ic_index');
            $table->index(['kadun', 'voter_color'], 'data_pengundi_kadun_voter_color_index');
        });

        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->index(['kadun', 'voter_color'], 'hasil_culaan_kadun_voter_color_index');
        });

        Schema::table('pangkalan_data_pengundi', function (Blueprint $table) {
            $table->index(['upload_batch_id', 'kadun'], 'pdp_batch_kadun_index');
            $table->index(['upload_batch_id', 'parlimen'], 'pdp_batch_parlimen_index');
        });
    }

    public function down(): void
    {
        Schema::table('data_pengundi', function (Blueprint $table) {
            $table->dropIndex('data_pengundi_no_ic_index');
            $table->dropIndex('data_pengundi_kadun_voter_color_index');
        });

        Schema::table('hasil_culaan', function (Blueprint $table) {
            $table->dropIndex('hasil_culaan_kadun_voter_color_index');
        });

        Schema::table('pangkalan_data_pengundi', function (Blueprint $table) {
            $table->dropIndex('pdp_batch_kadun_index');
            $table->dropIndex('pdp_batch_parlimen_index');
        });
    }
};
