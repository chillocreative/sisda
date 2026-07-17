<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Borang14Snapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['borang14_form_id', 'structure', 'votes', 'parties', 'reason', 'created_by'];

    protected $casts = [
        'structure' => 'array',
        'votes' => 'array',
        'parties' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Borang14Form::class, 'borang14_form_id');
    }
}
