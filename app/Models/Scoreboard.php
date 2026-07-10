<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scoreboard extends Model
{
    protected $fillable = ['kadun_id', 'penjuru', 'title', 'minima', 'logo_path', 'candidates'];

    protected $casts = [
        'penjuru'    => 'integer',
        'minima'     => 'integer',
        'candidates' => 'array',
    ];
}
