<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tetapan paparan papan markah bagi SATU KERUSI (Parlimen atau DUN).
 * Angka undi sebenar dibaca daripada borang14_votes; jadual ini hanya memegang
 * konfigurasi persembahan dan pilihan sumber undi.
 */
class Scoreboard extends Model
{
    public const STATUS_DRAF = 'draf';

    public const STATUS_TERSIAR = 'tersiar';

    protected $fillable = [
        'kawasan_type', 'kawasan_id', 'borang14_form_id',
        'title', 'minima', 'status', 'kod', 'logo_path', 'candidates', 'pihak_kami', 'updated_by',
    ];

    protected $casts = [
        'minima' => 'integer',
        'candidates' => 'array',
        'pihak_kami' => 'array',
    ];

    public function borang14Form(): BelongsTo
    {
        return $this->belongsTo(Borang14Form::class, 'borang14_form_id');
    }

    public function penyunting(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isTersiar(): bool
    {
        return $this->status === self::STATUS_TERSIAR;
    }
}
