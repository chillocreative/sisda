<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kerusi rasmi SPR (222 Parlimen + 600 DUN) seperti diterbitkan electiondata.my.
 *
 * kadun_id/bandar_id ialah cache padanan kepada geografi SISDA, bukan kunci
 * asing berkuatkuasa — geografi SISDA dipadan mengikut rentetan, jadi padanan
 * boleh gagal dan kekal null.
 */
class ElectionSeat extends Model
{
    public const JENIS_DUN = 'dun';

    public const JENIS_PARLIMEN = 'parlimen';

    protected $fillable = [
        'slug', 'nama', 'kod', 'negeri', 'jenis', 'kadun_id', 'bandar_id', 'synced_at',
    ];

    protected $casts = ['synced_at' => 'datetime'];

    public function results(): HasMany
    {
        return $this->hasMany(ElectionSeatResult::class);
    }

    /**
     * Keputusan LENGKAP terkini. Pilihan raya akan datang dipulangkan oleh API
     * tanpa keputusan (party null) — ia bukan garis dasar dan mesti diabaikan di
     * sini, jika tidak kad garis dasar akan memaparkan pilihan raya yang belum
     * berlaku sebagai keputusan sebenar.
     */
    public function latestCompletedResult(): ?ElectionSeatResult
    {
        return $this->results()
            ->whereNotNull('party')
            ->orderByDesc('tarikh')
            ->first();
    }
}
