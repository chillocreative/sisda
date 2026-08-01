<?php

namespace App\Support;

use App\Models\Bandar;
use App\Models\Kadun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Loads the Borang 14 reference geography (Daerah Mengundi → Pusat Mengundi
 * → Saluran with registered-voter counts) for a given DUN.
 *
 * Two sources, in priority order:
 * 1. A curated JSON file under resources/data/borang14/kadun-{id}.json —
 *    the exact SPR-gazetted Pusat Mengundi/Saluran breakdown.
 * 2. Struktur SEBENAR daripada roll DPT — apabila fail DPPR/DPI yang dimuat
 *    naik membawa lajur `Pusat Mengundi` dan `Saluran`, pokok itu diambil
 *    terus daripadanya (`source: 'dpt_sebenar'`). Ini BUKAN anggaran:
 *    bilangan saluran setiap Pusat Mengundi dan kiraan berdaftar setiap
 *    saluran datang daripada fail SPR itu sendiri.
 * 3. Anggaran terbitan DPT — fallback apabila roll tidak membawa
 *    pusat/saluran. Baris dikumpul mengikut Daerah Mengundi + Lokaliti dan
 *    setiap Lokaliti dilayan sebagai satu Pusat Mengundi dengan SATU Saluran.
 *    Ditanda `source: 'dpt_estimate'` — pemanggil mesti memaparkan penafian.
 *
 * DUNs with neither source return null so the page can show a "data not yet
 * available" state.
 */
class Borang14Reference
{
    /**
     * Tempoh cache rujukan, dalam SAAT.
     *
     * deriveFromDpt()/deriveFromDptForBandar() mengimbas SELURUH
     * pangkalan_data_pengundi kebangsaan (UPPER(kadun)/UPPER(parlimen)
     * mematikan index) dan memuatkan setiap baris pengundi kerusi itu ke dalam
     * PHP. Papan markah AWAM meninjau setiap 4 saat, tanpa log masuk, dan
     * setiap penonton meninjau sendiri — pada malam keputusan itu bermakna
     * ribuan imbasan penuh seminit yang boleh menumbangkan kemasukan Borang 14.
     *
     * Yang dicache di sini ialah STRUKTUR rujukan sahaja (Daerah Mengundi →
     * Pusat Mengundi → Saluran + bilangan berdaftar) — data yang berubah hanya
     * apabila roll DPT dimuat naik semula atau fail JSON terkurasi ditukar,
     * iaitu berbulan sekali. ANGKA UNDI LANGSUNG TIDAK dicache: ia dibaca
     * terus daripada borang14_votes dalam ScoreboardPayload::forSeat(), jadi
     * kemasukan undi tetap muncul dalam satu tinjauan.
     *
     * 45 saat dipilih: cukup panjang untuk meruntuhkan ~11 tinjauan setiap
     * penonton kepada SATU pertanyaan, cukup pendek supaya muat naik roll DPT
     * baharu kelihatan dalam masa kurang seminit tanpa perlu membatalkan cache
     * secara eksplisit di setiap laluan muat naik.
     */
    private const CACHE_TTL = 45;

    /** @return array<string,mixed>|null */
    public static function forKadun(int $kadunId): ?array
    {
        return self::cached("dun:{$kadunId}", fn () => self::bacaKadun($kadunId));
    }

    /**
     * Struktur rujukan untuk kerusi Parlimen. daerah_mengundi.bandar_id sudah
     * menunjuk ke Parlimen secara langsung, jadi DM dikumpul terus daripada
     * pangkalan_data_pengundi tanpa join melalui kadun.
     *
     * @return array<string,mixed>|null
     */
    public static function forBandar(int $bandarId): ?array
    {
        return self::cached("parlimen:{$bandarId}", function () use ($bandarId) {
            $bandar = Bandar::with('negeri')->find($bandarId);

            return $bandar ? self::deriveFromDptForBandar($bandar) : null;
        });
    }

    public static function hasData(int $kadunId): bool
    {
        return self::forKadun($kadunId) !== null;
    }

    /** @return array<string,mixed>|null */
    private static function bacaKadun(int $kadunId): ?array
    {
        $path = resource_path("data/borang14/kadun-{$kadunId}.json");

        if (is_file($path)) {
            return json_decode(file_get_contents($path), true) ?: null;
        }

        return self::deriveFromDpt($kadunId);
    }

