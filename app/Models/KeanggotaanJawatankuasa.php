<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeanggotaanJawatankuasa extends Model
{
    protected $table = 'keanggotaan_jawatankuasa';

    // JPRC (parliament/cabang level), JPRD (one per DUN), the party wings
    // AJK_CABANG (labelled "Cabang") / WANITA / AMK, plus MPKK, JBPP and JPWK.
    public const JENIS = ['JPRC', 'JPRD', 'AJK_CABANG', 'WANITA', 'AMK', 'MPKK', 'JBPP', 'JPWK'];

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

    /**
     * Pull the DUN name out of a jawatan string when the position is tied to a
     * state seat, e.g. "SETIAUSAHA DUN PINANG TUNGGAL" => "PINANG TUNGGAL".
     * Parliament-level positions (no "DUN <name>") return null.
     */
    public static function extractDunFromJawatan(?string $jawatan): ?string
    {
        if (! $jawatan) {
            return null;
        }

        if (preg_match('/\bA?DUN\s+([A-Za-z][A-Za-z\'.\s]+?)\s*$/i', trim($jawatan), $m)) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }
}
