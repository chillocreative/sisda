<?php

namespace App\Exports;

use App\Exports\Sheets\BriefingSummarySheet;
use App\Exports\Sheets\SeatScoresSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PilihanrayaBriefingExport implements WithMultipleSheets
{
    public function __construct(
        protected array $briefing,
        protected array $seatScores = [],
    ) {}

    public function sheets(): array
    {
        $sheets = [new BriefingSummarySheet($this->briefing)];

        if (! empty($this->seatScores)) {
            $sheets[] = new SeatScoresSheet($this->seatScores);
        }

        return $sheets;
    }
}
