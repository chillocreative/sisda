<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parlimen extends Model
{
    use HasFactory;

    protected $table = 'parlimen';

    protected $fillable = ['code', 'name', 'negeri_id'];

    public function negeri(){
        return $this->belongsTo(Negeri::class);
    }

    public function kadun(){
        return $this->hasMany(Kadun::class);
    }
}