    /**
     * Cache::remember() menganggap null sebagai "tiada dalam cache" dan akan
     * membina semula setiap kali — tepat pada kes terburuk (kerusi tanpa roll
     * masih mengimbas jadual penuh setiap tinjauan). Bungkus hasil dalam array
     * supaya null pun tersimpan.
     *
     * @param  callable():(array<string,mixed>|null)  $bina
     * @return array<string,mixed>|null
     */
    private static function cached(string $suffix, callable $bina): ?array
    {
        $bungkus = Cache::remember(self::kunci($suffix), self::CACHE_TTL, fn () => ['ref' => $bina()]);

        return is_array($bungkus) ? ($bungkus['ref'] ?? null) : null;
    }

    private static function kunci(string $suffix): string
    {
        return 'borang14ref:v1:'.$suffix;
    }

    /**
     * Jumlah pengundi berdaftar bagi sesuatu rujukan.
     *
     * Kelas ini memulangkan DUA bentuk berbeza, dan pemanggil tidak sepatutnya
     * perlu tahu yang mana satu:
     *   - Fail JSON terkurasi : daerah_mengundi[].jumlah_berdaftar WUJUD
     *   - Terbitan DPT        : TIADA jumlah_berdaftar; kiraan berada pada
     *                           daerah_mengundi[].pusat_mengundi[].saluran[].berdaftar
     *
     * Hanya satu DUN mempunyai fail terkurasi, jadi hampir setiap kerusi
     * menggunakan bentuk kedua. Kod lama menjumlahkan bentuk pertama sahaja
     * dengan `?? 0`, lalu memaparkan "% Keluar Mengundi: 0.0%" — angka direka.
     *
     * Memulangkan null apabila rujukan langsung TIDAK membawa sebarang angka
     * berdaftar. null bermaksud "tidak diketahui" dan mesti dipaparkan sebagai
     * "—". Sifar yang dilaporkan secara jujur oleh rujukan kekal sebagai 0.
     *
     * @param  array<string,mixed>  $reference
     */
    public static function jumlahBerdaftar(array $reference): ?int
    {
        $jumlah = 0;
        $adaAngka = false;

        foreach ($reference['daerah_mengundi'] ?? [] as $dm) {
            if (array_key_exists('jumlah_berdaftar', $dm) && $dm['jumlah_berdaftar'] !== null) {
                $jumlah += (int) $dm['jumlah_berdaftar'];
                $adaAngka = true;

                continue;
            }

            foreach ($dm['pusat_mengundi'] ?? [] as $pusat) {
                foreach ($pusat['saluran'] ?? [] as $saluran) {
                    if (array_key_exists('berdaftar', $saluran) && $saluran['berdaftar'] !== null) {
                        $jumlah += (int) $saluran['berdaftar'];
                        $adaAngka = true;
                    }
                }
            }
        }

        foreach (['undi_awal', 'undi_pos'] as $kunci) {
            $bahagian = $reference[$kunci] ?? [];
            if (array_key_exists('berdaftar', $bahagian) && $bahagian['berdaftar'] !== null) {
                $jumlah += (int) $bahagian['berdaftar'];
                $adaAngka = true;
            }
        }

        return $adaAngka ? $jumlah : null;
    }

    /** @return array<string,mixed>|null */
    private static function deriveFromDpt(int $kadunId): ?array
    {
        $kadun = Kadun::with('bandar.negeri')->find($kadunId);
        if (! $kadun) {
            return null;
        }

        $bina = self::binaDaripadaRoll('kadun', $kadun->nama);
        if ($bina === null) {
            return null;
        }

        return [
            'negeri' => $kadun->bandar->negeri->nama ?? '',
            'parlimen' => $kadun->bandar->nama ?? '',
            'dun' => $kadun->nama,
            'daerah_mengundi' => $bina['daerah_mengundi'],
            'undi_awal' => ['berdaftar' => 0],
            'undi_pos' => ['berdaftar' => 0],
            'source' => $bina['source'],
        ];
    }

