<?php

namespace Database\Seeders;

use App\Models\Bandar;
use App\Models\DaerahMengundi;
use App\Models\Lokaliti;
use Illuminate\Database\Seeder;

class JohorDmLokalitiSeeder extends Seeder
{
    /**
     * Seed Daerah Mengundi + Lokaliti for Johor from committed DPPR export
     * files in database/data/johor/*.json.
     *
     * Each file is an array of records with keys:
     *   kod    - 10-digit KodLokaliti (PPP DD MM LLL), e.g. "1400101001"
     *   nama   - locality name (NamaLokaliti)
     *   dm     - Daerah Mengundi name (NamaDM)
     *   dm_kod - "PPP/DD/MM", e.g. "140/01/01"
     *
     * Parlimen is resolved from the first 3 digits of `kod` -> "P" . NNN,
     * matched against bandar.kod_parlimen. DM belongs to the Bandar
     * (parlimen); Lokaliti belongs to the DM. Idempotent (updateOrCreate).
     *
     * To add another DUN: drop its export JSON into database/data/johor/
     * and re-run this seeder.
     */
    public function run(): void
    {
        $dir = database_path('data/johor');
        $files = glob($dir . '/*.json') ?: [];

        if (empty($files)) {
            $this->command->warn("No JSON data files found in {$dir}.");
            return;
        }

        $dmTotal = 0;
        $lokTotal = 0;

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            // Strip a UTF-8 BOM in case a file was exported with one.
            $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
            $records = json_decode($contents, true);

            if (!is_array($records) || empty($records)) {
                $this->command->warn('Skipping empty/invalid file: ' . basename($file));
                continue;
            }

            $dmCache = [];

            foreach ($records as $r) {
                $kod = (string) ($r['kod'] ?? '');
                $dmKod = (string) ($r['dm_kod'] ?? '');
                $lokNama = trim((string) ($r['nama'] ?? ''));
                $dmNama = trim((string) ($r['dm'] ?? ''));

                if (strlen($kod) < 7 || $dmKod === '' || $lokNama === '') {
                    continue;
                }

                if (!isset($dmCache[$dmKod])) {
                    $parlimenKod = 'P' . substr($kod, 0, 3);
                    $bandar = Bandar::where('kod_parlimen', $parlimenKod)->first();

                    if (!$bandar) {
                        $this->command->error("Parlimen {$parlimenKod} not found — run JohorParlimenSeeder first. Skipping {$dmKod}.");
                        continue;
                    }

                    $dm = DaerahMengundi::updateOrCreate(
                        ['kod_dm' => $dmKod, 'bandar_id' => $bandar->id],
                        ['nama' => $dmNama]
                    );
                    $dmCache[$dmKod] = $dm;
                    $dmTotal++;
                }

                Lokaliti::updateOrCreate(
                    ['nama' => $lokNama, 'daerah_mengundi_id' => $dmCache[$dmKod]->id],
                    []
                );
                $lokTotal++;
            }

            $this->command->info(basename($file) . " — " . count($dmCache) . " DM, " . count($records) . " lokaliti.");
        }

        $this->command->info("Johor DM/Lokaliti seeded. DM upserts: {$dmTotal}, Lokaliti upserts: {$lokTotal}.");
    }
}
