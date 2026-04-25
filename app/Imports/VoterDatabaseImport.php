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
            // maatwebsite/excel lowercases headers and strips non-alphanumerics.
            $arr = is_array($row) ? $row : $row->toArray();

            $get = function (array $aliases) use ($arr) {
                foreach ($aliases as $key) {
                    if (array_key_exists($key, $arr) && $arr[$key] !== null && $arr[$key] !== '') {
                        return $arr[$key];
                    }
                }
                return null;
            };

            $ic = trim((string) ($get(['ic', 'no_ic', 'noic']) ?? ''));

            // Zero-pad IC to 12 digits (Excel may store as number)
            if ($ic !== '') {
                $ic = str_pad($ic, 12, '0', STR_PAD_LEFT);
            }

            // Skip rows where IC is empty or not exactly 12 digits
            if (strlen($ic) !== 12 || !ctype_digit($ic)) {
                continue;
            }

            $jantinaCode = strtoupper(trim((string) ($get(['kodjantina', 'jantina']) ?? '')));
            $jantina = match ($jantinaCode) {
                'L' => 'LELAKI',
                'P' => 'PEREMPUAN',
                default => $jantinaCode ?: null,
            };

            $records[] = [
                'upload_batch_id' => $this->uploadBatchId,
                'no_ic'           => $ic,
                'nama'            => strtoupper(trim((string) ($get(['nama']) ?? ''))),
                'lokaliti'        => strtoupper(trim((string) ($get(['namalokaliti', 'lokaliti']) ?? ''))) ?: null,
                'kod_lokaliti'    => trim((string) ($get(['kodlokaliti', 'kod_lokaliti']) ?? '')) ?: null,
                'daerah_mengundi' => strtoupper(trim((string) ($get(['namadm', 'daerah_mengundi', 'daerahmengundi']) ?? ''))) ?: null,
                'kadun'           => strtoupper(trim((string) ($get(['namadun', 'kadun', 'dun']) ?? ''))) ?: null,
                'parlimen'        => strtoupper(trim((string) ($get(['namaparlimen', 'parlimen']) ?? ''))) ?: null,
                'negeri'          => strtoupper(trim((string) ($get(['namanegeri', 'negeri']) ?? ''))) ?: null,
                'bangsa'          => strtoupper(trim((string) ($get(['bangsa_spr', 'bangsa']) ?? ''))) ?: null,
                'jantina'         => $jantina,
                'tahun_lahir'     => trim((string) ($get(['tahunlahir', 'tahun_lahir']) ?? '')) ?: null,
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
