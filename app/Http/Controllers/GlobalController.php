<?php

namespace App\Http\Controllers;

use App\Models\Bandar;
use App\Models\MPKK;
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
}
