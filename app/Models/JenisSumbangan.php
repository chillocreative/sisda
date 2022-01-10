<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisSumbangan extends Model
{
    use HasFactory;

    protected $table = 'jenis_sumbangan';

    protected $fillable = ['name'];
}
