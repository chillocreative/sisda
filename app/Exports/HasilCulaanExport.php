<?php

namespace App\Exports;

use App\Models\HasilCulaan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HasilCulaanExport implements FromCollection, WithHeadings, WithMapping
{
    protected $query;

    public function __construct($query = null)
    {
        $this->query = $query;
    }

    public function collection()
    {
        if ($this->query) {
            return $this->query->with('submittedBy')->get();
        }
        return HasilCulaan::with('submittedBy')->get();
    }

    public function headings(): array
    {
        return [
            'Nama',
            'No. IC',
            'Umur',
            'No. Tel',
            'Bangsa',
            'Alamat',
            'Poskod',
            'Negeri',
            'Bandar',
            'KADUN',
            'Bil. Isi Rumah',
            'Pendapatan Isi Rumah',
            'Kategori Pekerjaan',
            'Pemilik Rumah',
            'Jenis Sumbangan',
            'Tujuan Sumbangan',
            'Bantuan Lain',
            'Keahlian Parti',
            'Kecenderungan Politik',
            'Kad Pengenalan',
            'Nota',
            'Tarikh & Masa',
            'Tahun',
            'Bulan',
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

    public function map($hasilCulaan): array
    {
        return [
            self::safe($hasilCulaan->nama),
            $hasilCulaan->no_ic,
            $hasilCulaan->umur,
            $hasilCulaan->no_tel,
            self::safe($hasilCulaan->bangsa),
            self::safe($hasilCulaan->alamat),
            $hasilCulaan->poskod,
            $hasilCulaan->negeri,
            $hasilCulaan->bandar,
            $hasilCulaan->kadun,
            $hasilCulaan->bil_isi_rumah,
            $hasilCulaan->pendapatan_isi_rumah,
            $hasilCulaan->pekerjaan,
            $hasilCulaan->pemilik_rumah,
            $hasilCulaan->jenis_sumbangan,
            $hasilCulaan->tujuan_sumbangan,
            $hasilCulaan->bantuan_lain,
            $hasilCulaan->keahlian_parti,
            $hasilCulaan->kecenderungan_politik,
            $hasilCulaan->kad_pengenalan,
            $hasilCulaan->nota,
            $hasilCulaan->created_at->format('d/m/Y H:i:s'),
            $hasilCulaan->created_at->format('Y'),
            $hasilCulaan->created_at->format('m'),
            $hasilCulaan->submittedBy->name ?? '-',
        ];
    }
}
