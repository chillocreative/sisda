<?php

namespace App\Services;

use App\Models\DataPengundi;
use App\Models\HasilCulaan;

class VoterSyncService
{
    /**
     * Fields that represent "about the voter" (not about a specific bantuan event)
     * and should be kept in sync across both tables, keyed on no_ic.
     */
    public const SHARED_FIELDS = [
        'nama',
        'umur',
        'no_tel',
        'bangsa',
        'alamat',
        'poskod',
        'negeri',
        'bandar',
        'parlimen',
        'kadun',
        'mpkk',
        'daerah_mengundi',
        'lokaliti',
        'keahlian_parti',
        'kecenderungan_politik',
        'status_pengundi',
        'voter_color',
        'is_deceased',
        'nota',
    ];

    /**
     * Sync shared fields from a HasilCulaan record to Data Pengundi.
     * Upserts: creates a DataPengundi row if none exists for the IC, otherwise
     * updates the existing row(s).
     */
    public static function syncFromHasilCulaan(HasilCulaan $record): void
    {
        if (empty($record->no_ic)) {
            return;
        }

        $shared = self::extract($record);

        $existing = DataPengundi::where('no_ic', $record->no_ic)->get();

        if ($existing->isEmpty()) {
            DataPengundi::create(array_merge($shared, [
                'no_ic' => $record->no_ic,
                'submitted_by' => $record->submitted_by,
            ]));
            return;
        }

        foreach ($existing as $row) {
            $row->fill($shared);
            if ($row->isDirty()) {
                $row->save();
            }
        }
    }

    /**
     * Sync shared fields from a DataPengundi record to every HasilCulaan row
     * that shares the same no_ic. Does not create new HasilCulaan rows —
     * those represent bantuan events, not voter records.
     */
    public static function syncFromDataPengundi(DataPengundi $record): void
    {
        if (empty($record->no_ic)) {
            return;
        }

        $shared = self::extract($record);

        HasilCulaan::where('no_ic', $record->no_ic)
            ->get()
            ->each(function (HasilCulaan $row) use ($shared) {
                $row->fill($shared);
                if ($row->isDirty()) {
                    $row->save();
                }
            });
    }

    /**
     * Pull the shared-field slice out of a record into a plain array.
     */
    private static function extract($record): array
    {
        $out = [];
        foreach (self::SHARED_FIELDS as $field) {
            if (array_key_exists($field, $record->getAttributes())) {
                $out[$field] = $record->{$field};
            }
        }
        return $out;
    }
}
