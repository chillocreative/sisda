<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacaSlot extends Model
{
    protected $table = 'paca_slot';

    protected $guarded = [];

    public function saluran(): BelongsTo
    {
        return $this->belongsTo(PacaSaluran::class, 'paca_saluran_id');
    }
}
