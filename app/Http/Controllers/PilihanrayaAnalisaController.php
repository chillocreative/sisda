<?php

namespace App\Http\Controllers;

use App\Support\Pilihanraya\JohorElectionData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pilihanraya → Analisa. Serves the Buloh Kasap analytical pages (Keputusan
 * 2022, Minima, Kaum Mengikut DM) and parses an uploaded scoresheet on demand.
 *
 * All routes sit inside the super_admin/admin pilihanraya group.
 */
class PilihanrayaAnalisaController extends Controller
{
    private function context(): array
    {
        return [
            'dun' => JohorElectionData::DUN,
            'parlimen' => JohorElectionData::PARLIMEN,
            'negeri' => JohorElectionData::NEGERI,
            'kawasanList' => JohorElectionData::kawasanList(),
        ];
    }

    public function keputusan(Request $request)
    {
        return Inertia::render('Pilihanraya/Analisa', [
            'context' => $this->context(),
            'rows' => JohorElectionData::keputusan2022(),
            'totals' => JohorElectionData::keputusan2022Totals(),
        ]);
    }

    public function minima(Request $request)
    {
        return Inertia::render('Pilihanraya/Minima', [
            'context' => $this->context(),
            'minima' => JohorElectionData::minima(),
        ]);
    }

    public function kaumDm(Request $request)
    {
        return Inertia::render('Pilihanraya/KaumDm', [
            'context' => $this->context(),
            'rows' => JohorElectionData::kaumDm(),
            'totals' => JohorElectionData::kaumDmTotals(),
        ]);
    }

    /**
     * Parse an uploaded scoresheet (xlsx/xls/csv) and return both the faithful
     * raw grid and, when the expected columns are recognised, a normalised
     * result set that the Keputusan table + charts can render directly.
     */
    public function upload(Request $request)
    {
        $request->validate([
            // 'txt' included because a .csv is frequently mime-guessed as text/plain.
            'fail' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ]);

        $file = $request->file('fail');

        $sheets = Excel::toArray(null, $file);
        $grid = $sheets[0] ?? [];

        // Trim fully-empty trailing rows/columns.
        $grid = array_map(fn ($row) => array_map(
            fn ($c) => is_null($c) ? '' : (is_string($c) ? trim($c) : $c),
            $row
        ), $grid);
        $grid = array_values(array_filter($grid, fn ($row) => implode('', array_map('strval', $row)) !== ''));

        return response()->json([
            'filename' => $file->getClientOriginalName(),
            'grid' => $grid,
            'parsed' => $this->normalizeScoresheet($grid),
        ]);
    }

    /**
     * Best-effort mapping of a scoresheet grid to the Keputusan schema. Locates
     * the header row (one that names "Daerah Mengundi" or carries PH & BN
     * columns) and pulls the standard result columns from it. Returns null when
     * the layout is unrecognised — the caller then falls back to the raw grid.
     */
    private function normalizeScoresheet(array $grid): ?array
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
                    $map[$this->columnKey($label)][] = $c;
                }
                break;
            }
        }

        if ($headerIdx === null) {
            return null;
        }

        $col = function (string $key) use ($map) {
            return $map[$key][0] ?? null;
        };

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

            // A totals row inside the sheet — capture but don't double count.
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

    private function columnKey(string $label): string
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
