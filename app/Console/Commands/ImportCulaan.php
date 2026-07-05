<?php

namespace App\Console\Commands;

use App\Imports\CulaanSentimentImport;
use App\Services\CulaanMatchService;
use Illuminate\Console\Command;

/**
 * CLI wrapper over CulaanMatchService — parse a DATA CULAAN sentiment CSV and
 * enrich data_pengundi. Generalizes culaan:import-segamat (any Johor
 * constituency, CSV input, sentiment mapping). Local/prod test + ops entry.
 */
class ImportCulaan extends Command
{
    protected $signature = 'culaan:import
        {file : Path to the DATA CULAAN CSV export}
        {--user= : users.id for submitted_by on created rows (default: first super_admin)}
        {--dry-run : Match and report only; write nothing}';

    protected $description = 'Import canvassing sentiment CSV -> set kecenderungan_politik + voter_color on data_pengundi (matched to the roll by name, scoped by Parlimen/DUN)';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            $this->error("File not found: {$this->argument('file')}");

            return self::FAILURE;
        }

        $rows = (new CulaanSentimentImport)->read($path);
        $this->info('Parsed '.count($rows).' CSV rows.');

        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $report = (new CulaanMatchService)->run($rows, $userId, (bool) $this->option('dry-run'));

        $dry = $report['dry_run'];
        $this->newLine();
        $this->table(['Result', 'Count'], [
            ['CSV rows parsed', $report['jumlah_baris']],
            ['Distinct voters (parlimen|nama)', $report['pengundi_unik']],
            [$dry ? 'Matched (would enrich)' : 'Matched', $report['matched']],
            [$dry ? 'Voter — would create from roll' : 'Voter — created from roll', $report['dicipta']],
            [$dry ? 'Would update' : 'Updated', $report['dikemaskini']],
            ['Unchanged', $report['tak_berubah'] ?? 0],
            ['Not found in roll', $report['tidak_dijumpai']],
            ['Ambiguous same-name (1 auto-picked, incl. in Matched)', $report['taksah']],
            ['Blank sentiment -> TIDAK PASTI', $report['tiada_sentimen']],
            ['Unresolved constituency', $report['unresolved_constituency']],
            ['Rows without DUN (operation_name)', $report['baris_tanpa_dun'] ?? 0],
        ]);

        $noDun = $report['baris_tanpa_dun'] ?? 0;
        if ($noDun > 0 && $noDun >= ($report['jumlah_baris'] ?? 0) * 0.5) {
            $this->newLine();
            $this->warn('Fail tiada DUN (operation_name) — padanan ikut Parlimen sahaja (banyak bertindih). Guna fail culaan harian (culaan_culaan-today_*.csv) untuk ketepatan DUN.');
        }

        if (! empty($report['sample_tidak_dijumpai'])) {
            $this->newLine();
            $this->warn('Sample not-found in roll (first 15):');
            foreach (array_slice($report['sample_tidak_dijumpai'], 0, 15) as $s) {
                $this->line('  '.$s);
            }
        }
        if (! empty($report['sample_taksah'])) {
            $this->newLine();
            $this->warn('Sample ambiguous (first 15):');
            foreach (array_slice($report['sample_taksah'], 0, 15) as $s) {
                $this->line('  '.$s);
            }
        }

        return self::SUCCESS;
    }
}
