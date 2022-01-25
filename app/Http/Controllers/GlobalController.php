<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\Kadun;
use App\Models\MPKK;
use App\Models\Parlimen;
use Illuminate\Http\Request;

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
}
