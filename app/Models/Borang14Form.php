<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Borang14Form extends Model
{
    protected $fillable = ['kadun_id', 'penjuru', 'parties'];

    protected $casts = [
        'parties' => 'array',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(Borang14Vote::class);
    }
}
