<?php

namespace App\Exports;

use App\Models\DataPengundi;
use App\Models\User;
use App\Services\VoterDataMasker;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DataPengundiExport implements FromQuery, WithHeadings, WithMapping
{
    protected $query;
    protected ?User $viewer;

    public function __construct($query, ?User $viewer = null)
    {
        $this->query = $query;
        $this->viewer = $viewer;
    }

    public function query()
    {
        return $this->query->with('submittedBy');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama',
            'No. IC',
            'Umur',
            'No. Tel',
            'Bangsa',
            'Hubungan',
            'Alamat',
            'Poskod',
            'Negeri',
            'Bandar',
            'Parlimen',
            'KADUN',
            'Keahlian Parti',
            'Kecenderungan Politik',
            'Tarikh Dicipta',
            'Dikemukakan Oleh',
        ];
    }

    private static function safe(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
            return "\t" . $v;
        }
        return $v;
    }

    public function map($dataPengundi): array
    {
        $d = VoterDataMasker::mask($dataPengundi, $this->viewer);
        return [
            $dataPengundi->id,
            self::safe($d['nama'] ?? null),
            $d['no_ic'] ?? null,
            $d['umur'] ?? null,
            $d['no_tel'] ?? null,
            self::safe($d['bangsa'] ?? null),
            $dataPengundi->hubungan,
            self::safe($d['alamat'] ?? null),
            $d['poskod'] ?? null,
            $d['negeri'] ?? null,
            $d['bandar'] ?? null,
            $dataPengundi->parlimen,
            $dataPengundi->kadun,
            $dataPengundi->keahlian_parti,
            $dataPengundi->kecenderungan_politik,
            $dataPengundi->created_at->format('d/m/Y H:i'),
            $dataPengundi->submittedBy->name ?? '-',
        ];
    }
}
