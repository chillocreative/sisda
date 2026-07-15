<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved AI comparison of 1–3 past/current elections on the Analisa
 * Keputusan page. See the create migration for the column semantics.
 */
class AnalisaComparison extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'kawasan_id',
        'dun',
        'parlimen',
        'status',
        'fact_payload',
        'ai_result',
        'ai_status',
        'ai_model',
        'ai_generated_at',
        'web_search_count',
    ];

    protected $casts = [
        'fact_payload' => 'array',
        'ai_result' => 'array',
        'ai_generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Soft-deleting a comparison hides it from the list; drop its scenarios
        // too (there is no restore UI) so orphan rows don't accumulate.
        static::deleting(function (self $comparison) {
            $comparison->scenarios()->delete();
        });
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(AnalisaScenario::class)->orderBy('position');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
