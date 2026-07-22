<?php

namespace App\Services\Pilihanraya;

use App\Models\Bandar;
use App\Models\ElectionDataSetting;
use App\Models\ElectionSeat;
use App\Models\Kadun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien untuk electiondata.my — keputusan rasmi SPR bagi setiap kerusi Malaysia
 * dari 1954 hingga kini (CC0, percuma).
 *
 * Mengikut kontrak ClaudeService: kaedah ini TIDAK PERNAH melontar. API yang
 * mati mesti merosot menjadi "tiada garis dasar tersedia" pada satu kad, bukan
 * memecahkan War Room. Setiap kegagalan memulangkan array kosong / null dan
 * dicatat, bukan dilontar ke lapisan HTTP.
 *
 * Kunci API disimpan dalam pangkalan data (disulitkan), bukan .env — sama
 * seperti ClaudeSetting.
 */
class ElectionDataService
{
    private const BASE = 'https://api.electiondata.my';

    /** Sekatan kadar tidak diterbitkan; had ini melindungi kita, bukan mereka. */
    private const TIMEOUT = 20;

    public function isConfigured(): bool
    {
        $s = ElectionDataSetting::current();

        return $s !== null && $s->is_active && ! empty($s->api_key);
    }

    /**
     * Senarai penuh kerusi (222 Parlimen + 600 DUN).
     *
     * @return array<int, array{seat:string, slug:string, type:string}>
     */
    public function seats(): array
    {
        return $this->get('/v1/seats/dropdown', [], 'seats') ?? [];
    }

    /**
     * Keputusan setiap pilihan raya bagi satu kerusi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function seatResults(string $slug): array
    {
        return $this->get('/v1/seats/results', ['slug' => $slug, 'lineage' => 'true'], 'results') ?? [];
    }

    /**
     * Pecahan undi penuh (setiap calon) bagi satu kerusi pada satu tarikh.
     *
     * @return array<string, mixed>|null
     */
    public function ballot(string $seat, string $state, string $date): ?array
    {
        $res = $this->get('/v1/results', ['seat' => $seat, 'state' => $state, 'date' => $date]);

        return is_array($res) ? $res : null;
    }

    /**
     * Slug electiondata.my bagi satu kawasan SISDA.
     *
     * Dua laluan, mengikut urutan keyakinan:
     *   1. Kod berbilang ('N15'/'P129') + nama + negeri -> slug binaan. Kod ini
     *      diisi oleh StateElectoralSeeder bagi Johor, N9 dan Pulau Pinang, jadi
     *      padanan di sana bersifat deterministik.
     *   2. Padanan nama + negeri terhadap election_seats yang telah disegerakkan,
     *      menggunakan perbandingan huruf besar-dipangkas yang sama seperti
     *      seluruh sistem (nameKey) — negeri SISDA lain tiada kod.
     *
     * Memulangkan null apabila tiada padanan pasti. Meneka kerusi yang salah
     * lebih buruk daripada tiada garis dasar: ia akan menunjukkan keputusan
     * kerusi ORANG LAIN sebagai keputusan kerusi ini.
     */
    public function slugFor(Kadun|Bandar $kawasan): ?string
    {
        $isDun = $kawasan instanceof Kadun;
        $negeri = $isDun ? $kawasan->bandar?->negeri?->nama : $kawasan->negeri?->nama;
        $kod = $isDun ? $kawasan->kod_dun : $kawasan->kod_parlimen;

        if ($negeri && $kod && $kawasan->nama) {
            $slug = self::slugify($kod.' '.$kawasan->nama.' '.$negeri);
            if (ElectionSeat::where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        if (! $negeri || ! $kawasan->nama) {
            return null;
        }

        // Sandaran: padan mengikut nama + negeri + jenis. Nama kerusi unik dalam
        // satu negeri bagi satu aras, jadi padanan berbilang bermakna data
        // bercanggah — pulangkan null dan bukannya memilih satu secara rambang.
        $matches = ElectionSeat::query()
            ->where('jenis', $isDun ? ElectionSeat::JENIS_DUN : ElectionSeat::JENIS_PARLIMEN)
            ->whereRaw('UPPER(TRIM(negeri)) = ?', [self::nameKey($negeri)])
            ->whereRaw('UPPER(TRIM(nama)) = ?', [self::nameKey($kawasan->nama)])
            ->pluck('slug');

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** Konvensyen padanan rentetan yang sama seperti seluruh sistem. */
    public static function nameKey(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    /** 'N15' + 'Juasseh' + 'Negeri Sembilan' -> 'n15-juasseh-negeri-sembilan' */
    public static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim((string) $value, '-');
    }

    /**
     * Satu permintaan GET. Memulangkan null pada SEBARANG kegagalan — pemanggil
     * merosot dengan anggun dan tidak pernah melihat pengecualian.
     */
    /**
     * @param  string|null  $unwrap  Kunci pembungkus yang dijangka pada respons.
     *
     * API membungkus setiap senarai di bawah kunci bernama — /v1/seats/dropdown
     * memulangkan {"seats": [...]}, /v1/seats/results memulangkan
     * {"results": [...]}. Kunci itu DINYATAKAN oleh pemanggil, bukan diteka:
     * pembungkus yang hilang bermakna bentuk API telah berubah, dan itu mesti
     * BISING. Pernah sekali ia senyap — kod menganggap array telanjang, is_array()
     * lulus ke atas pembungkus, tiada apa dicatat, dan sistem melaporkan "tiada
     * kerusi" selama berbulan-bulan sedangkan API memulangkan 800+ kerusi dengan
     * sempurna. Jangan sekali-kali pulangkan array pembungkus itu sendiri.
     */
    private function get(string $path, array $query = [], ?string $unwrap = null): ?array
    {
        $setting = ElectionDataSetting::current();
        if (! $setting || ! $setting->is_active || empty($setting->api_key)) {
            return null;
        }

        try {
            $res = Http::timeout(self::TIMEOUT)
                ->retry(2, 500, throw: false)
                ->withToken($setting->api_key)
                ->acceptJson()
                ->get(self::BASE.$path, $query);

            if (! $res->successful()) {
                Log::warning('electiondata.my request failed', [
                    'path' => $path, 'status' => $res->status(),
                ]);

                return null;
            }

            $json = $res->json();

            if (! is_array($json)) {
                return null;
            }

            if ($unwrap !== null) {
                if (! array_key_exists($unwrap, $json) || ! is_array($json[$unwrap])) {
                    Log::warning('electiondata.my response shape changed', [
                        'path' => $path,
                        'dijangka' => $unwrap,
                        'kunci_diterima' => array_keys($json),
                    ]);

                    return null;
                }

                return $json[$unwrap];
            }

            return $json;
        } catch (\Throwable $e) {
            Log::warning('electiondata.my request threw', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
