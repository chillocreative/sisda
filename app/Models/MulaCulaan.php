<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MulaCulaan extends Model
{
    use HasFactory;

    protected $table = 'mula_culaan';

    protected $fillable = ['user_id', 'nama', 'no_kad', 'umur', 'no_telp', 'bangsa', 'alamat', 'alamat_2', 'poskod', 'negeri', 'bandar', 'kadun', 'mpkk', 'bilangan_isi_rumah', 'jumlah_pendapatan_isi_rumah', 'pekerjaan', 'pemilik_rumah', 'jenis_sumbangan', 'tujuan_sumbangan', 'bantuan_lain', 'keahlian_partai', 'kecenderungan_politik', 'nota', 'tarikh_dan_masa', 'ic', 'ic_url'];

    protected $dates = ['tarikh_dan_masa'];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
