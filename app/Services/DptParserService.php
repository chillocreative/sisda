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

        // Parse all pages to collect header info (parlimen, negeri, kadun)
        foreach ($pages as $page) {
            $text = $page->getText();
            $pageHeader = self::parseHeader($text);
            if (!empty($pageHeader)) {
                $headerInfo = array_merge($headerInfo, $pageHeader);
            }
        }

        // Parse voter pages
        foreach ($pages as $page) {
            $text = $page->getText();

            // Skip non-voter pages
            if (stripos($text, 'BIL.NO K/P') === false && stripos($text, 'NAMA PEMILIH') === false) {
                continue;
            }

            $pageStats = self::parseVoterPage($text, $upload, $headerInfo);
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

        // Extract KADUN (N.XX NAME)
        if (preg_match('/NEGERI\s*:\s*N\.\d+\s+(.+?)$/mi', $text, $m)) {
            $info['kadun'] = trim($m[1]);
        }

        return $info;
    }

    protected static function parseVoterPage(string $text, DptUpload $upload, array $headerInfo): array
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

        // Get parlimen, negeri from header
        $parlimen = $headerInfo['parlimen'] ?? '';
        $negeri = $headerInfo['negeri'] ?? '';

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

            // Parse voter line
            if (preg_match('/^(\d{1,3})(\d{6}\d{2})\*{4}\s*([A-Z]{0,3}\d*\**)\s*(P|L)\s*(\d{4})(.+?)(?:\t|$)/i', $line, $m)) {
                $icPartial = $m[2]; // 8 digits
                $gender = $m[4];
                $yearBorn = $m[5];
                $nameAndAddress = trim($m[6]);

                // Separate name from address
                $name = $nameAndAddress;
                if (preg_match('/^(.+?)\t+(.*)$/', $nameAndAddress, $na)) {
                    $name = trim($na[1]);
                }

                // Complete IC: 8 visible digits + 0000
                $noIc = $icPartial . '0000';

                $isDeceased = ($currentSection === 'deceased');

                try {
                    PangkalanDataPengundi::updateOrCreate(
                        ['no_ic' => $noIc],
                        [
                            'nama' => strtoupper(trim($name)),
                            'daerah_mengundi' => $daerahMengundi,
                            'lokaliti' => $lokalitiName,
                            'kod_lokaliti' => $lokalitiCode,
                            'parlimen' => $parlimen,
                            'negeri' => $negeri,
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
