<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MPKK extends Model
{
    use HasFactory;

    protected $table = 'mpkk';

    protected $fillable = ['name', 'kadun_id'];

    public function kadun(){
        return $this->belongsTo(Kadun::class);
    }
}
