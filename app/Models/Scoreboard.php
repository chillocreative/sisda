<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scoreboard extends Model
{
    protected $fillable = ['borang14_form_id', 'title', 'minima', 'logo_path', 'candidates'];

    protected $casts = [
        'minima'     => 'integer',
        'candidates' => 'array',
    ];
}
