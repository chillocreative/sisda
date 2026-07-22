<?php

namespace App\Console\Commands;

use App\Models\Bandar;
use App\Models\ElectionDataSetting;
use App\Models\ElectionSeat;
use App\Models\ElectionSeatResult;
use App\Models\Kadun;
use App\Services\Pilihanraya\ElectionDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Segerakkan keputusan rasmi SPR daripada electiondata.my ke dalam SISDA.
 *
 * SENGAJA TIDAK dimasukkan ke dalam deploy.yml: 822 panggilan keluar pada setiap
 * push adalah salah. Jalankan secara manual selepas persediaan pertama, kemudian
 * sekali selepas setiap pilihan raya.
 *
 * Boleh diulang: setiap tulisan menggunakan updateOrCreate berkunci pada slug
 * (kerusi) dan (kerusi, tarikh) (keputusan), jadi menjalankannya dua kali tidak
 * menghasilkan pendua.
 */
class SyncElectionDataCommand extends Command
{
    protected $signature = 'pilihanraya:sync-electiondata
                            {--state= : Hadkan kepada satu negeri}
                            {--slug= : Segerakkan satu kerusi sahaja}
                            {--dry-run : Laporkan sahaja; jangan tulis apa-apa}';

    protected $description = 'Segerakkan kerusi dan keputusan rasmi SPR daripada electiondata.my';

