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
 *
 * Header matching is format-agnostic: every heading is reduced to
 * lowercase alphanumerics, so "No. K/P", "NO_IC", "ic" all resolve.
 */
class KeanggotaanImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    private const IC_KEYS = ['ic', 'noic', 'nokp', 'kp', 'kadpengenalan', 'nokadpengenalan', 'nomborkadpengenalan', 'mykad', 'icnumber'];

    private const NAMA_KEYS = ['nama', 'name', 'namapenuh', 'namaahli'];

    private const TEL_KEYS = ['notel', 'notelefon', 'telefon', 'tel', 'phone', 'nohp', 'hp', 'nombortelefon'];

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

            // Re-key every cell by its alphanumeric-only, lowercased header
            // so any header style matches our alias lists.
            $norm = [];
            foreach ($arr as $key => $val) {
                $norm[preg_replace('/[^a-z0-9]/', '', strtolower((string) $key))] = $val;
            }

            $get = function (array $aliases) use ($norm) {
                foreach ($aliases as $key) {
                    if (isset($norm[$key]) && $norm[$key] !== null && $norm[$key] !== '') {
                        return $norm[$key];
                    }
                }

                return null;
            };

            $ic = preg_replace('/\D/', '', (string) ($get(self::IC_KEYS) ?? ''));
            if ($ic !== '' && strlen($ic) < 12) {
                $ic = str_pad($ic, 12, '0', STR_PAD_LEFT);
            }
            if (strlen($ic) !== 12) {
                continue;
            }

            $records[] = [
                'batch_id' => $this->batchId,
                'no_ic' => $ic,
                'nama' => strtoupper(trim((string) ($get(self::NAMA_KEYS) ?? ''))) ?: '-',
                'no_tel' => trim((string) ($get(self::TEL_KEYS) ?? '')) ?: null,
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
