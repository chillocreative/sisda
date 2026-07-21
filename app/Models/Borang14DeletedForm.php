<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Arkib satu rekod Borang 14 yang dipadam — undi, struktur dan pemetaan parti
 * disimpan seadanya supaya padaman tersilap masih boleh dipulihkan.
 */
class Borang14DeletedForm extends Model
{
    protected $fillable = [
        'kawasan_type', 'kawasan_id', 'kawasan_nama', 'jenis_pr', 'tahun',
        'status', 'structure', 'votes', 'parties', 'deleted_by',
    ];

    protected $casts = [
        'structure' => 'array',
        'votes' => 'array',
        'parties' => 'array',
    ];
}
