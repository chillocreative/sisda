<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PilihanrayaForecast extends Model
{
    protected $table = 'pilihanraya_forecasts';

    protected $fillable = [
        'type',
        'scope_level',
        'scope_name',
        'payload',
        'result',
        'status',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
