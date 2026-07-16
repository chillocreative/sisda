<?php
// app/Services/Pilihanraya/KawasanResolver.php
namespace App\Services\Pilihanraya;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;

/**
 * Padan kawasan dari header scoresheet; cipta jika tiada.
 * Hanya Johor (56) + Pulau Pinang (40) ada DUN — 14 negeri lain kosong, jadi
 * kebanyakan upload memerlukan penciptaan kawasan.
 *
 * Kekangan keselamatan: negeri MESTI dipadankan dengan 16 negeri sedia ada.
 * Negeri TIDAK PERNAH dicipta — itu menghalang bacaan AI tersasar mencemarkan
 * data geografi induk.
 */
class KawasanResolver
{
    public static function resolve(array $extracted): array
    {
        $negeri = Negeri::whereRaw('UPPER(nama) = ?', [mb_strtoupper(trim($extracted['negeri'] ?? ''))])->first();
        if (! $negeri) {
            return [
                'ok' => false, 'kawasan_type' => null, 'kawasan_id' => null, 'created' => [],
                'error' => 'Negeri "' . ($extracted['negeri'] ?? '') . '" tidak dikenali dalam sistem.',
            ];
        }

        $created = [];

        // Kod DM '129/15/01' -> Parlimen 129. Nama Parlimen jarang ada pada sheet.
        $parlimenKod = trim((string) ($extracted['parlimen_kod'] ?? ''));
        if ($parlimenKod === '') {
            return [
                'ok' => false, 'kawasan_type' => null, 'kawasan_id' => null, 'created' => [],
                'error' => 'Kod Parlimen tidak dapat dibaca dari scoresheet.',
            ];
        }

        $namaParlimen = 'P.' . $parlimenKod;   // placeholder — jangan teka nama sebenar
        $bandar = Bandar::where('negeri_id', $negeri->id)
            ->whereRaw('UPPER(nama) = ?', [mb_strtoupper($namaParlimen)])
            ->first();

        if (! $bandar) {
            $bandar = Bandar::create(['nama' => $namaParlimen, 'negeri_id' => $negeri->id]);
            $created[] = ['jenis' => 'parlimen', 'nama' => $namaParlimen];
        }

        $namaDun = trim((string) ($extracted['kawasan_nama'] ?? ''));
        if ($namaDun === '') {
            return [
                'ok' => false, 'kawasan_type' => null, 'kawasan_id' => null, 'created' => $created,
                'error' => 'Nama kawasan tidak dapat dibaca dari scoresheet.',
            ];
        }

        $kadun = Kadun::where('bandar_id', $bandar->id)
            ->whereRaw('UPPER(nama) = ?', [mb_strtoupper($namaDun)])
            ->first();

        if (! $kadun) {
            $kadun = Kadun::create([
                'nama' => $namaDun,
                'kod_dun' => $extracted['kawasan_kod'] ?? null,
                'bandar_id' => $bandar->id,
            ]);
            $created[] = ['jenis' => 'dun', 'nama' => $namaDun];
        }

        return [
            'ok' => true,
            'kawasan_type' => Borang14Form::KAWASAN_DUN,
            'kawasan_id' => $kadun->id,
            'created' => $created,
            'error' => null,
        ];
    }
}
