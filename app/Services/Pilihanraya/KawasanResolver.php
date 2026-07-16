<?php
// app/Services/Pilihanraya/KawasanResolver.php
namespace App\Services\Pilihanraya;

use App\Models\Bandar;
use App\Models\Borang14Form;
use App\Models\Kadun;
use App\Models\Negeri;
use Illuminate\Support\Facades\DB;

/**
 * Padan kawasan dari header scoresheet; cipta jika tiada.
 * Hanya Johor (56) + Pulau Pinang (40) ada DUN — 14 negeri lain kosong, jadi
 * kebanyakan upload memerlukan penciptaan kawasan.
 *
 * Kekangan keselamatan: negeri MESTI dipadankan dengan 16 negeri sedia ada.
 * Negeri TIDAK PERNAH dicipta — itu menghalang bacaan AI tersasar mencemarkan
 * data geografi induk.
 *
 * Mod dryRun: jalankan LOGIK PADANAN & PENGESAHAN YANG SAMA tetapi jangan tulis
 * apa-apa — memulangkan apa yang AKAN dicipta supaya pemanggil boleh minta
 * pengesahan pengguna dahulu. Kedua-dua laluan (dry run & commit) berkongsi
 * fungsi validate()/match() supaya tidak menyimpang (apa yang commit tolak,
 * dry run juga mesti tolak — sama tertib, sama semakan).
 */
class KawasanResolver
{
    public static function resolve(array $extracted, bool $dryRun = false): array
    {
        $check = self::validate($extracted);
        if (! $check['ok']) {
            return $check['fail'];
        }

        ['negeri' => $negeri, 'parlimenKod' => $parlimenKod, 'namaDun' => $namaDun] = $check;
        $kawasanKod = $extracted['kawasan_kod'] ?? null;

        if ($dryRun) {
            return self::match($negeri, $parlimenKod, $namaDun, $kawasanKod, false);
        }

        // Semua pengesahan input lulus — sekarang selamat untuk menulis. Bungkus
        // dalam transaksi supaya kegagalan Kadun::create() tidak meninggalkan
        // Bandar anak-yatim yang baru dicipta.
        return DB::transaction(fn () => self::match($negeri, $parlimenKod, $namaDun, $kawasanKod, true));
    }

    /** Pengesahan input — SAMA untuk dry run & commit. Tidak menyentuh DB tulis. */
    private static function validate(array $extracted): array
    {
        $negeri = Negeri::whereRaw('UPPER(nama) = ?', [mb_strtoupper(trim($extracted['negeri'] ?? ''))])->first();
        if (! $negeri) {
            return ['ok' => false, 'fail' => self::fail('Negeri "' . ($extracted['negeri'] ?? '') . '" tidak dikenali dalam sistem.')];
        }

        // Kod DM '129/15/01' -> Parlimen 129. Nama Parlimen jarang ada pada sheet.
        $parlimenKod = trim((string) ($extracted['parlimen_kod'] ?? ''));
        if ($parlimenKod === '') {
            return ['ok' => false, 'fail' => self::fail('Kod Parlimen tidak dapat dibaca dari scoresheet.')];
        }

        $namaDun = trim((string) ($extracted['kawasan_nama'] ?? ''));
        if ($namaDun === '') {
            return ['ok' => false, 'fail' => self::fail('Nama kawasan tidak dapat dibaca dari scoresheet.')];
        }

        return ['ok' => true, 'negeri' => $negeri, 'parlimenKod' => $parlimenKod, 'namaDun' => $namaDun];
    }

    /**
     * Padan (atau, jika $write, cipta) Bandar/Kadun. Apabila $write === false,
     * tiada baris ditulis — jika Bandar tiada, andaikan Kadun di bawahnya juga
     * tiada (kadun bergantung kepada bandar_id) tanpa membuat pertanyaan sia-sia.
     */
    private static function match(Negeri $negeri, string $parlimenKod, string $namaDun, ?string $kawasanKod, bool $write): array
    {
        $created = [];
        $namaParlimen = 'P.' . $parlimenKod;   // placeholder — jangan teka nama sebenar

        $bandar = Bandar::where('negeri_id', $negeri->id)
            ->whereRaw('UPPER(nama) = ?', [mb_strtoupper($namaParlimen)])
            ->first();

        if (! $bandar) {
            $created[] = ['jenis' => 'parlimen', 'nama' => $namaParlimen];
            if ($write) {
                $bandar = Bandar::create([
                    'nama' => $namaParlimen,
                    'kod_parlimen' => $parlimenKod,
                    'negeri_id' => $negeri->id,
                ]);
            }
        }

        $kadun = $bandar
            ? Kadun::where('bandar_id', $bandar->id)->whereRaw('UPPER(nama) = ?', [mb_strtoupper($namaDun)])->first()
            : null;

        if (! $kadun) {
            $created[] = ['jenis' => 'dun', 'nama' => $namaDun];
            if ($write) {
                $kadun = Kadun::create([
                    'nama' => $namaDun,
                    'kod_dun' => $kawasanKod,
                    'bandar_id' => $bandar->id,
                ]);
            }
        }

        return [
            'ok' => true,
            'kawasan_type' => Borang14Form::KAWASAN_DUN,
            'kawasan_id' => $kadun->id ?? null,
            'created' => $created,
            'error' => null,
        ];
    }

    private static function fail(string $error): array
    {
        return ['ok' => false, 'kawasan_type' => null, 'kawasan_id' => null, 'created' => [], 'error' => $error];
    }
}
