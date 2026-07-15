<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One election within an AnalisaComparison — an uploaded scoresheet
 * (parsed to rows/totals) plus a human label and the election date.
 */
class AnalisaScenario extends Model
{
    protected $fillable = [
        'analisa_comparison_id',
        'position',
        'label',
        'election_date',
        'source_filename',
        'parsed_rows',
        'parsed_totals',
        'row_count',
    ];

    protected $casts = [
        'parsed_rows' => 'array',
        'parsed_totals' => 'array',
        'election_date' => 'date',
    ];

    public function comparison(): BelongsTo
    {
        return $this->belongsTo(AnalisaComparison::class, 'analisa_comparison_id');
    }
}
