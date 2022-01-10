<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KecenderunganPolitik extends Model
{
    use HasFactory;

    protected $table = 'kecenderungan_politik';

    protected $fillable = ['name'];
}
