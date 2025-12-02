<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mpkk extends Model
{
    use HasFactory;

    protected $table = 'mpkk';

    protected $fillable = [
        'nama',
        'kadun_id',
    ];

    public function kadun()
    {
        return $this->belongsTo(Kadun::class);
    }

    public function dataPengundi()
    {
        return $this->hasMany(DataPengundi::class, 'mpkk_id');
    }
}
