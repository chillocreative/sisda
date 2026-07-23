<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PacaPusat extends Model
{
    protected $table = 'paca_pusat';

    protected $guarded = [];

    public function form(): BelongsTo
    {
        // Kunci asing DIEKSPLISIT — tekaan lalai Laravel bagi kaedah
        // bernama form() ialah 'form_id', tetapi lajur sebenar (migrasi
        // Tugasan 1) ialah 'paca_form_id'. Tanpa ini, eager-load senyap
        // memulangkan null (bukan ralat SQL, kerana lajur 'form_id' langsung
        // tiada pada model ini) — Tugasan 4 (tambahSaluran/tambahSlot yang
        // menyusuri pusat->form) pecah dengan "Attempt to read property...
        // on null" tanpa petunjuk sebabnya.
        return $this->belongsTo(PacaForm::class, 'paca_form_id');
    }

    public function saluranList(): HasMany
    {
        return $this->hasMany(PacaSaluran::class)->orderBy('urutan');
    }
}
