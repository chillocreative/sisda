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
        'status_sumbangan',
        'no_rujukan',
        'tarikh_sumbangan',
        'jumlah_dipohon',
        'jumlah_dilulus',
        'jumlah_dibayar',
        'bantuan_lain',
        'perkeso_bantuan',
        'zpp_jenis_bantuan',
        'isejahtera_program',
        'bkb_program',
        'jumlah_bantuan_tunai',
        'jumlah_wang_tunai',
        'jkm_program',
        'keahlian_parti',
        'kecenderungan_politik',
        'status_pengundi',
        'voter_color',
        'kad_pengenalan',
        'nota',
        'is_deceased',
        'submitted_by',
        'sumber',
    ];

    protected $casts = [
        'pendapatan_isi_rumah' => 'decimal:2',
        'tarikh_sumbangan' => 'date',
        'jumlah_dipohon' => 'decimal:2',
        'jumlah_dilulus' => 'decimal:2',
        'jumlah_dibayar' => 'decimal:2',
        'is_deceased' => 'boolean',
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

    /**
     * Rows that actually represent a sumbangan/aid record — a reason (tujuan)
     * is set, or a real form (jenis, other than the "Tiada" placeholder).
     * Shared by the dashboard "Penerima Sumbangan" card and the Data Pengundi
     * table indicator so both count the same thing.
     */
    public function scopeSumbangan($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($w) {
                $w->whereNotNull('tujuan_sumbangan')->where('tujuan_sumbangan', '!=', '');
            })->orWhere(function ($w) {
                $w->whereNotNull('jenis_sumbangan')
                    ->where('jenis_sumbangan', '!=', '')
                    ->where('jenis_sumbangan', '!=', 'Tiada');
            });
        });
    }
}
