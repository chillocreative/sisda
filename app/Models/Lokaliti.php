<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lokaliti extends Model
{
    use HasFactory;

    protected $table = 'lokaliti';

    protected $fillable = ['name', 'daerah_mengundi_id'];

    public function daerahMengundi(){
        return $this->belongsTo(DaerahMengundi::class);
    }
}
