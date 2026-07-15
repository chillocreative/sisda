<?php

namespace App\Support\Pilihanraya;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads an uploaded election scoresheet (xlsx/xls/csv) and, when the
 * expected columns are recognised, normalises it to the Keputusan schema
 * (per Daerah Mengundi rows + totals). Shared by both the ad-hoc upload on
 * the Analisa page and the saveable comparison scenarios.
 *
 * Extracted verbatim from PilihanrayaAnalisaController so the two callers
 * parse identically.
 */
class ScoresheetParser
{
    /**
     * Parse a file into the faithful raw grid plus, when recognised, a
     * normalised result set.
     *
     * @return array{filename:string, grid:array, parsed:?array{rows:array,totals:array}}
     */
    public static function parse(UploadedFile $file): array
    {
        $grid = self::grid($file);

        return [
            'filename' => $file->getClientOriginalName(),
            'grid' => $grid,
            'parsed' => self::normalize($grid),
        ];
    }

    /** Read the first sheet and trim fully-empty trailing rows/columns. */
    public static function grid(UploadedFile $file): array
    {
        $sheets = Excel::toArray(null, $file);
        $grid = $sheets[0] ?? [];

        $grid = array_map(fn ($row) => array_map(
            fn ($c) => is_null($c) ? '' : (is_string($c) ? trim($c) : $c),
            $row
        ), $grid);

        return array_values(array_filter($grid, fn ($row) => implode('', array_map('strval', $row)) !== ''));
    }

    /**
     * Best-effort mapping of a scoresheet grid to the Keputusan schema.
     * Returns null when the layout is unrecognised.
     */
    public static function normalize(array $grid): ?array
    {
        $headerIdx = null;
        $map = [];

        foreach ($grid as $i => $row) {
            $cells = array_map(fn ($c) => strtoupper(trim((string) $c)), $row);
            $joined = implode('|', $cells);

            $hasDm = str_contains($joined, 'DAERAH MENGUNDI');
            $hasPh = in_array('PH', $cells, true) || str_contains($joined, '|PH|');
            $hasBn = in_array('BN', $cells, true);

            if ($hasDm || ($hasPh && $hasBn)) {
                $headerIdx = $i;
                foreach ($cells as $c => $label) {
                    $map[self::columnKey($label)][] = $c;
                }
                break;
            }
        }

        if ($headerIdx === null) {
            return null;
        }

        $col = fn (string $key) => $map[$key][0] ?? null;

        $dmCol = $col('dm');
        $phCol = $col('ph');
        $bnCol = $col('bn');
        if ($dmCol === null || $phCol === null || $bnCol === null) {
            return null;
        }

        $num = fn ($v) => is_numeric(str_replace([',', '%'], '', (string) $v))
            ? (float) str_replace([',', '%'], '', (string) $v)
            : null;

        $rows = [];
        $totals = ['pemilih' => 0, 'keluar' => 0, 'ph' => 0, 'pejuang' => 0, 'pn' => 0, 'bn' => 0, 'ditolak' => 0];

        for ($i = $headerIdx + 1; $i < count($grid); $i++) {
            $row = $grid[$i];
            $name = trim((string) ($row[$dmCol] ?? ''));
            if ($name === '' || $num($row[$phCol] ?? null) === null && $num($row[$bnCol] ?? null) === null) {
                continue;
            }

            $isTotal = (bool) preg_match('/\b(JUMLAH|JUMLAH BESAR|TOTAL)\b/u', strtoupper($name));

            $rec = [
                'dm' => $name,
                'pemilih' => $col('pemilih') !== null ? $num($row[$col('pemilih')] ?? null) : null,
                'keluar' => $col('keluar') !== null ? $num($row[$col('keluar')] ?? null) : null,
                'ph' => $num($row[$phCol] ?? null) ?? 0,
                'pejuang' => $col('pejuang') !== null ? ($num($row[$col('pejuang')] ?? null) ?? 0) : 0,
                'pn' => $col('pn') !== null ? ($num($row[$col('pn')] ?? null) ?? 0) : 0,
                'bn' => $num($row[$bnCol] ?? null) ?? 0,
                'ditolak' => $col('ditolak') !== null ? ($num($row[$col('ditolak')] ?? null) ?? 0) : 0,
                'melayu' => $col('melayu') !== null ? $num($row[$col('melayu')] ?? null) : null,
                'cina' => $col('cina') !== null ? $num($row[$col('cina')] ?? null) : null,
                'india' => $col('india') !== null ? $num($row[$col('india')] ?? null) : null,
            ];

            if ($isTotal) {
                continue;
            }

            foreach ($totals as $k => $_) {
                if (($rec[$k] ?? null) !== null) {
                    $totals[$k] += $rec[$k];
                }
            }
            $rows[] = $rec;
        }

        if (count($rows) === 0) {
            return null;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    private static function columnKey(string $label): string
    {
        $label = strtoupper(trim($label));

        return match (true) {
            str_contains($label, 'DAERAH MENGUNDI') => 'dm',
            $label === 'PEMILIH' || str_contains($label, 'DPPR') || str_contains($label, 'PENGUNDI') => 'pemilih',
            str_contains($label, 'UNDI KELUAR') || str_contains($label, 'KELUAR') || str_contains($label, 'UNDI SAH') => 'keluar',
            $label === 'PH' => 'ph',
            $label === 'BN' => 'bn',
            $label === 'PN' => 'pn',
            str_contains($label, 'PEJUANG') => 'pejuang',
            str_contains($label, 'DITOLAK') || str_contains($label, 'ROSAK') => 'ditolak',
            str_contains($label, 'MELAYU') => 'melayu',
            str_contains($label, 'CINA') => 'cina',
            str_contains($label, 'INDIA') => 'india',
            default => 'x_'.$label,
        };
    }
}
