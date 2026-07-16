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

        ['negeri' => $negeri, 'kawasanType' => $kawasanType, 'parlimenKod' => $parlimenKod, 'namaKawasan' => $namaKawasan] = $check;
        $kawasanKod = $extracted['kawasan_kod'] ?? null;

        if ($dryRun) {
            return self::match($negeri, $kawasanType, $parlimenKod, $namaKawasan, $kawasanKod, false);
        }

        // Semua pengesahan input lulus — sekarang selamat untuk menulis. Bungkus
        // dalam transaksi supaya kegagalan Kadun::create() tidak meninggalkan
        // Bandar anak-yatim yang baru dicipta.
        return DB::transaction(fn () => self::match($negeri, $kawasanType, $parlimenKod, $namaKawasan, $kawasanKod, true));
    }

    /** Pengesahan input — SAMA untuk dry run & commit. Tidak menyentuh DB tulis. */
    private static function validate(array $extracted): array
    {
        $negeri = Negeri::whereRaw('UPPER(nama) = ?', [mb_strtoupper(trim($extracted['negeri'] ?? ''))])->first();
        if (! $negeri) {
            return ['ok' => false, 'fail' => self::fail('Negeri "' . ($extracted['negeri'] ?? '') . '" tidak dikenali dalam sistem.')];
        }

        // Header SPR bezakan aras kerusi melalui awalan kawasan_kod:
        // "N.15 JUASSEH" -> DUN, "P.129 ..." -> Parlimen. Upload sebelum ini
        // sentiasa menulis DUN tanpa mengira aras sebenar sheet — kini kod
        // awalan itu sendiri yang menentukan, dan sheet yang tidak jelas
        // ditolak dengan mesej jelas dan BUKAN diandaikan DUN secara senyap.
        $kawasanKod = trim((string) ($extracted['kawasan_kod'] ?? ''));
        $kawasanKodUpper = mb_strtoupper($kawasanKod);
        $kawasanType = match (true) {
            str_starts_with($kawasanKodUpper, 'N.') => Borang14Form::KAWASAN_DUN,
            str_starts_with($kawasanKodUpper, 'P.') => Borang14Form::KAWASAN_PARLIMEN,
            default => null,
        };
        if ($kawasanType === null) {
            return ['ok' => false, 'fail' => self::fail(
                'Kod kawasan "' . ($extracted['kawasan_kod'] ?? '') . '" tidak dikenali daripada scoresheet — '.
                'dijangka bermula dengan "N." (DUN) atau "P." (Parlimen). Sila semak fail atau baca semula.'
            )];
        }

        // Kod DM '129/15/01' -> Parlimen 129. Nama Parlimen jarang ada pada sheet.
        $parlimenKod = trim((string) ($extracted['parlimen_kod'] ?? ''));
        if ($parlimenKod === '') {
            return ['ok' => false, 'fail' => self::fail('Kod Parlimen tidak dapat dibaca dari scoresheet.')];
        }

        // Nama kerusi yang sedang direkodkan: nama DUN untuk sheet DUN, ATAU
        // nama Parlimen itu sendiri untuk sheet Parlimen (tiada DUN di bawahnya).
        $namaKawasan = trim((string) ($extracted['kawasan_nama'] ?? ''));
        if ($namaKawasan === '') {
            return ['ok' => false, 'fail' => self::fail('Nama kawasan tidak dapat dibaca dari scoresheet.')];
        }

        return ['ok' => true, 'negeri' => $negeri, 'kawasanType' => $kawasanType, 'parlimenKod' => $parlimenKod, 'namaKawasan' => $namaKawasan];
    }

    /**
     * Padan (atau, jika $write, cipta) Bandar/Kadun. Apabila $write === false,
     * tiada baris ditulis — jika Bandar tiada, andaikan Kadun di bawahnya juga
     * tiada (kadun bergantung kepada bandar_id) tanpa membuat pertanyaan sia-sia.
     */
    private static function match(Negeri $negeri, string $kawasanType, string $parlimenKod, string $namaKawasan, ?string $kawasanKod, bool $write): array
    {
        $created = [];

        // Padan Parlimen pada KOD, bukan nama placeholder — bandar SUDAH disemai
        // untuk sesetengah negeri (cth Johor 56 DUN, Pulau Pinang 40 DUN) di bawah
        // NAMA SEBENAR ("Kluang", dsb.). Padanan nama placeholder 'P.<kod>' tidak
        // akan pernah sepadan dengan nama sebenar itu, jadi ia mesti dipadankan
        // melalui kod_parlimen (dilingkupkan kepada negeri yang sudah dipadan).
        // Konvensyen kod_parlimen sedia ada (lihat seeder Johor/Penang) ialah
        // 'P' + nombor TANPA titik (cth 'P160') — parlimen_kod hasil ekstrak
        // pula nombor bare sahaja ('160'), jadi digabung dahulu sebelum padan.
        $kodParlimenPenuh = 'P' . $parlimenKod;
        $bandar = Bandar::where('negeri_id', $negeri->id)
            ->whereRaw('UPPER(kod_parlimen) = ?', [mb_strtoupper($kodParlimenPenuh)])
            ->first();

        if (! $bandar) {
            // Hanya kembali kepada mencipta placeholder 'P.<kod>' apabila BENAR-BENAR
            // tiada baris dengan kod ini — bukan tekaan pertama.
            $namaPlaceholder = 'P.' . $parlimenKod;
            $created[] = ['jenis' => 'parlimen', 'nama' => $namaPlaceholder];
            if ($write) {
                $bandar = Bandar::create([
                    'nama' => $namaPlaceholder,
                    'kod_parlimen' => $kodParlimenPenuh,
                    'negeri_id' => $negeri->id,
                ]);
            }
        }

        // Sheet Parlimen (tiada DUN di bawahnya) — selesai di sini.
        if ($kawasanType === Borang14Form::KAWASAN_PARLIMEN) {
            return [
                'ok' => true,
                'kawasan_type' => Borang14Form::KAWASAN_PARLIMEN,
                'kawasan_id' => $bandar?->id,
                'created' => $created,
                'error' => null,
            ];
        }

        $kadun = $bandar
            ? Kadun::where('bandar_id', $bandar->id)->whereRaw('UPPER(nama) = ?', [mb_strtoupper($namaKawasan)])->first()
            : null;

        if (! $kadun) {
            $created[] = ['jenis' => 'dun', 'nama' => $namaKawasan];
            if ($write) {
                $kadun = Kadun::create([
                    'nama' => $namaKawasan,
                    'kod_dun' => $kawasanKod,
                    'bandar_id' => $bandar->id,
                ]);
            }
        }

        return [
            'ok' => true,
            'kawasan_type' => Borang14Form::KAWASAN_DUN,
            'kawasan_id' => $kadun?->id,
            'created' => $created,
            'error' => null,
        ];
    }

    private static function fail(string $error): array
    {
        return ['ok' => false, 'kawasan_type' => null, 'kawasan_id' => null, 'created' => [], 'error' => $error];
    }
}
