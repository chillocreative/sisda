<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Negeri extends Model
{
    use HasFactory;

    protected $table = 'negeri';

    protected $fillable = [
        'nama',
    ];

    public function bandar()
    {
        return $this->hasMany(Bandar::class);
    }

    /**
     * Scope a query to Pulau Pinang only. MPKK master data (and the tagging
     * feature built on it) only exists for Pulau Pinang; naming in seeders
     * is inconsistent ("Pulau Pinang" vs "PULAU PINANG") so match case-
     * insensitively, same convention as StateElectoralSeeder.
     */
    public function scopePulauPinang($query)
    {
        return $query->whereRaw('UPPER(TRIM(nama)) = ?', ['PULAU PINANG']);
    }
}
