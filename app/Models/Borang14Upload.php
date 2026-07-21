<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris sejarah bagi setiap scoresheet yang berjaya di-commit.
 *
 * Fail asal disimpan pada disk 'private' (tidak boleh dicapai terus melalui
 * URL) dan hanya dihidangkan melalui laluan muat turun yang menyemak semula
 * skop pengguna — mengikut corak muat naik DPT / keanggotaan sedia ada.
 */
class Borang14Upload extends Model
{
    protected $fillable = [
        'borang14_form_id', 'kawasan_type', 'kawasan_id', 'negeri', 'parlimen', 'dun',
        'nama_fail', 'fail_path', 'jenis_pr', 'tahun', 'source',
        'row_count', 'saluran_count', 'totals', 'needs_review', 'uploaded_by',
    ];

    protected $casts = [
        'totals' => 'array',
        'needs_review' => 'boolean',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Borang14Form::class, 'borang14_form_id');
    }
}
