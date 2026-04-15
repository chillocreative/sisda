<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataPengundi extends Model
{
    use HasFactory;

    protected $table = 'data_pengundi';

    protected $fillable = [
        'nama',
        'no_ic',
        'umur',
        'no_tel',
        'bangsa',
        'hubungan',
        'alamat',
        'poskod',
        'negeri',
        'bandar',
        'parlimen',
        'kadun',
        'mpkk',
        'daerah_mengundi',
        'lokaliti',
        'keahlian_parti',
        'kecenderungan_politik',
        'status_pengundi',
        'voter_color',
        'is_deceased',
        'nota',
        'submitted_by',
    ];

    protected $casts = [
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
     * Stacked history of uploaded documents + notes for this voter.
     * Each entry is a manual upload from the Data Pengundi edit form.
     */
    public function documents()
    {
        return $this->hasMany(DataPengundiDocument::class, 'data_pengundi_id')
            ->orderBy('created_at', 'desc');
    }
}
