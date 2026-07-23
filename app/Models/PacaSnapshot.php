<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacaSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $snapshot) {
            $snapshot->created_at ??= now();
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(PacaForm::class);
    }
}
