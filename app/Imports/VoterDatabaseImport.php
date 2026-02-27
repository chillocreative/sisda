<?php

namespace App\Imports;

use App\Models\PangkalanDataPengundi;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Collection;

class VoterDatabaseImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected int $uploadBatchId;

    public function __construct(int $uploadBatchId)
    {
        $this->uploadBatchId = $uploadBatchId;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        $records = [];

        foreach ($rows as $row) {
            // maatwebsite/excel lowercases headers and replaces spaces with underscores
            $ic = isset($row['ic']) ? trim((string) $row['ic']) : '';

            // Zero-pad IC to 12 digits (Excel may store as number)
            if ($ic !== '') {
                $ic = str_pad($ic, 12, '0', STR_PAD_LEFT);
            }

            // Skip rows where IC is empty or not exactly 12 digits
            if (strlen($ic) !== 12 || !ctype_digit($ic)) {
                continue;
            }

            $records[] = [
                'upload_batch_id' => $this->uploadBatchId,
                'no_ic'           => $ic,
                'nama'            => strtoupper(trim((string) ($row['nama'] ?? ''))),
                'lokaliti'        => strtoupper(trim((string) ($row['namalokaliti'] ?? ''))) ?: null,
                'daerah_mengundi' => strtoupper(trim((string) ($row['namadm'] ?? ''))) ?: null,
                'kadun'           => strtoupper(trim((string) ($row['namadun'] ?? ''))) ?: null,
                'parlimen'        => strtoupper(trim((string) ($row['namaparlimen'] ?? ''))) ?: null,
                'negeri'          => strtoupper(trim((string) ($row['negeri'] ?? ''))) ?: null,
                'bangsa'          => strtoupper(trim((string) ($row['bangsa_spr'] ?? ''))) ?: null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];

            // Insert in batches of 500 to avoid memory issues
            if (count($records) >= 500) {
                PangkalanDataPengundi::insert($records);
                $records = [];
            }
        }

        if (!empty($records)) {
            PangkalanDataPengundi::insert($records);
        }
    }
}
