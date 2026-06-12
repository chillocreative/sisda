<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class BriefingSummarySheet implements FromArray, WithTitle
{
    public function __construct(protected array $briefing) {}

    /**
     * Neutralise spreadsheet formula injection — a cell value starting
     * with =, +, @ or a tab would otherwise execute as a live formula.
     */
    private function cell($value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+@\t]/', $value) ? ' '.$value : $value;
    }

    public function array(): array
    {
        $rows = [
            [$this->cell($this->briefing['tajuk'] ?? 'Taklimat Eksekutif Pilihanraya')],
            ['Tarikh', $this->cell($this->briefing['tarikh'] ?? now()->format('d/m/Y'))],
            [],
        ];

        foreach ($this->briefing['seksyen'] ?? [] as $i => $section) {
            $rows[] = [($i + 1).'. '.$this->cell($section['tajuk'] ?? '')];
            $rows[] = ['', $this->cell($section['kandungan'] ?? '')];
            foreach ($section['bullet_points'] ?? [] as $point) {
                $rows[] = ['', '• '.$this->cell($point)];
            }
            $rows[] = [];
        }

        if (! empty($this->briefing['kesimpulan'])) {
            $rows[] = ['Kesimpulan'];
            $rows[] = ['', $this->cell($this->briefing['kesimpulan'])];
            $rows[] = [];
        }

        if (! empty($this->briefing['tindakan_segera'])) {
            $rows[] = ['Tindakan Segera'];
            foreach ($this->briefing['tindakan_segera'] as $action) {
                $rows[] = ['', '• '.$this->cell($action)];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Taklimat';
    }
}