    /** @return array<string,mixed>|null */
    private static function deriveFromDptForBandar(Bandar $bandar): ?array
    {
        $bina = self::binaDaripadaRoll('parlimen', $bandar->nama);
        if ($bina === null) {
            return null;
        }

        return [
            'negeri' => $bandar->negeri->nama ?? '',
            'parlimen' => $bandar->nama,
            'dun' => null,
            'daerah_mengundi' => $bina['daerah_mengundi'],
            'undi_awal' => ['berdaftar' => 0],
            'undi_pos' => ['berdaftar' => 0],
            'source' => $bina['source'],
        ];
    }

    /**
     * Baca roll DPT bagi satu kerusi dan bina pokok Daerah Mengundi.
     *
     * $lajur ialah 'kadun' (DUN) atau 'parlimen'. Pengundi meninggal
     * dikecualikan, sama seperti sebelum ini.
     *
     * @return array{daerah_mengundi: array<int,array<string,mixed>>, source: string}|null
     */
    private static function binaDaripadaRoll(string $lajur, string $nama): ?array
    {
        // $lajur masuk ke dalam SQL mentah — hadkan kepada dua nilai yang
        // dibenarkan supaya ia tidak boleh menjadi laluan suntikan walaupun
        // pemanggil masa depan tersilap.
        if (! in_array($lajur, ['kadun', 'parlimen'], true)) {
            return null;
        }

        $rows = DB::table('pangkalan_data_pengundi')
            ->whereRaw('UPPER('.$lajur.') = ?', [strtoupper($nama)])
            ->where(function ($q) {
                $q->where('is_deceased', false)->orWhereNull('is_deceased');
            })
            ->select('daerah_mengundi', 'lokaliti', 'pusat_mengundi', 'saluran')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return self::strukturSebenar($rows) ?? self::strukturAnggaran($rows);
    }

    /**
     * Struktur SEBENAR daripada muat naik DPPR/DPI: DM > Pusat Mengundi >
     * Saluran, dengan `berdaftar` = kiraan pengundi hidup bagi setiap saluran.
     *
     * Memulangkan null (isyarat "guna anggaran") melainkan SETIAP baris kerusi
     * ini membawa pusat_mengundi DAN saluran yang tidak kosong.
     *
     * KENAPA SEMUA-ATAU-TIADA, termasuk untuk data CAMPURAN: pusat/saluran
     * NULL bermaksud "TIDAK DIKETAHUI", bukan "tiada saluran". Kalau kerusi
     * dengan sebahagian baris sahaja berstruktur dibina sebagai struktur
     * sebenar, pengundi selebihnya akan TERCICIR terus daripada pokok — jumlah
     * berdaftar terkurang dan tiada apa-apa pada skrin memberitahu sebabnya.
     * Mod anggaran pula mengumpul SEMUA baris mengikut Lokaliti, jadi tiada
     * seorang pun pengundi hilang. Satu kerusi = SATU mod, tidak pernah
     * bercampur, dan pilihannya deterministik.
     *
     * @param  \Illuminate\Support\Collection<int,object>  $rows
     * @return array{daerah_mengundi: array<int,array<string,mixed>>, source: string}|null
     */
    private static function strukturSebenar($rows): ?array
    {
        $grouped = [];
        foreach ($rows as $r) {
            $pusat = trim((string) ($r->pusat_mengundi ?? ''));
            $saluran = trim((string) ($r->saluran ?? ''));
            if ($pusat === '' || $saluran === '') {
                return null;
            }
            $dm = trim((string) $r->daerah_mengundi) ?: 'TIADA DAERAH MENGUNDI';
            // '01' dan '1' ialah saluran yang SAMA — tanpa normalisasi ini
            // Excel yang menulis satu daripadanya berlapik sifar menghasilkan
            // dua saluran bernombor 1 dalam pusat yang sama.
            $kunciSaluran = ctype_digit($saluran) ? (int) $saluran : $saluran;
            $grouped[$dm][$pusat][$kunciSaluran] = ($grouped[$dm][$pusat][$kunciSaluran] ?? 0) + 1;
        }

        $daerahMengundi = [];
        foreach ($grouped as $dm => $senaraiPusat) {
            $pusatMengundi = [];
            foreach ($senaraiPusat as $pusat => $kiraanSaluran) {
                uksort($kiraanSaluran, [self::class, 'bandingSaluran']);
                $saluran = [];
                foreach ($kiraanSaluran as $no => $kiraan) {
                    $saluran[] = [
                        'no' => is_numeric($no) ? (int) $no : (string) $no,
                        'berdaftar' => $kiraan,
                    ];
                }
                $pusatMengundi[] = ['nama' => (string) $pusat, 'saluran' => $saluran];
            }
            $daerahMengundi[] = ['nama' => (string) $dm, 'pusat_mengundi' => $pusatMengundi];
        }

        return ['daerah_mengundi' => $daerahMengundi, 'source' => 'dpt_sebenar'];
    }

