<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kadun extends Model
{
    use HasFactory;

    protected $table = 'kadun';

    protected $fillable = ['code', 'name', 'parlimen_id'];

    public function mpkk(){
        return $this->hasMany(MPKK::class);
    }

    public function parlimen(){
        return $this->belongsTo(Parlimen::class);
    }
}
