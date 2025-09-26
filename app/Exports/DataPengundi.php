<?php

namespace App\Exports;

use App\Models\DataPengundi as AppDataPengundi;
use Illuminate\Support\Facades\Auth;
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
        $data = AppDataPengundi::where('is_draft', false)->whereBetween('created_at', [$this->from, $this->to])->select('name', 'no_kad', 'umur', 'phone', 'bangsa', 'hubungan', 'alamat', 'poskod', 'negeri', 'bandar', 'parlimen', 'kadun', 'keahlian_partai', 'kecenderungan_politik', 'created_at');

        if(Auth::user()->role_id === 3){
            $data = $data->where('user_id', Auth::user()->id);
        }

        return $data->get();
    }

    public function headings(): array
    {
        return ['Nama', 'No Kad', 'Umur', 'Tel', 'Bangsa', 'Hubungan', 'Alamat', 'Poskod', 'Negeri', 'Bandar', 'Parlimen', 'Kadun', 'Keahlian Parti', 'Kecenderungan Politik', 'Tarikh Input'];
    }
}
