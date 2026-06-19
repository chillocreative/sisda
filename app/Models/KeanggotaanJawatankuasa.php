<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeanggotaanJawatankuasa extends Model
{
    protected $table = 'keanggotaan_jawatankuasa';

    public const JENIS = ['JPRC', 'JPRD', 'AJK_CABANG', 'WANITA', 'AMK'];

    protected $fillable = [
        'no_ic',
        'nama',
        'jenis',
        'jawatan',
        'cabang',
        'dun',
        'no_tel',
        'matched_kadun',
        'matched_parlimen',
        'matched_negeri',
        'tahun_lahir',
        'umur',
        'bangsa',
        'jantina',
        'voter_color',
        'is_dicula',
        'is_pendaftaran_baru',
        'status_kawasan',
    ];

    protected $casts = [
        'is_dicula' => 'boolean',
        'is_pendaftaran_baru' => 'boolean',
    ];
}
