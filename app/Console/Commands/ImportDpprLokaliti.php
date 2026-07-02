<?php

namespace App\Console\Commands;

use App\Models\Bandar;
use App\Models\DaerahMengundi;
use App\Models\Lokaliti;
use Illuminate\Console\Command;

class ImportDpprLokaliti extends Command
{
    /**
     * Import Daerah Mengundi + Lokaliti from a DPPR export JSON.
     *
     * The JSON is an array of records with keys:
     *   kod    - 10-digit KodLokaliti (PPP DD MM LLL), e.g. "1400101001"
     *   nama   - locality name (NamaLokaliti)
     *   dm     - Daerah Mengundi name (NamaDM)
     *   dm_kod - "PPP/DD/MM", e.g. "140/01/01"
     *
     * Parlimen is resolved from the first 3 digits of `kod` -> "P" . NNN,
     * matched against bandar.kod_parlimen. DM belongs to the Bandar
     * (parlimen); Lokaliti belongs to the DM.
     */
    protected $signature = 'import:dppr {json : Path to the DPPR export JSON file}';

    protected $description = 'Import Daerah Mengundi and Lokaliti from a DPPR export JSON';

    public function handle(): int
    {
        $path = $this->argument('json');

        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $contents = file_get_contents($path);
        // PowerShell's `Out-File -Encoding utf8` prepends a UTF-8 BOM that breaks json_decode.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $records = json_decode($contents, true);

        if (!is_array($records) || empty($records)) {
            $this->error('JSON is empty or not an array of records.');
            return self::FAILURE;
        }

        $dmCache = [];   // dm_kod => DaerahMengundi
        $dmCount = 0;
        $lokCount = 0;

        foreach ($records as $r) {
            $kod = (string) ($r['kod'] ?? '');
            $dmKod = (string) ($r['dm_kod'] ?? '');
            $lokNama = trim((string) ($r['nama'] ?? ''));
            $dmNama = trim((string) ($r['dm'] ?? ''));

            if (strlen($kod) < 7 || $dmKod === '' || $lokNama === '') {
                $this->warn("Skipping malformed record: " . json_encode($r));
                continue;
            }

            if (!isset($dmCache[$dmKod])) {
                $parlimenKod = 'P' . substr($kod, 0, 3);
                $bandar = Bandar::where('kod_parlimen', $parlimenKod)->first();

                if (!$bandar) {
                    $this->error("Parlimen {$parlimenKod} not found — run JohorParlimenSeeder first. Skipping DM {$dmKod}.");
                    continue;
                }

                $dm = DaerahMengundi::updateOrCreate(
                    ['kod_dm' => $dmKod, 'bandar_id' => $bandar->id],
                    ['nama' => $dmNama]
                );
                if ($dm->wasRecentlyCreated) {
                    $dmCount++;
                }
                $dmCache[$dmKod] = $dm;
            }

            $lok = Lokaliti::updateOrCreate(
                ['nama' => $lokNama, 'daerah_mengundi_id' => $dmCache[$dmKod]->id],
                []
            );
            if ($lok->wasRecentlyCreated) {
                $lokCount++;
            }
        }

        $this->info("Done. Daerah Mengundi created: {$dmCount} (total referenced: " . count($dmCache) . "), Lokaliti created: {$lokCount}.");

        return self::SUCCESS;
    }
}
