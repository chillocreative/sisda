<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DptUpload extends Model
{
    protected $fillable = [
        'filename', 'label', 'parlimen', 'negeri', 'bulan', 'tahun',
        'tarikh_warta', 'total_records', 'total_deceased', 'total_new',
        'total_moved', 'status', 'error', 'uploaded_by',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
