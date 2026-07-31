<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Borang14Form extends Model
{
    public const KAWASAN_PARLIMEN = 'parlimen';
    public const KAWASAN_DUN = 'dun';

    protected $fillable = [
        'kawasan_type', 'kawasan_id', 'jenis_pr', 'tahun', 'penjuru',
        'parties', 'structure', 'status', 'source', 'source_filename',
        'needs_review', 'published_at', 'borang14_form_parlimen_id',
    ];

    protected $casts = [
        'parties' => 'array',
        'structure' => 'array',
        'needs_review' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(Borang14Vote::class);
    }

    /**
     * Borang Parlimen yang menakrifkan pertandingan PRU bagi borang DUN ini.
     * Null bermakna borang satu pertandingan sahaja (kes biasa).
     */
    public function formParlimen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'borang14_form_parlimen_id');
    }

    /** Borang DUN yang merekod pertandingan Parlimen ini. */
    public function borangDun(): HasMany
    {
        return $this->hasMany(self::class, 'borang14_form_parlimen_id');
    }

    /**
     * Undi bagi SATU pertandingan sahaja.
     *
     * Gunakan ini dan BUKAN votes() di mana-mana yang mengira angka: pada borang
     * serentak, votes() memulangkan undi PRU DAN PRN bercampur, lalu menjumlahkan
     * kira-kira dua kali ganda.
     */
    public function votesFor(string $contest): HasMany
    {
        return $this->votes()->where('contest', $contest);
    }

    /** Pertandingan borang ini sendiri — sama dengan kawasannya. */
    public function contestSendiri(): string
    {
        return $this->kawasan_type === self::KAWASAN_PARLIMEN
            ? Borang14Vote::CONTEST_PARLIMEN
            : Borang14Vote::CONTEST_DUN;
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Borang14Snapshot::class);
    }

    /** Polymorphic tanpa FK — kawasan_type menentukan jadual sasaran. */
    public function kawasan(): Bandar|Kadun|null
    {
        return $this->kawasan_type === self::KAWASAN_PARLIMEN
            ? Bandar::find($this->kawasan_id)
            : Kadun::find($this->kawasan_id);
    }

    public function kawasanNama(): string
    {
        return $this->kawasan()?->nama ?? '—';
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeForKawasan(Builder $q, string $type, int $id): Builder
    {
        return $q->where('kawasan_type', $type)->where('kawasan_id', $id);
    }
}
