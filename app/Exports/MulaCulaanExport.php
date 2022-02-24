<?php

namespace App\Exports;

use App\Models\MulaCulaan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MulaCulaanExport implements FromCollection, withHeadings
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
        return MulaCulaan::whereBetween('created_at', [$this->from, $this->to])->select('nama', 'no_kad', 'umur', 'no_telp', 'bangsa', 'alamat', 'alamat_2', 'poskod', 'negeri', 'bandar', 'kadun', 'mpkk', 'bilangan_isi_rumah', 'jumlah_pendapatan_isi_rumah', 'pekerjaan', 'pemilik_rumah', 'jenis_sumbangan', 'tujuan_sumbangan', 'bantuan_lain', 'keahlian_partai', 'kecenderungan_politik', 'nota', 'tarikh_dan_masa', 'created_at')->get();
    }

    public function headings(): array
    {
        return ['Nama', 'No Kad', 'Umur', 'Tel', 'Bangsa', 'Alamat', 'Alamat 2', 'Poskod', 'Negeri', 'Bandar', 'Kadun', 'MPKK', 'Bilangan Isi Rumah', 'Pendapatan Isi Rumah', 'Pekerjaan', 'Pemilik Rumah', 'Jenis Sumbangan', 'Tujuan Sumbangan', 'Bantuan Lain', 'Keahlian Partai', 'Kecenderungan Politik', 'Nota', 'Tarikh Dan Masa', 'Tarikh Input'];
    }
}
