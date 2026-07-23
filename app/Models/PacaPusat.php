<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PacaPusat extends Model
{
    protected $table = 'paca_pusat';

    protected $guarded = [];

    public function form(): BelongsTo
    {
        return $this->belongsTo(PacaForm::class);
    }

    public function saluranList(): HasMany
    {
        return $this->hasMany(PacaSaluran::class)->orderBy('urutan');
    }
}
