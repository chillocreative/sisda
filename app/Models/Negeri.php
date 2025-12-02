<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Negeri extends Model
{
    use HasFactory;

    protected $table = 'negeri';

    protected $fillable = [
        'nama',
    ];

    public function bandar()
    {
        return $this->hasMany(Bandar::class);
    }
}
