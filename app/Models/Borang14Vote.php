<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Borang14Vote extends Model
{
    public const CONTEST_DUN = 'dun';

    public const CONTEST_PARLIMEN = 'parlimen';

    /**
     * Julat slot CALON. Di luar julat ini hanya ada slot khas 90 (undi ditolak)
     * dan 91 (undi tidak dimasukkan) — bukan undi mana-mana calon, jadi apa-apa
     * yang mengira "adakah kerusi ini sudah melapor keputusan calon?" mesti
     * mengabaikannya. Lihat Borang14RollUp::jumlahSlot().
     */
    public const SLOT_CALON_MIN = 1;

    public const SLOT_CALON_MAX = 6;

    protected $fillable = ['borang14_form_id', 'contest', 'pusat', 'saluran', 'slot', 'undi'];

    protected $casts = [
        'slot' => 'integer',
        'undi' => 'integer',
    ];

    /**
     * Jaring keselamatan pada CIPTAAN (INSERT) SAHAJA — BUKAN kebenaran untuk
     * penulis serentak, dan BUKAN perlindungan pada KEMASKINI (UPDATE).
     *
     * Migrasi 2026_08_01 menjadikan `contest` NOT NULL, tetapi penulis SEDIA
     * ADA (Borang14Controller::saveVote/putVote/revert, seeder, dsb.) tidak
     * pernah menghantar `contest` — mereka dibina sebelum lajur ini wujud.
     * Untuk borang SATU pertandingan (kes biasa hari ini: DUN sahaja ATAU
     * Parlimen sahaja), kawasan_type borang itu SENDIRI ialah satu-satunya
     * jawapan yang mungkin betul, jadi ia selamat dijadikan lalai — tetapi
     * HANYA pada event `creating`, iaitu HANYA apabila baris itu baharu.
     *
     * PENTING — ini TIDAK melindungi borang SERENTAK (satu borang merekod
     * KEDUA-DUA PRU dan PRN) daripada DUA cara yang berbeza:
     *
     * 1. Pada INSERT: mengabaikan `contest` menulis secara senyap ke
     *    kawasan_type borang itu, yang hanya SATU daripada dua jawapan sah.
     *
     * 2. Pada UPDATE melalui updateOrCreate() — dan inilah yang lebih
     *    berbahaya: kunci padanan `saveVote`/`putVote` MASA INI
     *    (Borang14Controller.php ~:212, ~:1408) TIDAK termasuk `contest`.
     *    Pada borang serentak, kunci itu (form, pusat, saluran, slot) akan
     *    memadankan MANA-MANA baris sedia ada bagi sel itu — pertandingan
     *    yang mana pun ia — lalu mengambil laluan UPDATE terus. Hook
     *    `creating` di bawah LANGSUNG TIDAK BERJALAN pada laluan itu, jadi
     *    ia TIDAK melalaikan mahupun melindungi apa-apa; baris pertandingan
     *    yang SALAH boleh ditimpa secara senyap.
     *
     * Pengesahan (validation) semata-mata pada sempadan HTTP (cth. menjadikan
     * `contest` medan WAJIB pada permintaan) TIDAK MENCUKUPI untuk kes #2 —
     * ia hanya menghalang permintaan tanpa `contest` daripada sampai ke sini
     * langsung; ia tidak mengubah kunci padanan updateOrCreate() itu sendiri.
     * Task 2 MESTI melebarkan kunci padanan `saveVote`/`putVote` untuk turut
     * merangkumi `contest` sebelum borang serentak selamat ditulis/dikemas
     * kini — validation sahaja tidak menutup lubang ini.
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
