<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\Hubungan;
use App\Models\Kadun;
use App\Models\KeahlianPartai;
use App\Models\KecenderunganPolitik;
use App\Models\MPKK;
use App\Models\Negeri;
use App\Models\Parlimen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataPengundiController extends Controller
{
    public function index(){
        $data = [
            'draft' => DataPengundi::where('user_id', Auth::user()->id)->where('is_draft', 1)->get(),
            'negeri' => Negeri::all(),
            'keahlianPartai' => KeahlianPartai::all(),
            'kecenderunganPolitik' => KecenderunganPolitik::all(),
            'hubungan' => Hubungan::all(),
        ];
        return view('pages.data-pengundi.index', $data);
    }

    public function edit($id){
        $data_pengundi = DataPengundi::findOrFail($id);

        $data = [
            'data' => $data_pengundi,
            'negeri' => Negeri::all(),
            'keahlianPartai' => KeahlianPartai::all(),
            'kecenderunganPolitik' => KecenderunganPolitik::all(),
            'hubungan' => Hubungan::all(),
        ];
        $negeri_id = Negeri::where('name', $data_pengundi->negeri)->first()->id;
        $parlimen_id = Parlimen::where('name', $data_pengundi->parlimen)->first()->id;

        $data['bandar'] = Bandar::where('negeri_id', $negeri_id)->get();
        $data['parlimen'] = Parlimen::where('negeri_id', $negeri_id)->get();
        $data['kadun'] = Kadun::where('parlimen_id', $parlimen_id)->get();

        if(Auth::user()->role_id === 3){
            if($data_pengundi->user_id !== Auth::user()->id){
                return abort(403);
            }
        }

        return view('pages.data-pengundi.edit', $data);
    }

    public function update($id, Request $request){
        $data = DataPengundi::findOrFail($id);

        if(Auth::user()->role_id === 3){
            if($data->user_id !== Auth::user()->id){
                return abort(403);
            }
        }

        $request->validate([
            'name' => 'required',
            'no_kad' => 'required',
            'umur' => 'required',
            'phone' => 'required',
            'bangsa' => 'required',
            'alamat' => 'required',
            'poskod' => 'required',
            'negeri' => 'required',
            'bandar' => 'required',
            'parlimen' => 'required',
            'kadun' => 'required',
            'keahlian_partai' => $request->umur > 17 ? 'required' : '',
            'kecenderungan_politik' => $request->umur > 17 ? 'required' : '',
        ]);

        $negeri = Negeri::where('id', $request->negeri)->first();
        $parlimen = Parlimen::where('id', $request->parlimen)->first();
        $data->update([
            'name' => strtoupper($request->name),
            'no_kad' => $request->no_kad,
            'umur' => $request->umur,
            'phone' => $request->phone,
            'hubungan' => ($request->hubungan === 'lain' ? $request->hubungan_custom : $request->hubungan) ?? '',
            'bangsa' => $request->bangsa,
            'alamat' => strtoupper($request->alamat),
            'poskod' => $request->poskod,
            'negeri' => $negeri->name,
            'bandar' => $request->bandar,
            'parlimen' => $parlimen->name,
            'kadun' => strtoupper($request->kadun),
            'mpkk' => $request->mpkk,
            'daerah_mengundi' => $request->daerah_mengundi,
            'lokaliti' => $request->lokaliti,
            'keahlian_partai' => $request->umur > 17 ? strtoupper($request->keahlian_partai) : '',
            'kecenderungan_politik' => $request->umur > 17 ? strtoupper($request->kecenderungan_politik) : '',
            'user_id' => Auth::user()->id,
        ]);

        return back()->with('success', 'Data telah berjaya ditambah');
    }

    public function store(Request $request){
        $request->validate([
            'name' => 'required',
            'no_kad' => 'required',
            'umur' => 'required',
            'phone' => 'required',
            'bangsa' => 'required',
            'alamat' => 'required',
            'poskod' => 'required',
            'negeri' => 'required',
            'bandar' => 'required',
            'parlimen' => 'required',
            'kadun' => 'required',
            'keahlian_partai' => $request->umur > 17 ? 'required' : '',
            'kecenderungan_politik' => $request->umur > 17 ? 'required' : '',
        ]);

        $negeri = Negeri::where('id', $request->negeri)->first();
        $parlimen = Parlimen::where('id', $request->parlimen)->first();

        DataPengundi::create([
            'name' => strtoupper($request->name),
            'no_kad' => $request->no_kad,
            'umur' => $request->umur,
            'phone' => $request->phone,
            'hubungan' => ($request->hubungan === 'lain' ? $request->hubungan_custom : $request->hubungan) ?? NULL,
            'bangsa' => $request->bangsa,
            'alamat' => strtoupper($request->alamat),
            'poskod' => $request->poskod,
            'negeri' => $negeri->name,
            'bandar' => $request->bandar,
            'parlimen' => $parlimen->name,
            'kadun' => $request->kadun,
            'mpkk' => $request->mpkk,
            'daerah_mengundi' => $request->daerah_mengundi,
            'lokaliti' => $request->lokaliti,
            'keahlian_partai' => $request->umur > 17 ? strtoupper($request->keahlian_partai) : NULL,
            'kecenderungan_politik' => $request->umur > 17 ? strtoupper($request->kecenderungan_politik) : NULL,
            'user_id' => Auth::user()->id,
        ]);
        
        if($request->submit_type === 'draft'){
            return back()->with('success', 'Data pengundi berjaya ditambahkan');
        }

        $data = DataPengundi::where('user_id', Auth::user()->id)->where('is_draft', 1)->get();
        $uniq_id = uniqid();

        foreach($data as $i => $item){
            $item->update([
                'hubungan' => $i === 0 ? NULL : $item->hubungan,
                'is_draft' => false,
                'post_id' => $uniq_id,
            ]);
        }
        
        return back()->with('success', 'Data berjaya ditambahkan');
    }

    public function destroy($id){
        $data = DataPengundi::findOrFail($id);

        if($data->user_id !== Auth::user()->id){
            return back()->with('error', 'Tiada akses');  
        }

        $data->delete();
        return back()->with('success', 'Data pengundi berjaya dipadam');
    }
}
