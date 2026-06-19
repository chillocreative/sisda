<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Keanggotaan extends Model
{
    protected $table = 'keanggotaan';

    protected $fillable = [
        'batch_id',
        'no_ic',
        'nama',
        'no_tel',
        'matched_kadun',
        'matched_parlimen',
        'matched_negeri',
        'tahun_lahir',
        'umur',
        'bangsa',
        'jantina',
        'voter_color',
        'is_dicula',
        'is_pendaftaran_baru',
        'status_kawasan',
    ];

    protected $casts = [
        'is_dicula' => 'boolean',
        'is_pendaftaran_baru' => 'boolean',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(KeanggotaanBatch::class, 'batch_id');
    }
}
