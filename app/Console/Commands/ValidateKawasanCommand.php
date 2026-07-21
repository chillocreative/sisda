<?php

namespace App\Console\Commands;

use App\Models\Bandar;
use App\Models\ElectionSeat;
use App\Models\Kadun;
use App\Services\Pilihanraya\ElectionDataService;
use Illuminate\Console\Command;

/**
 * Bandingkan geografi SISDA dengan senarai kerusi rasmi SPR.
 *
 * BACA SAHAJA — tidak mengubah apa-apa. Geografi SISDA dipadan mengikut
 * RENTETAN, bukan kunci asing, jadi satu tersalah taip pada nama kerusi
 * memutuskan hubungan data secara senyap. Arahan ini menjadikan pemutusan itu
 * kelihatan, tetapi pembetulannya kekal keputusan manusia: menulis semula nama
 * kerusi secara automatik akan memutuskan setiap baris canvass yang telah
 * dipadankan dengan ejaan lama.
 *
 * Memerlukan `pilihanraya:sync-electiondata` dijalankan dahulu.
 */
class ValidateKawasanCommand extends Command
{
    protected $signature = 'pilihanraya:validate-kawasan {--negeri= : Hadkan kepada satu negeri}';

    protected $description = 'Laporkan percanggahan antara kawasan SISDA dan senarai kerusi rasmi SPR (baca sahaja)';

    public function handle(): int
    {
        if (ElectionSeat::count() === 0) {
            $this->error('Tiada kerusi rasmi disegerakkan. Jalankan `pilihanraya:sync-electiondata` dahulu.');

            return self::FAILURE;
        }

        $negeri = $this->option('negeri');

        $tiadaPadanan = [];       // kawasan SISDA yang tiada dalam senarai rasmi
        $kodBercanggah = [];      // nama padan, kod tidak
        $kodKosong = [];          // kerusi rasmi diketahui, kod SISDA kosong

        foreach ($this->kawasans($negeri) as [$jenis, $row, $namaNegeri, $kod]) {
            $rasmi = ElectionSeat::query()
                ->where('jenis', $jenis)
                ->whereRaw('UPPER(TRIM(negeri)) = ?', [ElectionDataService::nameKey($namaNegeri)])
                ->whereRaw('UPPER(TRIM(nama)) = ?', [ElectionDataService::nameKey($row->nama)])
                ->first();

            if (! $rasmi) {
                $tiadaPadanan[] = [$jenis, $namaNegeri, $row->nama, $kod ?: '—'];

                continue;
            }

            if ($kod && $rasmi->kod && ElectionDataService::nameKey($kod) !== ElectionDataService::nameKey($rasmi->kod)) {
                $kodBercanggah[] = [$jenis, $namaNegeri, $row->nama, $kod, $rasmi->kod];
            } elseif (! $kod && $rasmi->kod) {
                $kodKosong[] = [$jenis, $namaNegeri, $row->nama, $rasmi->kod];
            }
        }

        // Arah bertentangan: kerusi rasmi yang tiada langsung dalam SISDA.
        $tiadaDalamSisda = ElectionSeat::query()
            ->whereNull('kadun_id')->whereNull('bandar_id')
            ->when($negeri, fn ($q) => $q->whereRaw('UPPER(TRIM(negeri)) = ?', [ElectionDataService::nameKey($negeri)]))
            ->orderBy('negeri')->orderBy('nama')
            ->get(['jenis', 'negeri', 'nama', 'kod']);

        $this->report('Kawasan SISDA yang TIADA dalam senarai rasmi (ejaan atau kerusi mansuh?)',
            ['Jenis', 'Negeri', 'Nama', 'Kod SISDA'], $tiadaPadanan);

        $this->report('Kod kerusi BERCANGGAH (nama padan, kod tidak)',
            ['Jenis', 'Negeri', 'Nama', 'Kod SISDA', 'Kod rasmi'], $kodBercanggah);

        $this->report('Kod kerusi KOSONG dalam SISDA (kod rasmi diketahui)',
            ['Jenis', 'Negeri', 'Nama', 'Kod rasmi'], $kodKosong);

        $this->report('Kerusi rasmi yang belum dipaut kepada mana-mana kawasan SISDA',
            ['Jenis', 'Negeri', 'Nama', 'Kod'], $tiadaDalamSisda->map(fn ($s) => [$s->jenis, $s->negeri, $s->nama, $s->kod ?: '—'])->all());

        $jumlah = count($tiadaPadanan) + count($kodBercanggah) + count($kodKosong) + $tiadaDalamSisda->count();
        $this->newLine();
        $jumlah === 0
            ? $this->info('Tiada percanggahan ditemui.')
            : $this->warn("{$jumlah} percanggahan dilaporkan. Tiada apa-apa diubah — betulkan secara manual di Data Induk.");

        return self::SUCCESS;
    }

    /** @return iterable<array{0:string,1:object,2:string,3:?string}> */
    private function kawasans(?string $negeri): iterable
    {
        $bandars = Bandar::with('negeri')
            ->when($negeri, fn ($q) => $q->whereHas('negeri', fn ($n) => $n->whereRaw('UPPER(TRIM(nama)) = ?', [ElectionDataService::nameKey($negeri)])))
            ->get();

        foreach ($bandars as $b) {
            yield [ElectionSeat::JENIS_PARLIMEN, $b, (string) $b->negeri?->nama, $b->kod_parlimen];
        }

        foreach (Kadun::with('bandar.negeri')->whereIn('bandar_id', $bandars->pluck('id'))->get() as $k) {
            yield [ElectionSeat::JENIS_DUN, $k, (string) $k->bandar?->negeri?->nama, $k->kod_dun];
        }
    }

    private function report(string $title, array $headers, array $rows): void
    {
        $this->newLine();
        if ($rows === []) {
            $this->line("<info>✔</info> {$title}: tiada.");

            return;
        }
        $this->line("<comment>{$title}</comment> (".count($rows).')');
        $this->table($headers, array_slice($rows, 0, 50));
        if (count($rows) > 50) {
            // Sekatan dinyatakan secara terbuka — laporan yang dipotong senyap
            // akan dibaca sebagai "itu sahaja yang salah".
            $this->line('… '.(count($rows) - 50).' lagi tidak dipaparkan.');
        }
    }
}
