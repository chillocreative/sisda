<?php

namespace App\Console\Commands;

use App\Models\Bandar;
use App\Models\DaerahMengundi;
use App\Models\Kadun;
use App\Models\Lokaliti;
use App\Models\Negeri;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMasterDataFromXlsx extends Command
{
    protected $signature = 'master-data:import-from-xlsx
                            {path : Absolute path to the xlsx file}
                            {--dun= : Filter rows where NamaDUN matches (case-insensitive, partial allowed)}
                            {--sheet= : Sheet name (defaults to active sheet)}
                            {--dry-run : Show what would be imported without writing}';

    protected $description = 'Import unique Daerah Mengundi & Lokaliti rows into Data Induk from an SPR-style xlsx';

    public function handle(): int
    {
        $path = $this->argument('path');
        $dunFilter = $this->option('dun');
        $sheetName = $this->option('sheet');
        $dryRun = (bool) $this->option('dry-run');

        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        $this->info("Reading: {$path}");
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $sheetName ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();
        if (!$sheet) {
            $this->error("Sheet not found: {$sheetName}");
            return 1;
        }

        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        $headers = [];
        foreach ($sheet->rangeToArray("A1:{$highestCol}1", null, true, true, false)[0] as $idx => $val) {
            $headers[$idx] = strtolower(trim((string) $val));
        }

        $required = ['namalokaliti', 'namadm', 'namadun', 'namaparlimen'];
        $missing = array_diff($required, $headers);
        if (!empty($missing)) {
            $this->error('Missing required columns: ' . implode(', ', $missing));
            $this->line('Found headers: ' . implode(', ', $headers));
            return 1;
        }

        $colIndex = array_flip($headers);
        $needNegeri = isset($colIndex['namanegeri']);

        $dunFilterUpper = $dunFilter ? strtoupper(trim($dunFilter)) : null;

        $pairs = [];
        $dmParlimenMap = [];
        $dunParlimenMap = [];
        $negeriForParlimen = [];
        $rowsScanned = 0;
        $rowsKept = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $rowsScanned++;
            $row = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0];
            $get = function (string $key) use ($row, $colIndex) {
                return isset($colIndex[$key]) ? trim((string) ($row[$colIndex[$key]] ?? '')) : '';
            };

            $dun = $get('namadun');
            if ($dunFilterUpper && stripos($dun, $dunFilterUpper) === false) {
                continue;
            }

            $lokaliti = strtoupper($get('namalokaliti'));
            $dm = strtoupper($get('namadm'));
            $parlimen = $get('namaparlimen');
            $negeri = $needNegeri ? $get('namanegeri') : '';

            if ($lokaliti === '' || $dm === '' || $parlimen === '') {
                continue;
            }

            $rowsKept++;
            $pairs["{$dm}||{$lokaliti}"] = ['dm' => $dm, 'lokaliti' => $lokaliti];
            $dmParlimenMap[$dm] = $parlimen;
            if ($dun !== '') {
                $dunParlimenMap[strtoupper($dun)] = $parlimen;
            }
            if ($negeri !== '') {
                $negeriForParlimen[$parlimen] = $negeri;
            }
        }

        $this->info("Rows scanned: {$rowsScanned}, kept: {$rowsKept}");
        $this->info('Unique Daerah Mengundi: ' . count($dmParlimenMap));
        $this->info('Unique Lokaliti pairs: ' . count($pairs));

        if ($dryRun) {
            $this->warn('Dry run — no DB writes.');
            foreach ($dmParlimenMap as $dmName => $parl) {
                $this->line("  DM: {$dmName}  (Parlimen: {$parl})");
            }
            foreach ($pairs as $p) {
                $this->line("  Lokaliti: {$p['lokaliti']}  ←  DM: {$p['dm']}");
            }
            return 0;
        }

        $createdDm = 0;
        $createdLok = 0;
        $createdKadun = 0;

        foreach ($dmParlimenMap as $dmName => $parlimenName) {
            $bandar = Bandar::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($parlimenName))])->first();
            if (!$bandar) {
                $negeri = isset($negeriForParlimen[$parlimenName])
                    ? Negeri::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($negeriForParlimen[$parlimenName]))])->first()
                    : null;
                $bandar = Bandar::create([
                    'nama' => $parlimenName,
                    'kod_parlimen' => '',
                    'negeri_id' => $negeri?->id,
                ]);
                $this->line("  + Bandar created: {$parlimenName}");
            }

            $dm = DaerahMengundi::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($dmName))])
                ->where('bandar_id', $bandar->id)
                ->first();
            if (!$dm) {
                $dm = DaerahMengundi::create([
                    'nama' => $dmName,
                    'kod_dm' => '',
                    'bandar_id' => $bandar->id,
                ]);
                $createdDm++;
            }
        }

        foreach ($dunParlimenMap as $dunName => $parlimenName) {
            $bandar = Bandar::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($parlimenName))])->first();
            if (!$bandar) {
                continue;
            }
            $kadun = Kadun::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($dunName))])
                ->where('bandar_id', $bandar->id)
                ->first();
            if (!$kadun) {
                Kadun::create([
                    'nama' => $dunName,
                    'kod_dun' => '',
                    'bandar_id' => $bandar->id,
                ]);
                $createdKadun++;
            }
        }

        foreach ($pairs as $p) {
            $dm = DaerahMengundi::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($p['dm']))])->first();
            if (!$dm) {
                continue;
            }
            $existing = Lokaliti::whereRaw('UPPER(TRIM(nama)) = ?', [strtoupper(trim($p['lokaliti']))])
                ->where('daerah_mengundi_id', $dm->id)
                ->first();
            if (!$existing) {
                Lokaliti::create([
                    'nama' => $p['lokaliti'],
                    'daerah_mengundi_id' => $dm->id,
                ]);
                $createdLok++;
            }
        }

        $this->info("Done. Created: DM={$createdDm}, Lokaliti={$createdLok}, Kadun={$createdKadun}");
        return 0;
    }
}
