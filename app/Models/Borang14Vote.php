<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Borang14Vote extends Model
{
    protected $fillable = ['borang14_form_id', 'pusat', 'saluran', 'slot', 'undi'];

    protected $casts = [
        'slot' => 'integer',
        'undi' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Borang14Form::class, 'borang14_form_id');
    }
}
