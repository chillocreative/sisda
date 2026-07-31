<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Borang14Vote extends Model
{
    public const CONTEST_DUN = 'dun';

    public const CONTEST_PARLIMEN = 'parlimen';

    protected $fillable = ['borang14_form_id', 'contest', 'pusat', 'saluran', 'slot', 'undi'];

    protected $casts = [
        'slot' => 'integer',
        'undi' => 'integer',
    ];

    /**
     * Jaring keselamatan, BUKAN kebenaran untuk penulis serentak.
     *
     * Migrasi 2026_08_01 menjadikan `contest` NOT NULL, tetapi penulis
     * SEDIA ADA (Borang14Controller::saveVote/putVote/revert, seeder, dsb.)
     * tidak pernah menghantar `contest` — mereka dibina sebelum lajur ini
     * wujud. Untuk borang SATU pertandingan (kes biasa hari ini: DUN sahaja
     * ATAU Parlimen sahaja), kawasan_type borang itu SENDIRI ialah satu-
     * satunya jawapan yang mungkin betul, jadi ia selamat dijadikan lalai.
     *
     * Ini TIDAK memberi kebenaran kepada penulis borang SERENTAK (satu
     * borang merekod KEDUA-DUA PRU dan PRN) untuk mengabaikan `contest`.
     * Pada borang sedemikian, kawasan_type borang itu hanya SATU daripada
     * dua jawapan yang sah — mengabaikan `contest` di situ akan menulis
     * secara senyap ke pertandingan yang SALAH. Sebab itu Task 2 menjadikan
     * `contest` medan WAJIB pada sempadan HTTP: supaya pemanggil yang lupa
     * menghantarnya gagal dengan jelas (422), bukan diberi lalai senyap.
     */
    protected static function booted(): void
    {
        static::creating(function (self $vote) {
            if (! empty($vote->contest)) {
                return;
            }

            $vote->contest = static::query()
                ->getConnection()
                ->table('borang14_forms')
                ->where('id', $vote->borang14_form_id)
                ->value('kawasan_type');
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Borang14Form::class, 'borang14_form_id');
    }
}
