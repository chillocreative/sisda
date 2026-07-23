<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PacaForm extends Model
{
    protected $guarded = [];

    public function pusatList(): HasMany
    {
        return $this->hasMany(PacaPusat::class)->orderBy('urutan');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PacaSnapshot::class);
    }
}
