<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadBatch extends Model
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

    public function pangkalanDataPengundi(): HasMany
    {
        return $this->hasMany(PangkalanDataPengundi::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