    public function handle(ElectionDataService $api): int
    {
        if (! $api->isConfigured()) {
            $this->error('API electiondata.my belum dikonfigurasi. Tetapkan kunci di /settings/election-data.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $state = $this->option('state');
        $slugOnly = $this->option('slug');

        $seats = collect($api->seats())
            ->filter(fn ($s) => is_array($s) && ! empty($s['slug']))
            ->when($slugOnly, fn ($c) => $c->where('slug', $slugOnly))
            ->when($state, fn ($c) => $c->filter(
                fn ($s) => ElectionDataService::nameKey(self::negeriOf($s)) === ElectionDataService::nameKey($state)
            ))
            ->values();

        if ($seats->isEmpty()) {
            $this->warn('Tiada kerusi sepadan dengan penapis itu (atau API tidak memulangkan apa-apa).');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Memproses {$seats->count()} kerusi…");

        $stats = ['kerusi' => 0, 'keputusan' => 0, 'tidak_dipadan' => 0];
        $bar = $this->output->createProgressBar($seats->count());

        foreach ($seats as $seat) {
            $nama = self::namaOf($seat);
            $negeri = self::negeriOf($seat);
            $jenis = ($seat['type'] ?? '') === 'parlimen' ? ElectionSeat::JENIS_PARLIMEN : ElectionSeat::JENIS_DUN;
            $kod = self::kodFromSlug($seat['slug']);

            $link = $this->matchKawasan($jenis, $nama, $negeri, $kod);
            if ($link['kadun_id'] === null && $link['bandar_id'] === null) {
                $stats['tidak_dipadan']++;
            }

            $results = $api->seatResults($seat['slug']);

            // Pecahan undi penuh (setiap calon) datang dari titik akhir BERBEZA,
            // satu panggilan per kerusi per tarikh. Mengambilnya bagi SETIAP
            // pilihan raya bermakna ~12,000 panggilan; hanya keputusan LENGKAP
            // TERKINI yang dipaparkan sebagai garis dasar, jadi hanya itu yang
            // diambil. Selebihnya menyimpan ringkasan pemenang sahaja.
            $terkini = collect($results)
                ->filter(fn ($r) => is_array($r) && ! empty($r['party']) && ! empty($r['date'] ?? $r['tarikh'] ?? null))
                ->sortByDesc(fn ($r) => $r['date'] ?? $r['tarikh'])
                ->first();

            $ballotFor = null;
            if ($terkini && ! $dryRun) {
                $penuh = $api->ballot($nama, $negeri, (string) ($terkini['date'] ?? $terkini['tarikh']));
                if (is_array($penuh)) {
                    $ballotFor = [
                        'tarikh' => (string) ($terkini['date'] ?? $terkini['tarikh']),
                        'ballot' => $penuh['ballot'] ?? null,
                        'stats' => $penuh['stats'] ?? null,
                    ];
                }
            }

            if ($dryRun) {
                $stats['kerusi']++;
                $stats['keputusan'] += count($results);
                $bar->advance();

                continue;
            }

            // Satu transaksi per kerusi: kerusi + keputusannya masuk bersama,
            // atau tidak langsung. Satu transaksi merangkumi 822 kerusi akan
            // memegang kunci selama beberapa minit pada pangkalan data hidup.
            DB::transaction(function () use ($seat, $nama, $negeri, $jenis, $kod, $link, $results, $ballotFor, &$stats) {
                $row = ElectionSeat::updateOrCreate(
                    ['slug' => $seat['slug']],
                    [
                        'nama' => $nama,
                        'kod' => $kod,
                        'negeri' => $negeri,
                        'jenis' => $jenis,
                        'kadun_id' => $link['kadun_id'],
                        'bandar_id' => $link['bandar_id'],
                        'synced_at' => now(),
                    ],
                );
                $stats['kerusi']++;

                // Blok `stats` daripada /v1/results mengisi angka yang tiada
                // dalam ringkasan kerusi — tetapi HANYA bagi tarikh yang sama.
                $stat = function (string $key) use ($ballotFor) {
                    return $ballotFor['stats'][$key] ?? null;
                };

                foreach ($results as $r) {
                    $tarikh = $r['date'] ?? $r['tarikh'] ?? null;
                    if (! $tarikh) {
                        continue;               // tiada kunci untuk merekodnya
                    }

                    // Dinormalkan kepada permulaan hari SEBELUM digunakan sebagai
                    // kunci carian. Cast 'date' menulis '2023-08-12 00:00:00',
                    // jadi mencari dengan rentetan mentah '2023-08-12' tidak akan
                    // pernah padan — larian kedua akan cuba menyisip semula dan
                    // melanggar kekangan unik, memusnahkan sifat boleh-ulang.
                    try {
                        $tarikh = \Illuminate\Support\Carbon::parse($tarikh)->startOfDay();
                    } catch (\Throwable) {
                        continue;               // tarikh tidak boleh dibaca — langkau
                    }

                    ElectionSeatResult::updateOrCreate(
                        ['election_seat_id' => $row->id, 'tarikh' => $tarikh],
                        [
                            'election_name' => $r['election_name'] ?? null,
                            // Setiap angka disalin APA ADANYA. Pilihan raya akan
                            // datang tiba dengan semuanya null — '?? 0' di sini
                            // akan mereka-reka kekalahan 0 undi.
                            'party' => $r['party'] ?? null,
                            'party_uid' => $r['party_uid'] ?? null,
                            'coalition' => $r['coalition'] ?? null,
                            'candidate' => $r['name'] ?? $r['candidate'] ?? null,
                            'majority' => $r['majority'] ?? null,
                            'majority_perc' => $r['majority_perc'] ?? null,
                            'voter_turnout' => $r['voter_turnout'] ?? null,
                            'voter_turnout_perc' => $r['voter_turnout_perc'] ?? null,
                            'voters_total' => $r['voters_total'] ?? $stat('voters_total'),
                            'votes_rejected' => $r['votes_rejected'] ?? null,
                            'votes_rejected_perc' => $r['votes_rejected_perc'] ?? null,
                            // Hanya keputusan lengkap terkini membawa pecahan
                            // undi penuh (lihat nota pengambilan di atas).
                            'ballot' => ($ballotFor && $ballotFor['tarikh'] === (string) ($r['date'] ?? $r['tarikh'] ?? ''))
                                ? $ballotFor['ballot']
                                : ($r['ballot'] ?? null),
                            'synced_at' => now(),
                        ],
                    );
                    $stats['keputusan']++;
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Kerusi', 'Keputusan', 'Tidak dipadan dengan geografi SISDA'],
            [[$stats['kerusi'], $stats['keputusan'], $stats['tidak_dipadan']]],
        );

        if ($stats['tidak_dipadan'] > 0) {
            $this->warn('Kerusi yang tidak dipadan disimpan tetapi tidak terpaut kepada kawasan SISDA. '
                .'Jalankan `pilihanraya:validate-kawasan` untuk melihat sebabnya.');
        }

        if (! $dryRun) {
            ElectionDataSetting::current()?->update(['last_synced_at' => now()]);
        }

        return self::SUCCESS;
    }

    /**
     * Padankan kerusi rasmi dengan geografi SISDA — kod dahulu (deterministik
     * apabila diisi oleh StateElectoralSeeder), kemudian nama + negeri.
     *
     * Padanan berbilang dilayan sebagai TIADA padanan: memaut kepada kawasan
     * yang salah lebih buruk daripada tidak memaut langsung.
     *
     * @return array{kadun_id:?int, bandar_id:?int}
     */
    private function matchKawasan(string $jenis, string $nama, string $negeri, ?string $kod): array
    {
        $kosong = ['kadun_id' => null, 'bandar_id' => null];
        if ($nama === '' || $negeri === '') {
            return $kosong;
        }

        if ($jenis === ElectionSeat::JENIS_PARLIMEN) {
            $q = Bandar::query()->whereHas('negeri', fn ($n) => $n->whereRaw('UPPER(TRIM(nama)) = ?', [ElectionDataService::nameKey($negeri)]));
            $ids = $kod
                ? (clone $q)->where('kod_parlimen', $kod)->pluck('id')
                : collect();
            if ($ids->count() !== 1) {
                $ids = $q->whereRaw('UPPER(TRIM(nama)) = ?', [ElectionDataService::nameKey($nama)])->pluck('id');
            }

            return $ids->count() === 1 ? ['kadun_id' => null, 'bandar_id' => (int) $ids->first()] : $kosong;
        }

        $q = Kadun::query()->whereHas('bandar.negeri', fn ($n) => $n->whereRaw('UPPER(TRIM(nama)) = ?', [ElectionDataService::nameKey($negeri)]));
        $ids = $kod ? (clone $q)->where('kod_dun', $kod)->pluck('id') : collect();
        if ($ids->count() !== 1) {
            $ids = $q->whereRaw('UPPER(TRIM(nama)) = ?', [ElectionDataService::nameKey($nama)])->pluck('id');
        }

        return $ids->count() === 1 ? ['kadun_id' => (int) $ids->first(), 'bandar_id' => null] : $kosong;
    }

    /**
     * 'N.15 Juasseh, Negeri Sembilan' -> 'Juasseh'
     *
     * API mengawal nama kerusi dengan KOD-nya ("P.140 Segamat, Johor"). Kod itu
     * MESTI dibuang: geografi SISDA menyimpan nama sahaja ("Segamat"), dan
     * election_seats.nama dibandingkan dengan nama itu oleh
     * ValidateKawasanCommand serta laluan sandaran ElectionDataService::slugFor().
     * Menyimpan awalan itu menjadikan SETIAP kawasan dilaporkan sebagai
     * "tiada dalam senarai rasmi" — 180 percanggahan palsu pada pengeluaran,
     * yang mengarahkan pengguna menamakan semula geografi yang sebenarnya betul.
     * Menamakan semula kawasan akan memutuskan setiap baris pengundi yang
     * dipadankan mengikut rentetan pada ejaan lama.
     *
     * Awalan dibuang HANYA apabila ia benar-benar kod kerusi ini (disahkan
     * terhadap slug), jadi nama tulen tidak boleh dicacatkan.
     */
    private static function namaOf(array $seat): string
    {
        $nama = trim(explode(',', (string) ($seat['seat'] ?? ''))[0] ?? '');
        $kod = self::kodFromSlug((string) ($seat['slug'] ?? ''));

        if ($kod === null) {
            return $nama;
        }

        $huruf = preg_quote(substr($kod, 0, 1), '/');
        $nombor = (int) substr($kod, 1);
        $corak = '/^'.$huruf.'\.?\s*0*'.$nombor.'\s+/i';

        return trim((string) preg_replace($corak, '', $nama));
    }

    /** 'Juasseh, Negeri Sembilan' -> 'Negeri Sembilan' */
    private static function negeriOf(array $seat): string
    {
        $parts = explode(',', (string) ($seat['seat'] ?? ''));

        return trim($parts[1] ?? '');
    }

    /** 'n15-juasseh-negeri-sembilan' -> 'N15' */
    private static function kodFromSlug(string $slug): ?string
    {
        return preg_match('/^([np]\d{1,3})-/i', $slug, $m) ? mb_strtoupper($m[1]) : null;
    }
}
