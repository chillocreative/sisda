<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TujuanSumbangan extends Model
{
    use HasFactory;

    protected $table = 'tujuan_sumbangan';

    protected $fillable = ['name'];
}
