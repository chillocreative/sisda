<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeanggotaanJawatankuasa extends Model
{
    protected $table = 'keanggotaan_jawatankuasa';

    // Only JPRC (parliament/cabang level) and JPRD (one per DUN) are used. The
    // DB enum still permits the older wing values, but the app no longer offers
    // them.
    public const JENIS = ['JPRC', 'JPRD'];

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
