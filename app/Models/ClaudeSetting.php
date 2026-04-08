<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaudeSetting extends Model
{
    protected $fillable = [
        'api_key', 'model', 'max_tokens', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
    ];

    protected $hidden = ['api_key'];

    public static function current(): ?self
    {
        return self::first();
    }
}
