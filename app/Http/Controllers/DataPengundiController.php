<?php

namespace App\Http\Controllers;

use App\Models\DataPengundi;
use App\Models\Kadun;
use App\Models\KeahlianPartai;
use App\Models\KecenderunganPolitik;
use App\Models\Negeri;
use App\Models\Parlimen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataPengundiController extends Controller
{
    public function index(){
        $data = [
            'negeri' => Negeri::all(),
            'keahlianPartai' => KeahlianPartai::all(),
            'kecenderunganPolitik' => KecenderunganPolitik::all(),
        ];
        return view('pages.data-pengundi', $data);
    }

    public function store(Request $request){
        // $request->validate([
        //     'name' => 'required',
        //     'no_kad' => 'required',
        //     'umur' => 'required',
        //     'phone' => 'required',
        //     'bangsa' => 'required',
        //     'alamat' => 'required',
        //     'poskod' => 'required',
        //     'negeri' => 'required',
        //     'bandar' => 'required',
        //     'parlimen' => 'required',
        //     'kadun' => 'required',
        // ]);

        foreach($request->name as $i => $name){
            $negeri = Negeri::where('id', $request->negeri[$i])->first();
            $parlimen = Parlimen::where('id', $request->parlimen[$i])->first();
            DataPengundi::create([
                'name' => $request->name[$i],
                'no_kad' => $request->no_kad[$i],
                'umur' => $request->umur[$i],
                'phone' => $request->phone[$i],
                'bangsa' => $request->bangsa[$i],
                'alamat' => $request->alamat[$i],
                'alamat2' => $request->alamat2[$i],
                'poskod' => $request->poskod[$i],
                'negeri' => $negeri->name,
                'bandar' => $request->bandar[$i],
                'parlimen' => $parlimen->name,
                'kadun' => $request->kadun[$i],
                'keahlian_partai' => $request->keahlian_partai[$i],
                'kecenderungan_politik' => $request->kecenderungan_politik[$i],
                'user_id' => Auth::user()->id,
            ]);
        }

        return back()->with('success', 'Data pengundi berjaya ditambahkan');
    }
}
