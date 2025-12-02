<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HasilCulaan extends Model
{
    use HasFactory;

    protected $table = 'hasil_culaan';

    protected $fillable = [
        'nama',
        'no_ic',
        'umur',
        'no_tel',
        'bangsa',
        'alamat',
        'poskod',
        'negeri',
        'bandar',
        'kadun',
        'daerah_mengundi',
        'bil_isi_rumah',
        'pendapatan_isi_rumah',
        'pekerjaan',
        'pemilik_rumah',
        'jenis_sumbangan',
        'tujuan_sumbangan',
        'bantuan_lain',
        'keahlian_parti',
        'kecenderungan_politik',
        'kad_pengenalan',
        'nota',
        'submitted_by',
    ];

    protected $casts = [
        'pendapatan_isi_rumah' => 'decimal:2',
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
