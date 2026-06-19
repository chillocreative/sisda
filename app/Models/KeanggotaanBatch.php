<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeanggotaanBatch extends Model
{
    protected $fillable = [
        'nama_fail',
        'fail_path',
        'jumlah_rekod',
        'status',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function keanggotaan(): HasMany
    {
        return $this->hasMany(Keanggotaan::class, 'batch_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** IDs of all active membership batches (the active set is their union). */
    public static function activeIds(): array
    {
        return static::active()->pluck('id')->all();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
