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
        'parlimen',
        'kadun',
        'mpkk',
        'daerah_mengundi',
        'lokaliti',
        'bil_isi_rumah',
        'pendapatan_isi_rumah',
        'pekerjaan',
        'jenis_pekerjaan',
        'jenis_pekerjaan_lain',
        'pemilik_rumah',
        'jenis_sumbangan',
        'tujuan_sumbangan',
        'bantuan_lain',
        'zpp_jenis_bantuan',
        'isejahtera_program',
        'bkb_program',
        'jumlah_bantuan_tunai',
        'jumlah_wang_tunai',
        'jkm_program',
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
