<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Merge & deduplikasi master data "Tujuan Sumbangan" + "Jenis Sumbangan".
 *
 * Bahagian A sahaja: kemas kini senarai master (global, bandar_id = NULL,
 * berkuatkuasa untuk semua Bandar di seluruh Malaysia). Pemetaan semula
 * rekod hasil_culaan (Bahagian B) dilakukan kemudian selepas data pengundi
 * dimuat naik & disync dalam SISDA.
 *
 * tujuan_sumbangan = SEBAB bantuan diberi (profil penerima)
 * jenis_sumbangan  = BENTUK bantuan diberi
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============ TUJUAN SUMBANGAN ============

        // Luaskan "Kemasukan IPT..." → Pendidikan / Persekolahan (Sekolah / IPT)
        DB::table('tujuan_sumbangan')
            ->where('nama', 'Kemasukan IPT (Universiti / Kolej / Pusat Pembelajaran)')
            ->update([
                'nama' => 'Pendidikan / Persekolahan (Sekolah / IPT)',
                'updated_at' => now(),
            ]);

        // Susunan entri sedia ada + tolak "Lain-lain" ke hujung
        $tujuanOrder = [
            'Asnaf / Keluarga Miskin' => 1,
            'Pendidikan / Persekolahan (Sekolah / IPT)' => 2,
            'Masalah Kesihatan / Perubatan' => 3,
            'Bencana (Banjir / Ribut / Kebakaran)' => 4,
            'Kemalangan' => 5,
            'Kematian' => 6,
            'Hilang Punca Pendapatan' => 7,
            'Warga Emas' => 8,
            'Orang Kurang Upaya (OKU)' => 9,
            'Ibu Tunggal' => 10,
            'Lain-lain' => 99,
        ];
        foreach ($tujuanOrder as $nama => $order) {
            DB::table('tujuan_sumbangan')
                ->where('nama', $nama)
                ->update(['sort_order' => $order, 'updated_at' => now()]);
        }

        // Entri BARU (global)
        $newTujuan = [
            'Kebajikan / Perbelanjaan Harian' => 11,
            'Modal / Bantuan Perniagaan' => 12,
            'Atlet / Sukan' => 13,
        ];
        foreach ($newTujuan as $nama => $order) {
            DB::table('tujuan_sumbangan')->updateOrInsert(
                ['nama' => $nama, 'bandar_id' => null],
                ['sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // ============ JENIS SUMBANGAN ============

        // Susunan entri sedia ada + tolak "Lain-lain" / "Tiada" ke hujung
        $jenisOrder = [
            'Wang Tunai' => 1,
            'Hamper Barangan Keperluan Dapur' => 2,
            'Hamper Perayaan' => 3,
            'Lain-lain' => 98,
            'Tiada' => 99,
        ];
        foreach ($jenisOrder as $nama => $order) {
            DB::table('jenis_sumbangan')
                ->where('nama', $nama)
                ->update(['sort_order' => $order, 'updated_at' => now()]);
        }

        // Entri BARU (bentuk bukan-tunai, global)
        $newJenis = [
            'Subsidi Tong Gas' => 4,
            'Peralatan / Barangan Perubatan' => 5,
            'Peralatan Perniagaan' => 6,
            'Bayaran Bil / Tunggakan' => 7,
            'Peralatan Pendidikan' => 8,
            'Barangan Ilmiah / Keagamaan' => 9,
        ];
        foreach ($newJenis as $nama => $order) {
            DB::table('jenis_sumbangan')->updateOrInsert(
                ['nama' => $nama, 'bandar_id' => null],
                ['sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // Buang entri baru tujuan
        DB::table('tujuan_sumbangan')->whereIn('nama', [
            'Kebajikan / Perbelanjaan Harian',
            'Modal / Bantuan Perniagaan',
            'Atlet / Sukan',
        ])->delete();

        // Buang entri baru jenis
        DB::table('jenis_sumbangan')->whereIn('nama', [
            'Subsidi Tong Gas',
            'Peralatan / Barangan Perubatan',
            'Peralatan Perniagaan',
            'Bayaran Bil / Tunggakan',
            'Peralatan Pendidikan',
            'Barangan Ilmiah / Keagamaan',
        ])->delete();

        // Pulihkan label IPT asal
        DB::table('tujuan_sumbangan')
            ->where('nama', 'Pendidikan / Persekolahan (Sekolah / IPT)')
            ->update([
                'nama' => 'Kemasukan IPT (Universiti / Kolej / Pusat Pembelajaran)',
                'updated_at' => now(),
            ]);
    }
};
