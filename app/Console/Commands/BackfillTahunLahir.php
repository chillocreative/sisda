<?php

namespace App\Console\Commands;

use App\Models\PangkalanDataPengundi;
use App\Support\MalaysianIc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fill pangkalan_data_pengundi.tahun_lahir from the voter's IC.
 *
 * Voter exports frequently carry no birth-year column (the SPR/TERAS export
 * has none), which leaves the Dashboard's Taburan Umur chart empty even though
 * every row's IC begins with the birth date. This backfills the existing rows;
 * new uploads derive it at import time.
 *
 * Only rows where tahun_lahir is blank are touched — a value that came from a
 * file is never overwritten. ICs that do not decode to a plausible voter birth
 * date are left NULL (unknown), not guessed at.
 */
class BackfillTahunLahir extends Command
{
    protected $signature = 'pengundi:backfill-tahun-lahir
                            {--dry-run : Report what would change without writing}
                            {--chunk=1000 : Rows per batch}';

    protected $description = 'Isi tahun_lahir pengundi daripada nombor IC (baris kosong sahaja)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(100, (int) $this->option('chunk'));

        $pending = $this->pendingQuery()->count();
        $this->info(($dryRun ? '[DRY RUN] ' : '')."Baris tanpa tahun_lahir: {$pending}");

        if ($pending === 0) {
            $this->info('Tiada apa-apa untuk diisi.');

            return self::SUCCESS;
        }

        $filled = 0;
        $undecodable = 0;
        $bar = $this->output->createProgressBar($pending);
        $bar->start();

        // chunkById, not chunk: the rows are being updated out of the result
        // set as we go, which makes offset paging skip records.
        $this->pendingQuery()->select('id', 'no_ic')->chunkById($chunk, function ($rows) use (&$filled, &$undecodable, $dryRun, $bar) {
            $updates = [];
            foreach ($rows as $row) {
                $year = MalaysianIc::voterBirthYear((string) $row->no_ic);
                if ($year === null) {
                    $undecodable++;
                } else {
                    $updates[$year][] = $row->id;
                    $filled++;
                }
                $bar->advance();
            }

            if (! $dryRun) {
                // One UPDATE per distinct year rather than per row.
                DB::transaction(function () use ($updates) {
                    foreach ($updates as $year => $ids) {
                        PangkalanDataPengundi::whereIn('id', $ids)->update(['tahun_lahir' => (string) $year]);
                    }
                });
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? 'Akan diisi' : 'Diisi').": {$filled}");
        $this->warn("IC tanpa tarikh lahir yang munasabah (kekal kosong): {$undecodable}");

        if ($dryRun) {
            $this->comment('Dry run — tiada perubahan disimpan.');
        }

        return self::SUCCESS;
    }

    private function pendingQuery()
    {
        return PangkalanDataPengundi::whereNotNull('no_ic')
            ->where('no_ic', '!=', '')
            ->where(fn ($q) => $q->whereNull('tahun_lahir')->orWhere('tahun_lahir', ''));
    }
}
