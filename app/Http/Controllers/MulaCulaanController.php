<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\BantuanLain;
use App\Models\JenisSumbangan;
use App\Models\Kadun;
use App\Models\KeahlianPartai;
use App\Models\KecenderunganPolitik;
use App\Models\MPKK;
use App\Models\MulaCulaan;
use App\Models\Negeri;
use App\Models\TujuanSumbangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MulaCulaanController extends Controller
{
    public function index(){
        $negeri = Negeri::all();
        $kadun = Kadun::all();
        $jenisSumbangan = JenisSumbangan::all();
        $tujuanSumbangan = TujuanSumbangan::all();
        $bantuanLain = BantuanLain::all();
        $keahlianPartai = KeahlianPartai::all();
        $kecenderunganPolitik = KecenderunganPolitik::all();

        $data = [
            'negeri' => $negeri,
            'kadun' => $kadun,
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
            'name' => 'required',
            'no_kad' => 'required',
            'umur' => 'required',
            'no_telp' => 'required',
            'bangsa' => 'required',
            'alamat' => 'required',
            'poskod' => 'required',
            'negeri' => 'required',
            'bandar' => 'required',
            'kadun' => 'required',
            'mpkk' => 'required',
            'bilangan_isi_rumah' => 'required',
            'jumlah_pendapatan_isi_rumah' => 'required',
            'pekerjaan' => 'required',
            'pemilik_rumah' => 'required',
            'jenis_sumbangan' => 'required',
            'tujuan_sumbangan' => 'required',
            'bantuan_lain' => 'required',
            'keahlian_partai' => 'required',
            'kecenderungan_politik' => 'required',
            'tarikh_dan_masa' => 'required',
            'gambar_ic' => 'required',
        ]);

        $file = $request->file('gambar_ic');
        $fileName = time() . '.' . $file->extension();
        $file->move('ic/', $fileName);

        $request['ic'] = $fileName;
        $request['nama'] = $request->name;
        $request['user_id'] = Auth::user()->id;
        $request['negeri'] = Negeri::find($request->negeri)->name;
        $request['kadun'] = Kadun::find($request->kadun)->name;
        $request['jenis_sumbangan'] = implode(',', $request->jenis_sumbangan);
        $request['bantuan_lain'] = implode(',', $request->bantuan_lain);

        if($request->bangsa == 'lain-lain'){
            if($request->bangsa_custom){
                $request['bangsa'] = $request->bangsa_custom;
            }else{
                $request['bangsa'] = 'Lain-Lain';
            }
        }
        
        if($request->tujuan_sumbangan == 'lain-lain'){
            if($request->tujuan_sumbangan_custom){
                $request['tujuan_sumbangan'] = $request->tujuan_sumbangan_custom;
            }else{
                $request['tujuan_sumbangan'] = 'Lain-Lain';
            }
        }
        // $request['keahlian_partai'] = implode(',', $request->keahlian_partai);
        // $request['kecenderungan_politik'] = implode(',', $request->kecenderungan_politik);

        
        MulaCulaan::create($request->all());
        return back()->with('success', 'Mula culaan berjaya disimpan');
    }
}
