<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacaSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        // Tanpa cast ini, $timestamps=false bermakna created_at pulang sebagai
        // rentetan mentah, bukan Carbon — sejarah PACA (Tugasan 4/5) yang
        // memanggil ->diffForHumans()/->format() akan gagal. Setiap model
        // bertarikh lain dalam app mengembalikan Carbon; ini menyamainya.
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $snapshot) {
            $snapshot->created_at ??= now();
        });
    }

    public function form(): BelongsTo
    {
        // Kunci asing DIEKSPLISIT — sama seperti PacaPusat::form(), tekaan
        // lalai ('form_id') tidak sepadan dengan lajur migrasi sebenar
        // ('paca_form_id'); tanpa ini eager-load senyap memulangkan null.
        return $this->belongsTo(PacaForm::class, 'paca_form_id');
    }
}
