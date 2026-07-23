<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PacaSaluran extends Model
{
    protected $table = 'paca_saluran';

    protected $guarded = [];

    public function pusat(): BelongsTo
    {
        return $this->belongsTo(PacaPusat::class, 'paca_pusat_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PacaSlot::class, 'paca_saluran_id')->orderBy('urutan');
    }
}
