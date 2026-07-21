<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Keanggotaan extends Model
{
    protected $table = 'keanggotaan';

    protected $fillable = [
        'batch_id',
        'no_anggota',
        'no_ic',
        'nama',
        'no_tel',
        'cabang',
        'dun',
        'negeri',
        'alamat',
        'matched_kadun',
        'matched_parlimen',
        'matched_negeri',
        'matched_daerah_mengundi',
        'matched_lokaliti',
        'tahun_lahir',
        'umur',
        'bangsa',
        'jantina',
        'voter_color',
        'is_dicula',
        'is_pendaftaran_baru',
        'status_kawasan',
        'status_anggota',
        'status_ekyc',
        'daftar_tanpa_pengetahuan',
    ];

    protected $casts = [
        'is_dicula' => 'boolean',
        'is_pendaftaran_baru' => 'boolean',
        'daftar_tanpa_pengetahuan' => 'boolean',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(KeanggotaanBatch::class, 'batch_id');
    }

    /**
     * The one definition of an "Aktif / EKYC" member.
     *
     * The uploaded file's own STATUS EKYC column decides whenever it says
     * anything: 'completed' counts, 'pending' does not — a pending member in an
     * EKYC-flagged batch is still pending. Only when the file was silent
     * (status_ekyc NULL: older batches, PDF imports, files without the column)
     * does the original batch-level rule apply, so no existing figure moves.
     *
     * @param  array<int, int>  $ekycBatchIds  ids of batches flagged is_ekyc
     */
    public function scopeEkycVerified($query, array $ekycBatchIds)
    {
        return $query->where(function ($q) use ($ekycBatchIds) {
            $q->where('status_ekyc', 'completed')
                ->orWhere(function ($f) use ($ekycBatchIds) {
                    $f->whereNull('status_ekyc')
                        ->where(function ($old) use ($ekycBatchIds) {
                            $old->where('status_anggota', 'aktif')
                                ->when($ekycBatchIds, fn ($b) => $b->orWhereIn('batch_id', $ekycBatchIds));
                        });
                });
        });
    }

    /**
     * The same rule evaluated in PHP, for loops over already-fetched rows.
     *
     * @param  array<int, mixed>  $ekycBatchIdSet  batch ids flipped to keys
     */
    public static function rowIsEkycVerified(?string $statusEkyc, ?string $statusAnggota, ?int $batchId, array $ekycBatchIdSet): bool
    {
        if ($statusEkyc !== null) {
            return $statusEkyc === 'completed';
        }

        return $statusAnggota === 'aktif' || isset($ekycBatchIdSet[$batchId]);
    }

    /**
     * The same rule as raw SQL, for aggregates like
     * SUM(CASE WHEN <expr> THEN 1 ELSE 0 END). Plain SQL only, so it runs on
     * both SQLite (CI) and MySQL (production).
     *
     * @param  array<int, int>  $ekycBatchIds
     * @return array{0: string, 1: array<int, mixed>} [expression, bindings]
     */
    public static function ekycSql(array $ekycBatchIds): array
    {
        $expr = "status_ekyc = ? OR (status_ekyc IS NULL AND status_anggota = ?)";
        $bind = ['completed', 'aktif'];

        if ($ekycBatchIds !== []) {
            $ph = implode(',', array_fill(0, count($ekycBatchIds), '?'));
            $expr = "status_ekyc = ? OR (status_ekyc IS NULL AND (status_anggota = ? OR batch_id IN ({$ph})))";
            $bind = array_merge($bind, $ekycBatchIds);
        }

        return ["({$expr})", $bind];
    }
}
