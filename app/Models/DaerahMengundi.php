<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DaerahMengundi extends Model
{
    protected $table = 'daerah_mengundi';

    protected $fillable = [
        'kod_dm',
        'nama',
        'bandar_id',
    ];

    public function bandar()
    {
        return $this->belongsTo(Bandar::class);
    }
}