    /**
     * Anggaran lama: kumpul mengikut DM -> Lokaliti (dilayan sebagai Pusat
     * Mengundi), satu Saluran setiap Pusat Mengundi. Kiraan baris setiap
     * kumpulan menjadi `berdaftar` Pusat Mengundi itu.
     *
     * @param  \Illuminate\Support\Collection<int,object>  $rows
     * @return array{daerah_mengundi: array<int,array<string,mixed>>, source: string}
     */
    private static function strukturAnggaran($rows): array
    {
        $grouped = [];
        foreach ($rows as $r) {
            $dm = trim((string) $r->daerah_mengundi) ?: 'TIADA DAERAH MENGUNDI';
            $lokaliti = trim((string) $r->lokaliti) ?: 'TIADA LOKALITI';
            $grouped[$dm][$lokaliti] = ($grouped[$dm][$lokaliti] ?? 0) + 1;
        }

        $daerahMengundi = [];
        foreach ($grouped as $dm => $lokalitiCounts) {
            $pusatMengundi = [];
            foreach ($lokalitiCounts as $lokaliti => $count) {
                $pusatMengundi[] = [
                    'nama' => (string) $lokaliti,
                    'saluran' => [['no' => 1, 'berdaftar' => $count]],
                ];
            }
            $daerahMengundi[] = ['nama' => (string) $dm, 'pusat_mengundi' => $pusatMengundi];
        }

        return ['daerah_mengundi' => $daerahMengundi, 'source' => 'dpt_estimate'];
    }

    /**
     * Susunan saluran mengikut NOMBOR, bukan rentetan.
     *
     * Isihan rentetan naif meletakkan '10' terus selepas '1' — pada kerusi
     * bandar dengan 10+ saluran, grid kemasukan tersusun salah dan pengendali
     * membaca baris yang salah. Saluran berangka didahulukan; label bukan
     * angka (jarang) diisih secara abjad di belakangnya.
     */
    private static function bandingSaluran(int|string $a, int|string $b): int
    {
        $aAngka = is_numeric($a);
        $bAngka = is_numeric($b);

        if ($aAngka && $bAngka) {
            return (float) $a <=> (float) $b;
        }
        if ($aAngka !== $bAngka) {
            return $aAngka ? -1 : 1;
        }

        return strcmp((string) $a, (string) $b);
    }

    /**
     * Adakah rujukan ini TERBITAN roll DPT — sama ada anggaran Lokaliti
     * ('dpt_estimate') atau struktur sebenar daripada fail DPPR/DPI
     * ('dpt_sebenar')?
     *
     * Pemanggil yang mengasingkan "rujukan terkurasi/gazet" daripada "apa yang
     * kami terbitkan sendiri daripada roll" mesti guna ini, BUKAN
     * perbandingan langsung dengan 'dpt_estimate'. Kod lama membandingkan
     * dengan satu rentetan itu sahaja; membiarkannya bermakna 'dpt_sebenar'
     * tersalah anggap sebagai JSON kurasi, lalu:
     *   - struktur scoresheet borang sendiri berhenti mengatasi terbitan DPT
     *     (kunci sel menyimpang -> setiap sel grid papar 0), dan
     *   - panel penyuntingan struktur terkunci ($asal jadi 'kurasi').
     *
     * @param  array<string,mixed>|null  $reference
     */
    public static function daripadaDpt(?array $reference): bool
    {
        return in_array($reference['source'] ?? null, ['dpt_estimate', 'dpt_sebenar'], true);
    }
}
