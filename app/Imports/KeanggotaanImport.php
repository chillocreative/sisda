<?php

namespace App\Imports;

use App\Models\Keanggotaan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports a membership spreadsheet (one member per row) into the
 * keanggotaan table. Only the raw identity fields are inserted here —
 * the SISDA match (kawasan / DUN / age / voter_color) is computed in one
 * set-based pass afterwards by MemberMatchService::syncTable().
 */
class KeanggotaanImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    public function __construct(protected int $batchId) {}

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        $records = [];

        foreach ($rows as $row) {
            $arr = is_array($row) ? $row : $row->toArray();

            $get = function (array $aliases) use ($arr) {
                foreach ($aliases as $key) {
                    if (array_key_exists($key, $arr) && $arr[$key] !== null && $arr[$key] !== '') {
                        return $arr[$key];
                    }
                }

                return null;
            };

            $ic = trim((string) ($get(['ic', 'no_ic', 'noic', 'kadpengenalan', 'nokp']) ?? ''));
            if ($ic !== '') {
                $ic = str_pad($ic, 12, '0', STR_PAD_LEFT);
            }
            if (strlen($ic) !== 12 || ! ctype_digit($ic)) {
                continue;
            }

            $records[] = [
                'batch_id' => $this->batchId,
                'no_ic' => $ic,
                'nama' => strtoupper(trim((string) ($get(['nama', 'name']) ?? ''))),
                'no_tel' => trim((string) ($get(['notel', 'no_tel', 'telefon', 'phone']) ?? '')) ?: null,
                'status_kawasan' => 'luar_kawasan',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($records) >= 500) {
                Keanggotaan::insert($records);
                $records = [];
            }
        }

        if (! empty($records)) {
            Keanggotaan::insert($records);
        }
    }
}
