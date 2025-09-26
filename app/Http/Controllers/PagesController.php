<?php

namespace App\Http\Controllers;

use App\Models\BantuanLain;
use App\Models\JenisPekerjaan;
use App\Models\JenisSumbangan;
use App\Models\KeahlianPartai;
use App\Models\KecenderunganPolitik;
use App\Models\MPKK;
use App\Models\MulaCulaan;
use App\Models\Role;
use App\Models\TujuanSumbangan;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use stdClass;

class PagesController extends Controller
{
    public function dashboard(Request $request){    
        if(Auth::user()->role_id === 3){
            return view('pages.dashboard.user');
        }

        function getDataMontly($modelName, $year){
            $temp = [];
            $montly = [];

            $model = $modelName->select('id', 'created_at')->where(DB::raw('YEAR(created_at)'), $year)->get()->groupBy(function($date){
                return Carbon::parse($date->created_at)->format('m');
            });

            
            foreach ($model as $key => $value) {
                $temp[(int)$key] = count($value);
            }

            for($i = 0; $i < 12; $i++){
                if(!empty($temp[$i])){
                    $montly[$i] = $temp[$i];    
                }else{
                    $montly[$i] = 0;    
                }
            }
            
            return $montly;
        }

        function label($modelName){
            $label = [];
            foreach($modelName as $k){
                $label[] = $k->name;
            }

            return $label;
        }

        function getData($parentModel, $modelName, $column){
            $temp = [];
            $dataCount = [];

            foreach ($parentModel as $key => $value) {
                $temp[$key] = $value->$column;
            }

            foreach($modelName as $i => $k){
                $count = 1;
                $dataCount[] = 0;
                for($j = 0; $j < count($temp); $j++){
                    if($k->name == $temp[$j]){
                        $dataCount[$i] = $count++;
                    }
                }
            }

            return $dataCount;
        }

        function getMultipleData($parentModel, $modelName, $column){
            $temp = [];
            $expArr = [];
            $exp = [];
            $dataCount = [];
            
            foreach ($parentModel as $key => $value) {
                $temp[$key] = $value->$column;
            }

            for($j = 0; $j < count($temp); $j++){
                $expArr[] = explode(',', $temp[$j]);
            }

            for($i = 0; $i < count($expArr); $i++){
                for($j = 0; $j < count($expArr[$i]); $j++){
                    $exp[] = $expArr[$i][$j];
                }
            }

            foreach($modelName as $i => $k){
                $count = 1;
                $nameTemp = $k->name;
                $dataCount[] = 0;
                $expArr = [];
                for($j = 0; $j < count($exp); $j++){
                    if($nameTemp == $exp[$j]){
                        $dataCount[$i] = $count++;
                    }
                }
            }

            return $dataCount;
        }

        $thisYear = Carbon::now()->format('Y');
        $roles = Role::all();
        $mpkk = MPKK::all();
        $keahlianParti = KeahlianPartai::get();
        $kecenderunganPolitik = KecenderunganPolitik::get();
        $jenisSumbangan = JenisSumbangan::get();
        $tujuanSumbangan = TujuanSumbangan::get();
        $bantuanLain = BantuanLain::get();
        $jenisPekerjaan = JenisPekerjaan::get();
        $jumlahKeahlianParti = KeahlianPartai::get();
        $bangsa = ['Melayu', 'India', 'Cina', 'Bumiputra', 'Lain Lain'];

        if($request->mpkk){
            $culaan = MulaCulaan::where('mpkk', $request->mpkk);
        }else{
            $culaan = MulaCulaan::orderBy('created_at', 'ASC');
        }

        if($culaan->count() > 0){
            //Umur
            for($i = 0; $i < 17; $i++){
                $umurTemp[$i] = (5 * $i) + 18;
                $umurLabel[$i] = ((5 * $i) + 18) . ' - ' . ((5 * $i) + 18 + 5 - 1);
            }
            unset($umurLabel[count($umurLabel)-1]);
    
            for($i = 0; $i < count($umurTemp) - 1; $i++){
                $count = 1;
                $umurData[$i] = 0;
                foreach($culaan->get() as $c){
                    if($c->umur >= $umurTemp[$i] && $c->umur < (5 * $i) + 18 + 5){
                        $umurData[$i] = $count++;
                    }
                }
            }
    
            //Jumlah Pendapatan
            for($i = 0; $i < 20; $i++){
                $jumlahPendapatanTemp[$i] = (500 * $i) + 500;
                $jumlahPendapatanLabel[$i] = ((500 * $i) + 1) . ' - ' . ((500 * $i) + 500);
            }
            
            for($i = 0; $i < count($jumlahPendapatanTemp); $i++){
                $count = 1;
                $jumlahPendapatanData[$i] = 0;
                foreach($culaan->get() as $c){
                    if($c->jumlah_pendapatan_isi_rumah >= $jumlahPendapatanTemp[$i] - 500 + 1 && $c->jumlah_pendapatan_isi_rumah <= $jumlahPendapatanTemp[$i]){
                        $jumlahPendapatanData[$i] = $count++;
                    }
                }
            }
    
            //Keahlian Parti Mengikuti Bangsa
            foreach($culaan->get() as $i => $c){
                $keahlianBangsaTemp[$i] = $c->bangsa;
                $keahlianPartiBangsaTemp[$i] = $c->keahlian_partai;
            }
    
            for($i = 0; $i < count($keahlianPartiBangsaTemp); $i++){
                $keahlianPartiBangsaExpArr[$i] = explode(',', $keahlianPartiBangsaTemp[$i]);
            }
    
            for($i = 0; $i < count($keahlianPartiBangsaExpArr); $i++){
                for($j = 0; $j < count($keahlianPartiBangsaExpArr[$i]); $j++){
                    $keahlianPartiBangsaExp[] = $keahlianPartiBangsaExpArr[$i][$j];
                    $keahlianBangsaExp[] = $keahlianBangsaTemp[$i];
                }
            }
    
            for($i = 0; $i < count($bangsa); $i++){
                $keahlianPartiBangsaData[] = array();
                foreach($keahlianParti as $j => $parti){
                    $keahlianPartiBangsaData[$i][$j] = 0;
                    $count = 1;
                    for($k = 0; $k < count($keahlianPartiBangsaExp); $k++ ){
                        if($parti->name == $keahlianPartiBangsaExp[$k] && $keahlianBangsaExp[$k] == $bangsa[$i]){
                            $keahlianPartiBangsaData[$i][$j] = $count++;
                        }
                    }
                }
            }
    
            $data = [
                'roles' => $roles,
                'mpkk' => $mpkk,
                'culaan' => $culaan,
                'keahlianPartiLabel' => label($keahlianParti),
                'keahlianPartiData' =>  getMultipleData($culaan->get(), $keahlianParti, 'keahlian_partai'),
                'kecenderunganPolitikLabel' => label($kecenderunganPolitik),
                'kecenderunganPolitikData' => getData($culaan->get(), $kecenderunganPolitik, 'kecenderungan_politik'),
                'jenisSumbanganLabel' => label($jenisSumbangan),
                'jenisSumbanganData' => getMultipleData($culaan->get(), $jenisSumbangan, 'jenis_sumbangan'),
                'bantuanLainLabel' => label($bantuanLain),
                'bantuanLainData' => getMultipleData($culaan->get(), $bantuanLain, 'bantuan_lain'),
                'tujuanSumbanganLabel' => label($tujuanSumbangan),
                'tujuanSumbanganData' => getData($culaan->get(), $tujuanSumbangan, 'tujuan_sumbangan'),
                'jenisPekerjaanLabel' => label($jenisPekerjaan),
                'jenisPekerjaanData' => getData($culaan->get(), $jenisPekerjaan, 'pekerjaan'),
                'umurLabel' => $umurLabel,
                'umurData' => $umurData,
                'jumlahPendapatanLabel' => $jumlahPendapatanLabel,
                'jumlahPendapatanData' => $jumlahPendapatanData,
                'jumlahKeahlianPartiLabel' => label($jumlahKeahlianParti),
                'bangsaLabel' => $bangsa,
                'keahlianPartiBangsaData' => $keahlianPartiBangsaData,
                'culaanMontly' => getDataMontly($culaan, $thisYear),
            ];
            
            return view('pages.dashboard.admin', $data);
        }

        $data = [
            'roles' => $roles,
            'mpkk' => $mpkk,
            'culaan' => $culaan,
        ];
        return view('pages.dashboard.admin', $data);
    }

    public function user(){
        if(Route::is('user-superadmin')){
            $role = Role::where('name', 'superadmin')->first();
            $title = 'Superadmin';
        }elseif(Route::is('user-admin')){
            $role = Role::where('name', 'admin')->first();
            $title = 'Admin';
        }elseif(Route::is('user-user')){
            $role = Role::where('name', 'user')->first();
            $title = 'User';
        }

        $users = User::where('role_id', $role->id)->orderBy('updated_at', 'DESC')->get();

        return view('pages.user.index', compact('role', 'title', 'users'));
    }
}