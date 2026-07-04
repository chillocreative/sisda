<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CulaanUpload extends Model
{
    protected $fillable = [
        'nama_fail', 'fail_path', 'file_hash', 'status', 'error', 'jumlah_baris',
        'matched', 'dicipta', 'dikemaskini', 'tidak_dijumpai', 'taksah', 'tiada_sentimen',
        'report', 'uploaded_by',
    ];

    protected $casts = [
        'report' => 'array',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
