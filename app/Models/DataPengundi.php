<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataPengundi extends Model
{
    use HasFactory;

    protected $table = 'data_pengundi';

    protected $fillable = [
        'nama',
        'no_ic',
        'umur',
        'no_tel',
        'bangsa',
        'hubungan',
        'alamat',
        'poskod',
        'negeri',
        'bandar',
        'parlimen',
        'kadun',
        'mpkk',
        'daerah_mengundi',
        'keahlian_parti',
        'kecenderungan_politik',
        'submitted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who submitted this record.
     */
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
