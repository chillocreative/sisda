<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataPengundiDocument extends Model
{
    use HasFactory;

    protected $table = 'data_pengundi_documents';

    protected $fillable = [
        'data_pengundi_id',
        'file_path',
        'nota',
        'submitted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dataPengundi()
    {
        return $this->belongsTo(DataPengundi::class, 'data_pengundi_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
