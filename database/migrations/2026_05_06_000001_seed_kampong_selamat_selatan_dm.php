<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bandar = DB::table('bandar')->whereRaw('UPPER(nama) = ?', ['KEPALA BATAS'])->first();
        if (! $bandar) {
            return;
        }

        $dmName = 'KAMPONG SELAMAT SELATAN';
        $kodDm = '041/03/09';

        $existingDm = DB::table('daerah_mengundi')
            ->where('bandar_id', $bandar->id)
            ->whereRaw('UPPER(nama) = ?', [$dmName])
            ->first();

        if ($existingDm) {
            $dmId = $existingDm->id;
        } else {
            $kodDmInUse = DB::table('daerah_mengundi')->where('kod_dm', $kodDm)->exists();
            if ($kodDmInUse) {
                $kodDm = $this->nextAvailableKodDm($bandar->id, '041/03/');
            }
            $dmId = DB::table('daerah_mengundi')->insertGetId([
                'kod_dm' => $kodDm,
                'nama' => $dmName,
                'bandar_id' => $bandar->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('lokaliti')
            ->whereRaw('UPPER(nama) = ?', ['KG SELAMAT SELATAN'])
            ->whereNull('daerah_mengundi_id')
            ->update([
                'daerah_mengundi_id' => $dmId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $bandar = DB::table('bandar')->whereRaw('UPPER(nama) = ?', ['KEPALA BATAS'])->first();
        if (! $bandar) {
            return;
        }

        $dm = DB::table('daerah_mengundi')
            ->where('bandar_id', $bandar->id)
            ->whereRaw('UPPER(nama) = ?', ['KAMPONG SELAMAT SELATAN'])
            ->first();

        if (! $dm) {
            return;
        }

        DB::table('lokaliti')
            ->where('daerah_mengundi_id', $dm->id)
            ->update(['daerah_mengundi_id' => null]);

        DB::table('daerah_mengundi')->where('id', $dm->id)->delete();
    }

    private function nextAvailableKodDm(int $bandarId, string $prefix): string
    {
        $existing = DB::table('daerah_mengundi')
            ->where('bandar_id', $bandarId)
            ->where('kod_dm', 'like', $prefix . '%')
            ->pluck('kod_dm');

        $maxSeq = 0;
        foreach ($existing as $kod) {
            $tail = substr($kod, strlen($prefix));
            if (ctype_digit($tail) && (int) $tail > $maxSeq) {
                $maxSeq = (int) $tail;
            }
        }

        return $prefix . str_pad((string) ($maxSeq + 1), 2, '0', STR_PAD_LEFT);
    }
};
