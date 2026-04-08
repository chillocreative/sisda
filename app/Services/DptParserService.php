<?php

namespace App\Services;

use App\Models\DptUpload;
use App\Models\PangkalanDataPengundi;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class DptParserService
{
    public static function parse(string $filePath, DptUpload $upload): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();

        $stats = ['total' => 0, 'new' => 0, 'deceased' => 0, 'moved' => 0];
        $headerInfo = [];

        // Parse page 1 for header info
        if (count($pages) > 0) {
            $headerInfo = self::parseHeader($pages[0]->getText());
        }

        // Parse voter pages
        foreach ($pages as $page) {
            $text = $page->getText();

            // Skip non-voter pages
            if (stripos($text, 'BIL.NO K/P') === false && stripos($text, 'NAMA PEMILIH') === false) {
                continue;
            }

            $pageStats = self::parseVoterPage($text, $upload);
            $stats['total'] += $pageStats['total'];
            $stats['new'] += $pageStats['new'];
            $stats['deceased'] += $pageStats['deceased'];
            $stats['moved'] += $pageStats['moved'];
        }

        return ['stats' => $stats, 'header' => $headerInfo];
    }

    protected static function parseHeader(string $text): array
    {
        $info = [];

        if (preg_match('/BULAN\s+(\w+)\s+TAHUN\s+(\d{4})/i', $text, $m)) {
            $info['bulan'] = $m[1];
            $info['tahun'] = $m[2];
        }

        if (preg_match('/DIWARTAKAN\s+PADA\s+(.+?)$/mi', $text, $m)) {
            $info['tarikh_warta'] = trim($m[1]);
        }

        if (preg_match('/PARLIMEN\s*:\s*P\.\d+\s+(.+?)$/mi', $text, $m)) {
            $info['parlimen'] = trim($m[1]);
        }

        if (preg_match('/NEGERI\s*:\s*(.+?)$/mi', $text, $m)) {
            $info['negeri'] = trim($m[1]);
        }

        return $info;
    }

    protected static function parseVoterPage(string $text, DptUpload $upload): array
    {
        $stats = ['total' => 0, 'new' => 0, 'deceased' => 0, 'moved' => 0];

        // Extract Daerah Mengundi
        $daerahMengundi = '';
        if (preg_match('/DAERAH MENGUNDI:\s*[\d\/]+\s+(.+?)$/mi', $text, $m)) {
            $daerahMengundi = trim($m[1]);
        }

        // Extract Lokaliti code and name
        $lokalitiCode = '';
        $lokalitiName = '';
        if (preg_match('/LOKALITI\s*:\s*(\d+)\s+(.+?)$/mi', $text, $m)) {
            $lokalitiCode = trim($m[1]);
            $lokalitiName = trim($m[2]);
        }

        // Determine current section
        $lines = explode("\n", $text);
        $currentSection = 'unknown';

        foreach ($lines as $line) {
            $line = trim($line);

            if (stripos($line, 'PENDAFTARAN BARU') !== false) {
                $currentSection = 'new';
                continue;
            }
            if (stripos($line, 'PEMOTONGAN - KEMATIAN') !== false) {
                $currentSection = 'deceased';
                continue;
            }
            if (stripos($line, 'PEMOTONGAN - PEMILIH BERTUKAR') !== false) {
                $currentSection = 'moved';
                continue;
            }

            // Parse voter line: starts with a digit (BIL number), followed by IC
            // Pattern: BIL + IC(8digits+****) + optional_id + J/gender + YEAR + NAME + address
            if (preg_match('/^(\d+)(\d{6}\d{2})\*{4}\s*(\d*\**)?\s*(P|L)\s*(\d{4})(.+?)(?:\t|$)/i', $line, $m)) {
                $bil = $m[1];
                $icPartial = $m[2]; // 8 digits
                $gender = $m[4];
                $yearBorn = $m[5];
                $nameAndAddress = trim($m[6]);

                // Separate name from address (address is after last tab or at end)
                $name = $nameAndAddress;
                $address = '';
                if (preg_match('/^(.+?)\t+(.*)$/', $nameAndAddress, $na)) {
                    $name = trim($na[1]);
                    $address = trim($na[2]);
                }

                // Complete IC: 8 visible digits + 0000
                $noIc = $icPartial . '0000';

                $isDeceased = ($currentSection === 'deceased');

                // Save to pangkalan_data_pengundi
                try {
                    PangkalanDataPengundi::updateOrCreate(
                        ['no_ic' => $noIc],
                        [
                            'nama' => strtoupper(trim($name)),
                            'daerah_mengundi' => $daerahMengundi,
                            'lokaliti' => $lokalitiName,
                            'kod_lokaliti' => $lokalitiCode,
                            'jantina' => $gender === 'L' ? 'LELAKI' : 'PEREMPUAN',
                            'tahun_lahir' => $yearBorn,
                            'is_deceased' => $isDeceased,
                            'dpt_upload_id' => $upload->id,
                        ]
                    );
                } catch (\Exception $e) {
                    Log::warning("DPT parse error for IC {$noIc}: " . $e->getMessage());
                }

                $stats['total']++;
                if ($currentSection === 'new') $stats['new']++;
                if ($currentSection === 'deceased') $stats['deceased']++;
                if ($currentSection === 'moved') $stats['moved']++;
            }
        }

        return $stats;
    }
}
