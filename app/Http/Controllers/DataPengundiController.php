<?php

namespace App\Http\Controllers;

use App\Models\Kadun;
use App\Models\KeahlianPartai;
use App\Models\KecenderunganPolitik;
use App\Models\Negeri;
use Illuminate\Http\Request;

class DataPengundiController extends Controller
{
    public function index(){
        $data = [
            'negeri' => Negeri::all(),
            'kadun' => Kadun::all(),
            'keahlianPartai' => KeahlianPartai::all(),
            'kecenderunganPolitik' => KecenderunganPolitik::all(),
        ];
        return view('pages.data-pengundi', $data);
    }
}
