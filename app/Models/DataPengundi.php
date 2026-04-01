<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataPengundi extends Model
{
    use HasFactory;

    protected $table = 'data_pengundi';

    protected $fillable = ['name', 'no_kad', 'umur', 'phone', 'bangsa', 'hubungan', 'alamat', 'poskod', 'negeri', 'bandar', 'parlimen', 'kadun', 'mpkk', 'daerah_mengundi', 'lokaliti', 'keahlian_partai', 'kecenderungan_politik', 'user_id', 'is_draft', 'post_id'];

    protected $attributes = [
        'keahlian_partai' => '-',
        'kecenderungan_politik' => '-',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
