<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hubungan extends Model
{
    use HasFactory;

    protected $table = 'hubungan';

    protected $fillable = ['name'];
}
