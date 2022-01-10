<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MulaCulaan extends Model
{
    use HasFactory;

    protected $table = 'mula_culaan';

    protected $fillable = ['user_id', 'alamat', 'kadun', 'mpkk', 'bilangan_isi_rumah', 'jumlah_pendapatan_isi_rumah', 'jenis_sumbangan', 'tujuan_sumbangan', 'bantuan_lain', 'keahlian_partai', 'kecenderungan_politik', 'nota', 'tarikh_dan_masa'];

    protected $dates = ['tarikh_dan_masa'];
}
