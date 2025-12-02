<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TujuanSumbangan extends Model
{
    use HasFactory;

    protected $table = 'tujuan_sumbangan';

    protected $fillable = [
        'nama',
        'bandar_id',
        'sort_order',
    ];

    public function bandar()
    {
        return $this->belongsTo(Bandar::class);
    }

    /**
     * Default ordering by sort_order
     */
    protected static function booted()
    {
        static::addGlobalScope('ordered', function ($builder) {
            $builder->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
        });
    }
}
