<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class SeatScoresSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(protected array $seatScores) {}

    public function collection(): Collection
    {
        return collect($this->seatScores);
    }

    public function headings(): array
    {
        return [
            'Kerusi',
            'Jenis',
            'Pengundi Berdaftar',
            'Diculaan',
            'Liputan %',
            'Putih',
            'Hitam',
            'Kelabu',
            'Skor',
            'Kategori',
            'Tren Putih 30 Hari',
        ];
    }

    public function map($seat): array
    {
        // Guard text cells against formula injection; numerics are cast
        // upstream in PilihanrayaController::exportPayload().
        $cell = fn ($v) => preg_match('/^[=+@\t]/', (string) $v) ? ' '.(string) $v : (string) $v;

        return [
            $cell($seat['kerusi'] ?? ''),
            strtoupper((string) ($seat['jenis'] ?? 'kadun')),
            (int) ($seat['daftar'] ?? 0),
            (int) ($seat['culaan'] ?? 0),
            (float) ($seat['liputan'] ?? 0),
            (int) ($seat['putih'] ?? 0),
            (int) ($seat['hitam'] ?? 0),
            (int) ($seat['kelabu'] ?? 0),
            (int) ($seat['skor'] ?? 0),
            $cell($seat['kategori'] ?? ''),
            $seat['tren_putih_30h'] ?? '-',
        ];
    }

    public function title(): string
    {
        return 'Skor Kerusi';
    }
}
