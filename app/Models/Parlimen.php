<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parlimen extends Model
{
    protected $table = 'parlimen';
    protected $fillable = ['nama', 'kod_parlimen', 'negeri_id', 'bandar_id'];
}
