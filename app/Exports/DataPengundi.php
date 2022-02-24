<?php

namespace App\Exports;

use App\Models\DataPengundi as AppDataPengundi;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DataPengundi implements FromCollection, WithHeadings
{
    private $from;
    private $to;

    public function __construct($from, $to){
        $this->from = $from;
        $this->to = $to;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return AppDataPengundi::whereBetween('created_at', [$this->from, $this->to])->select('name', 'no_kad', 'umur', 'phone', 'bangsa', 'hubungan', 'alamat', 'alamat2', 'poskod', 'negeri', 'bandar', 'parlimen', 'kadun', 'keahlian_partai', 'kecenderungan_politik', 'created_at')->get();
    }

    public function headings(): array
    {
        return ['Nama', 'No Kad', 'Umur', 'Tel', 'Bangsa', 'Hubungan', 'Alamat', 'Alamat 2', 'Poskod', 'Negeri', 'Bandar', 'Parlimen', 'Kadun', 'Keahlian Parti', 'Kecenderungan Politik', 'Tarikh Input'];
    }
}
