<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bangsa extends Model
{
    protected $fillable = ['nama', 'sort_order'];
    
    protected $table = 'bangsa';

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
