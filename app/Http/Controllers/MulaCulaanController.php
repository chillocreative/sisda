<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\BantuanLain;
use App\Models\DaerahMengundi;
use App\Models\JenisSumbangan;
use App\Models\Kadun;
use App\Models\KeahlianPartai;
use App\Models\KecenderunganPolitik;
use App\Models\Lokaliti;
use App\Models\MPKK;
use App\Models\MulaCulaan;
use App\Models\Negeri;
use App\Models\Parlimen;
use App\Models\TujuanSumbangan;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class MulaCulaanController extends Controller
{
    private function data(){
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

        return $data;
    }

    private $validation = [
        'name' => 'required',
        'no_kad' => 'required|numeric',
        'umur' => 'required',
        'no_telp' => 'required',
        'bangsa' => 'required',
        'alamat' => 'required',
        'poskod' => 'required',
        'negeri' => 'required',
        'bandar' => 'required',
        'parlimen' => 'required',
        'kadun' => 'required',
        'mpkk' => 'required',
        'daerah_mengundi' => 'nullable',
        'lokaliti' => 'nullable',
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
    ];

    public function index(){
        $data = $this->data();
        
        return view('pages.mula-culaan.index', $data);
    }

    public function store(Request $request){
        $request->validate($this->validation);

        $file = $request->file('gambar_ic');
        $fileName = time() . '.' . $file->extension();
        $file->move('ic/', $fileName);

        $request['ic'] = $fileName;
        $request['ic_url'] = env('APP_URL') . '/' . 'ic' . '/' . $fileName;
        $request['nama'] = $request->name;
        $request['user_id'] = Auth::user()->id;
        $request['negeri'] = Negeri::find($request->negeri)->name;
        $parlimen = Parlimen::find($request->parlimen);
        $request['parlimen'] = $parlimen ? $parlimen->name : $request->parlimen;
        $request['kadun'] = Kadun::find($request->kadun)->name;
        $request['jenis_sumbangan'] = implode(',', $request->jenis_sumbangan);
        $request['bantuan_lain'] = implode(',', $request->bantuan_lain);
        $request['pekerjaan'] = $request->pekerjaan === 'lain' ? $request->pekerjaan_custom : $request->pekerjaan;
        $request['bangsa'] = $request->bangsa === 'lain' ? $request->bangsa_custom : $request->bangsa;
        $request['tujuan_sumbangan'] = $request->tujuan_sumbangan === 'lain' ? $request->tujuan_sumbangan_custom : $request->tujuan_sumbangan;

        // $request['keahlian_partai'] = implode(',', $request->keahlian_partai);
        // $request['kecenderungan_politik'] = implode(',', $request->kecenderungan_politik);

        MulaCulaan::create($request->all());
        return back()->with('success', 'Mula culaan berjaya disimpan');
    }

    public function edit($id){
        $data = $this->data();
        $culaan = MulaCulaan::findOrFail($id);

        if(Auth::user()->role_id === 3){
            if($culaan->user_id !== Auth::user()->id){
                return back()->with('error', 'Tidak mempunyai akses');
            }
        }

        $negeri_id = Negeri::where('name', $culaan->negeri)->first()->id;
        $kadun_id = Kadun::where('name', $culaan->kadun)->first()->id;

        $data['bandar'] = Bandar::where('negeri_id', $negeri_id)->get();
        $data['parlimen'] = Parlimen::where('negeri_id', $negeri_id)->get();
        $data['mpkk'] = MPKK::where('kadun_id', $kadun_id)->get();
        $data['culaan'] = $culaan;

        return view('pages.mula-culaan.edit', $data);
    }

    public function update($id, Request $request){
        $culaan = MulaCulaan::findOrFail($id);

        if(Auth::user()->role_id === 3){
            if($culaan->user_id !== Auth::user()->id){
                return abort(403);
            }
        }

        if($request->gambar_ic){
            $file = $request->file('gambar_ic');
            $fileName = time() . '.' . $file->extension();
            $file->move('ic/', $fileName);
    
            $request['ic'] = $fileName;
            $request['ic_url'] = env('APP_URL') . '/' . 'ic' . '/' . $fileName;
        }else{
            $request['ic'] = $culaan->ic;
        }
        
        $request['nama'] = $request->name;
        $request['negeri'] = Negeri::find($request->negeri)->name;
        $parlimen = Parlimen::find($request->parlimen);
        $request['parlimen'] = $parlimen ? $parlimen->name : $request->parlimen;
        $request['kadun'] = Kadun::find($request->kadun)->name;
        $request['jenis_sumbangan'] = implode(',', $request->jenis_sumbangan);
        $request['bantuan_lain'] = implode(',', $request->bantuan_lain);
        $request['pekerjaan'] = $request->pekerjaan === 'lain' ? $request->pekerjaan_custom : $request->pekerjaan;
        $request['bangsa'] = $request->bangsa === 'lain' ? $request->bangsa_custom : $request->bangsa;
        $request['tujuan_sumbangan'] = $request->tujuan_sumbangan === 'lain' ? $request->tujuan_sumbangan_custom : $request->tujuan_sumbangan;

        $request->validate(Arr::except($this->validation, ['gambar_ic']));
        $culaan->update($request->all());
        return back()->with('success', 'Mula culaan berjaya diupdate');
    }
}
