<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisSumbangan extends Model
{
    use HasFactory;

    protected $table = 'jenis_sumbangan';

    protected $fillable = [
        'nama',
        'bandar_id',
        'sort_order',
    ];

    public function bandar()
    {
        return $this->belongsTo(Bandar::class);
    }

    protected static function booted()
    {
        static::addGlobalScope('ordered', function ($builder) {
            $builder->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
        });
    }
}
