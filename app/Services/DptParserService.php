<?php

namespace App\Services;

use App\Models\DptUpload;
use App\Models\PangkalanDataPengundi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Smalot\PdfParser\Parser;

class DptParserService
{
    public static function parse(string $filePath, DptUpload $upload): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();

        $stats = ['total' => 0, 'new' => 0, 'deceased' => 0, 'moved' => 0, 'errors' => 0];
        $headerInfo = [];

        // Check which columns exist
        $hasExtraColumns = Schema::hasColumn('pangkalan_data_pengundi', 'dpt_upload_id');

        // Parse all pages to collect header info
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

            if (stripos($text, 'BIL.NO K/P') === false && stripos($text, 'NAMA PEMILIH') === false) {
                continue;
            }

            $pageStats = self::parseVoterPage($text, $upload, $headerInfo, $hasExtraColumns);
            $stats['total'] += $pageStats['total'];
            $stats['new'] += $pageStats['new'];
            $stats['deceased'] += $pageStats['deceased'];
            $stats['moved'] += $pageStats['moved'];
            $stats['errors'] += $pageStats['errors'];
        }

        Log::info("DPT parse complete: {$stats['total']} total, {$stats['new']} new, {$stats['deceased']} deceased, {$stats['moved']} moved, {$stats['errors']} errors");

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

    protected static function parseVoterPage(string $text, DptUpload $upload, array $headerInfo, bool $hasExtraColumns): array
    {
        $stats = ['total' => 0, 'new' => 0, 'deceased' => 0, 'moved' => 0, 'errors' => 0];

        // Extract Daerah Mengundi
        $daerahMengundi = '';
        if (preg_match('/DAERAH MENGUNDI:\s*[\d\/]+\s+(.+?)$/mi', $text, $m)) {
            $daerahMengundi = trim($m[1]);
        }

        // Extract Lokaliti
        $lokalitiCode = '';
        $lokalitiName = '';
        if (preg_match('/LOKALITI\s*:\s*(\d+)\s+(.+?)$/mi', $text, $m)) {
            $lokalitiCode = trim($m[1]);
            $lokalitiName = trim($m[2]);
        }

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

            if (preg_match('/^(\d{1,3})(\d{6}\d{2})\*{4}\s*([A-Z]{0,3}\d*\**)\s*(P|L)\s*(\d{4})(.+?)(?:\t|$)/i', $line, $m)) {
                $icPartial = $m[2];
                $gender = $m[4];
                $yearBorn = $m[5];
                $nameAndAddress = trim($m[6]);

                $name = $nameAndAddress;
                if (preg_match('/^(.+?)\t+(.*)$/', $nameAndAddress, $na)) {
                    $name = trim($na[1]);
                }

                $noIc = $icPartial . '0000';
                $isDeceased = ($currentSection === 'deceased');

                try {
                    // Use direct DB insert/update for reliability
                    $existing = DB::table('pangkalan_data_pengundi')->where('no_ic', $noIc)->first();

                    $baseData = [
                        'nama' => strtoupper(trim($name)),
                        'daerah_mengundi' => $daerahMengundi,
                        'lokaliti' => $lokalitiName,
                        'parlimen' => $parlimen,
                        'negeri' => $negeri,
                        'updated_at' => now(),
                    ];

                    if ($hasExtraColumns) {
                        $baseData['kod_lokaliti'] = $lokalitiCode;
                        $baseData['jantina'] = $gender === 'L' ? 'LELAKI' : 'PEREMPUAN';
                        $baseData['tahun_lahir'] = $yearBorn;
                        $baseData['is_deceased'] = $isDeceased ? 1 : 0;
                        $baseData['dpt_upload_id'] = $upload->id;
                    }

                    if ($existing) {
                        DB::table('pangkalan_data_pengundi')
                            ->where('no_ic', $noIc)
                            ->update($baseData);
                    } else {
                        $baseData['no_ic'] = $noIc;
                        $baseData['created_at'] = now();
                        DB::table('pangkalan_data_pengundi')->insert($baseData);
                    }

                    $stats['total']++;
                    if ($currentSection === 'new') $stats['new']++;
                    if ($currentSection === 'deceased') $stats['deceased']++;
                    if ($currentSection === 'moved') $stats['moved']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error("DPT save error for IC {$noIc}: " . $e->getMessage());
                }
            }
        }

        return $stats;
    }
}
