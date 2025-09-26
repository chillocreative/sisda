<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\DataPengundi;
use App\Models\Kadun;
use App\Models\MPKK;
use App\Models\MulaCulaan;
use App\Models\Parlimen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GlobalController extends Controller
{
    public function getMPKKSpecific(Request $request){
        $mpkk = MPKK::where('kadun_id', $request->id)->get();
        return response()->json($mpkk);
    }

    public function getBandarSpecific(Request $request){
        $bandar = Bandar::where('negeri_id', $request->id)->get();
        return response()->json($bandar);
    }

    public function getParlimenSpecific(Request $request){
        $parlimen = Parlimen::where('negeri_id', $request->id)->get();
        return response()->json($parlimen);
    }

    public function getKadunSpecific(Request $request){
        $kadun = Kadun::where('parlimen_id', $request->id)->get();
        return response()->json($kadun);
    }

    public function dataPengundi(Request $request){
        if($request->no_kad){
            if(Auth::user()->role_id === 3){
                $data = DataPengundi::where('user_id', Auth::user()->id)->where('no_kad', 'like', '%' . $request->no_kad . '%')->get();
            }else{
                $data = DataPengundi::where('no_kad', 'like', '%' . $request->no_kad . '%')->get()->load('user');
            }

            return response()->json($data);
        }
        
        return abort(404);
    }

    public function mulaCulaan(Request $request){
        if($request->no_kad){
            if(Auth::user()->role_id === 3){
                $data = MulaCulaan::where('user_id', Auth::user()->id)->where('no_kad', 'like', '%' . $request->no_kad . '%')->get();
            }else{
                $data = MulaCulaan::where('no_kad', 'like', '%' . $request->no_kad . '%')->get()->load('user');
            }
        
            return response()->json($data);
        }

        return abort(404);
    }
}
