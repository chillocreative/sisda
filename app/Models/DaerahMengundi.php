<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaerahMengundi extends Model
{
    use HasFactory;

    protected $table = 'daerah_mengundi';

    protected $fillable = ['name', 'mpkk_id'];

    public function mpkk(){
        return $this->belongsTo(MPKK::class);
    }
}
