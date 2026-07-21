<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu keputusan pilihan raya bagi satu kerusi.
 *
 * SETIAP lajur angka boleh null. Pilihan raya akan datang wujud dalam data
 * dengan setiap angka null — jangan sekali-kali menganggapnya 0.
 */
class ElectionSeatResult extends Model
{
    protected $fillable = [
        'election_seat_id', 'election_name', 'tarikh', 'party', 'party_uid', 'coalition',
        'candidate', 'majority', 'majority_perc', 'voter_turnout', 'voter_turnout_perc',
        'voters_total', 'votes_rejected', 'votes_rejected_perc', 'ballot', 'synced_at',
    ];

    protected $casts = [
        'tarikh' => 'date',
        'ballot' => 'array',
        'synced_at' => 'datetime',
    ];

    public function seat(): BelongsTo
    {
        return $this->belongsTo(ElectionSeat::class, 'election_seat_id');
    }

    /** Adakah keputusan ini benar-benar berlaku (berbanding pilihan raya akan datang)? */
    public function isCompleted(): bool
    {
        return $this->party !== null;
    }
}
