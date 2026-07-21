<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tetapan API electiondata.my — mengikut konvensyen ClaudeSetting:
 * kunci disulitkan, tersembunyi daripada serialisasi, satu baris sahaja.
 */
class ElectionDataSetting extends Model
{
    protected $fillable = ['api_key', 'is_active', 'last_synced_at'];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = ['api_key'];

    public static function current(): ?self
    {
        return self::first();
    }
}
