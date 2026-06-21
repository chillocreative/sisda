<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton settings for the Keanggotaan module. Currently holds the active
 * "Penggal Pemilihan Parti" (party-election term) as a year range, used to
 * decide AMK/Srikandi/Wanita wing eligibility (see MemberWingService).
 */
class KeanggotaanSetting extends Model
{
    protected $table = 'keanggotaan_settings';

    protected $fillable = ['tahun_mula', 'tahun_tamat'];

    protected $casts = [
        'tahun_mula' => 'integer',
        'tahun_tamat' => 'integer',
    ];

    /** The single settings row, created empty on first access. */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
