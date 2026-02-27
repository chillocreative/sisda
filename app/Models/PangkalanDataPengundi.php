<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PangkalanDataPengundi extends Model
{
    protected $table = 'pangkalan_data_pengundi';

    protected $fillable = [
        'upload_batch_id',
        'no_ic',
        'nama',
        'lokaliti',
        'daerah_mengundi',
        'kadun',
        'parlimen',
        'negeri',
        'bangsa',
    ];

    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(UploadBatch::class);
    }
}
