<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kadun extends Model
{
    use HasFactory;

    protected $table = 'kadun';

    protected $fillable = [
        'nama',
        'kod_dun',
        'bandar_id',
    ];

    public function bandar()
    {
        return $this->belongsTo(Bandar::class);
    }

    public function mpkk()
    {
        return $this->hasMany(Mpkk::class);
    }
}
