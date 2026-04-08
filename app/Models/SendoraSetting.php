<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SendoraSetting extends Model
{
    protected $fillable = [
        'api_url', 'api_token', 'device_id', 'admin_phone', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_token' => 'encrypted',
    ];

    protected $hidden = ['api_token'];

    public static function current(): ?self
    {
        return self::first();
    }
}
