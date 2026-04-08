<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PangkalanDataPengundi extends Model
{
    protected $table = 'pangkalan_data_pengundi';

    protected $fillable = [
        'upload_batch_id',
        'dpt_upload_id',
        'no_ic',
        'nama',
        'lokaliti',
        'kod_lokaliti',
        'daerah_mengundi',
        'kadun',
        'parlimen',
        'negeri',
        'bangsa',
        'jantina',
        'tahun_lahir',
        'is_deceased',
    ];

    protected $casts = [
        'is_deceased' => 'boolean',
    ];

    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(UploadBatch::class);
    }
}
