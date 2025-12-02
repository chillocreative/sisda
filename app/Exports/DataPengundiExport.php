<?php

namespace App\Exports;

use App\Models\DataPengundi;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DataPengundiExport implements FromQuery, WithHeadings, WithMapping
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
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

    public function map($dataPengundi): array
    {
        return [
            $dataPengundi->id,
            $dataPengundi->nama,
            $dataPengundi->no_ic,
            $dataPengundi->umur,
            $dataPengundi->no_tel,
            $dataPengundi->bangsa,
            $dataPengundi->hubungan,
            $dataPengundi->alamat,
            $dataPengundi->poskod,
            $dataPengundi->negeri,
            $dataPengundi->bandar,
            $dataPengundi->parlimen,
            $dataPengundi->kadun,
            $dataPengundi->keahlian_parti,
            $dataPengundi->kecenderungan_politik,
            $dataPengundi->created_at->format('d/m/Y H:i'),
            $dataPengundi->submittedBy->name ?? '-',
        ];
    }
}
