<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bandar extends Model
{
    use HasFactory;

    protected $table = 'bandar';

    protected $fillable = [
        'nama',
        'kod_parlimen',
        'negeri_id',
    ];

    public function negeri()
    {
        return $this->belongsTo(Negeri::class);
    }

    public function kadun()
    {
        return $this->hasMany(Kadun::class);
    }
}
