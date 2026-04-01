<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lokaliti extends Model
{
    protected $table = 'lokaliti';
    protected $fillable = ['nama', 'daerah_mengundi_id'];

    public function daerahMengundi()
    {
        return $this->belongsTo(DaerahMengundi::class);
    }
}
