<?php

namespace App\Console\Commands;

use App\Models\DataPengundi;
use App\Models\PangkalanDataPengundi;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Console\Command;

class ImportCulaanSegamat extends Command
{
    protected $signature = 'culaan:import-segamat {--dry-run : Report matches without writing anything}';

    protected $description = 'Match DATA CULAAN sentiment (Segamat/Buloh Kasap) to DPPR voters by exact name and set kecenderungan_politik on Data Pengundi records';

    public function handle(): int
    {
        $file = database_path('data/johor/culaan_segamat.json');
        if (! is_file($file)) {
            $this->error("Data file not found: {$file}");

            return self::FAILURE;
        }

        // Strip a UTF-8 BOM in case the file was exported with one.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file));
        $records = json_decode($contents, true);
        if (! is_array($records) || empty($records)) {
            $this->error('No records found in culaan_segamat.json');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // submitted_by owner for any newly-created Data Pengundi rows.
        $systemUser = User::where('role', 'super_admin')->orderBy('id')->first()
            ?? User::orderBy('id')->first();
        if (! $systemUser) {
            $this->error('No user available for submitted_by.');

            return self::FAILURE;
        }

        $norm = fn ($s) => strtoupper(trim(preg_replace('/\s+/', ' ', (string) $s)));

        // Build normalized-name => kecenderungan_politik map from the CSV export.
        $sentimentByName = [];
        foreach ($records as $r) {
            $key = $norm($r['nama'] ?? '');
            $kp = trim((string) ($r['kecenderungan_politik'] ?? ''));
            if ($key === '' || $kp === '') {
                continue;
            }
            $sentimentByName[$key] = $kp;
        }

        // DPPR voters for the Segamat parlimen (active upload batch when set).
        $activeBatchIds = UploadBatch::activeIds();
        $voterQuery = PangkalanDataPengundi::query()
            ->whereRaw('LOWER(parlimen) = ?', ['segamat']);
        if (! empty($activeBatchIds)) {
            $voterQuery->whereIn('upload_batch_id', $activeBatchIds);
        }

        $created = 0;
        $updated = 0;
        $matchedVoters = 0;
        $matchedNames = [];

        $voterQuery->orderBy('id')->chunkById(1000, function ($voters) use (
            $sentimentByName, $norm, $dryRun, $systemUser,
            &$created, &$updated, &$matchedVoters, &$matchedNames
        ) {
            foreach ($voters as $voter) {
                $key = $norm($voter->nama);
                if (! isset($sentimentByName[$key])) {
                    continue;
                }

                $kp = $sentimentByName[$key];
                $matchedVoters++;
                $matchedNames[$key] = true;

                if ($dryRun) {
                    continue;
                }

                // Preserve any real, user-entered record — only set the political
                // tendency. Create a minimal record when none exists yet.
                $existing = DataPengundi::where('no_ic', $voter->no_ic)
                    ->whereRaw('LOWER(parlimen) = ?', ['segamat'])
                    ->first();

                if ($existing) {
                    if ($existing->kecenderungan_politik !== $kp) {
                        $existing->kecenderungan_politik = $kp;
                        $existing->save();
                    }
                    $updated++;
                } else {
                    DataPengundi::create($this->buildRecord($voter, $kp, $systemUser->id));
                    $created++;
                }
            }
        });

        $unmatched = array_values(array_diff(array_keys($sentimentByName), array_keys($matchedNames)));

        $this->info('=== Culaan Segamat import '.($dryRun ? '(DRY-RUN, nothing written)' : '').' ===');
        $this->info('CSV unique voters:          '.count($sentimentByName));
        $this->info('DPPR voters matched:        '.$matchedVoters);
        $this->info('Data Pengundi created:      '.$created);
        $this->info('Data Pengundi updated:      '.$updated);
        $this->info('CSV names with NO match:    '.count($unmatched));
        if (! empty($unmatched)) {
            $this->line('Unmatched (first 50): '.implode(' | ', array_slice($unmatched, 0, 50)));
        }

        return self::SUCCESS;
    }

    /**
     * Minimal Data Pengundi row for a matched voter. Required NOT NULL fields
     * we don't have are left blank; umur is derived from the IC birthdate.
     */
    private function buildRecord(PangkalanDataPengundi $voter, string $kp, int $userId): array
    {
        return [
            'nama' => $voter->nama,
            'no_ic' => $voter->no_ic,
            'umur' => $this->ageFromIc($voter->no_ic),
            'no_tel' => '',
            'bangsa' => $voter->bangsa ?: '',
            'alamat' => '',
            'poskod' => '',
            'negeri' => $voter->negeri ? ucwords(strtolower($voter->negeri)) : 'Johor',
            'bandar' => $voter->parlimen ?: 'SEGAMAT',
            'parlimen' => $voter->parlimen ?: 'SEGAMAT',
            'kadun' => $voter->kadun ?: 'BULOH KASAP',
            'daerah_mengundi' => $voter->daerah_mengundi,
            'lokaliti' => $voter->lokaliti,
            'kecenderungan_politik' => $kp,
            'submitted_by' => $userId,
        ];
    }

    /**
     * Age from a 12-digit Malaysian IC (YYMMDD prefix). 0 if unparseable.
     */
    private function ageFromIc(?string $ic): int
    {
        if (! $ic || strlen($ic) < 6 || ! ctype_digit(substr($ic, 0, 6))) {
            return 0;
        }
        $yy = (int) substr($ic, 0, 2);
        $currentYY = (int) now()->format('y');
        $birthYear = $yy <= $currentYY ? 2000 + $yy : 1900 + $yy;
        $age = (int) now()->year - $birthYear;

        return ($age >= 0 && $age <= 120) ? $age : 0;
    }
}
