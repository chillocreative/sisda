<?php

namespace App\Http\Controllers;

use App\Models\BantuanLain;
use App\Models\JenisSumbangan;
use App\Models\Kadun;
use App\Models\KeahlianPartai;
use App\Models\KecenderunganPolitik;
use App\Models\MPKK;
use App\Models\MulaCulaan;
use App\Models\TujuanSumbangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MulaCulaanController extends Controller
{
    public function index(){
        $kadun = Kadun::all();
        $mpkk = MPKK::all();
        $jenisSumbangan = JenisSumbangan::all();
        $tujuanSumbangan = TujuanSumbangan::all();
        $bantuanLain = BantuanLain::all();
        $keahlianPartai = KeahlianPartai::all();
        $kecenderunganPolitik = KecenderunganPolitik::all();

        $data = [
            'kadun' => $kadun,
            'mpkk' => $mpkk,
            'jenisSumbangan' => $jenisSumbangan,
            'tujuanSumbangan' => $tujuanSumbangan,
            'bantuanLain' => $bantuanLain,
            'keahlianPartai' => $keahlianPartai,
            'kecenderunganPolitik' => $kecenderunganPolitik,
        ];
        return view('pages.mula-culaan', $data);
    }

    public function store(Request $request){
        $request->validate([
            'alamat' => 'required',
            'kadun' => 'required',
            'mpkk' => 'required',
            'bilangan_isi_rumah' => 'required',
            'jumlah_pendapatan_isi_rumah' => 'required',
            'jenis_sumbangan' => 'required',
            'tujuan_sumbangan' => 'required',
            'bantuan_lain' => 'required',
            'keahlian_partai' => 'required',
            'kecenderungan_politik' => 'required',
            'nota' => 'required',
            'tarikh_dan_masa' => 'required',
        ]);
        
        $request['user_id'] = Auth::user()->id;
        $request['kadun'] = Kadun::where('id', $request->kadun)->first()->name;
        $request['jenis_sumbangan'] = implode(',', $request->jenis_sumbangan);
        $request['bantuan_lain'] = implode(',', $request->bantuan_lain);
        $request['keahlian_partai'] = implode(',', $request->keahlian_partai);
        $request['kecenderungan_politik'] = implode(',', $request->kecenderungan_politik);
        MulaCulaan::create($request->all());
        return back()->with('success', 'Mula culaan berjaya disimpan');
    }
}
