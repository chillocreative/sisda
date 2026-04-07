<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tujuan_sumbangan')->truncate();

        $items = [
            'Asnaf / Keluarga Miskin',
            'Kemasukan IPT (Universiti / Kolej / Pusat Pembelajaran)',
            'Masalah Kesihatan / Perubatan',
            'Bencana (Banjir / Ribut / Kebakaran)',
            'Kemalangan',
            'Kematian',
            'Hilang Punca Pendapatan',
            'Warga Emas',
            'Orang Kurang Upaya (OKU)',
            'Ibu Tunggal',
            'Lain-lain',
        ];

        foreach ($items as $index => $nama) {
            DB::table('tujuan_sumbangan')->insert([
                'nama' => $nama,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tujuan_sumbangan')->truncate();

        $items = [
            'Asnaf / Keluarga Miskin',
            'Kelahiran Bayi',
            'Kemalangan Jalan Raya',
            'Banjir',
            'Kematian',
            'Rumah Terbakar',
            'Ribut',
        ];

        foreach ($items as $index => $nama) {
            DB::table('tujuan_sumbangan')->insert([
                'nama' => $nama,
                'sort_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
