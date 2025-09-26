<?php

namespace App\Http\Controllers;

use App\Exports\DataPengundi as DataPengundiExport;
use App\Exports\MulaCulaanExport;
use App\Models\BantuanLain;
use App\Models\DataPengundi;
use App\Models\JenisSumbangan;
use App\Models\Kadun;
use App\Models\KeahlianPartai;
use App\Models\KecenderunganPolitik;
use App\Models\MulaCulaan;
use App\Models\Negeri;
use App\Models\TujuanSumbangan;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ReportController extends Controller
{
    public function mulaCulaan(Request $request){
        if(!$request->from){
            $from = Carbon::now()->subDays(30)->format('Y-m-d');
            $to = Carbon::now()->format('Y-m-d');
            return redirect('report/mula-culaan?from=' . $from . '&to=' . $to);
        }else{
            $from = Carbon::parse($request->from)->format('Y-m-d');
            $to = Carbon::parse($request->to)->addDay()->format('Y-m-d');
        }

        $mulaCulaan = MulaCulaan::whereBetween('created_at', [$from, $to])->get();

        if(Auth::user()->role_id === 3){
            $mulaCulaan = $mulaCulaan->where('user_id', Auth::user()->id);
        }

        return view('pages.report.mula-culaan', compact('mulaCulaan'));
    }

    public function destroyMulaCulaan($id){
        $data = MulaCulaan::findOrFail($id);

        if(Auth::user()->role_id === 3){
            if($data->user_id !== Auth::user()->id){
                return back()->with('error', 'Tidak mempunyai akses');
            }
        }

        $data->delete();
        return back()->with('success', 'Mula Culaan berjaya dipadam');
    }

    public function exportExcelMulaCulaan(Request $request){
        $from = Carbon::parse($request->from)->format('Y-m-d');
        $to = Carbon::parse($request->to)->addDay()->format('Y-m-d');
        $fileName = 'Mula Culaan dari ' . $from . ' hingga ' . $to;
        $data = new MulaCulaanExport($from, $to);
        return Excel::download($data, $fileName . '.xlsx');
    }

    public function dataPengundi(Request $request){
        if(!$request->from){
            $from = Carbon::now()->subDays(30)->format('Y-m-d');
            $to = Carbon::now()->format('Y-m-d');
            return redirect('report/data-pengundi?from=' . $from . '&to=' . $to);
        }else{
            $from = Carbon::parse($request->from)->format('Y-m-d');
            $to = Carbon::parse($request->to)->addDay()->format('Y-m-d');
        }

        $dataPengundi = DataPengundi::where('is_draft', false)->whereBetween('created_at', [$from, $to])->get();
        if(Auth::user()->role_id === 3){
            $dataPengundi = $dataPengundi->where('user_id', Auth::user()->id);
        }

        return view('pages.report.data-pengundi', compact('dataPengundi'));
    }

    public function destroyDataPengundi($id){
        $data = DataPengundi::findOrFail($id);

        if(Auth::user()->role_id === 3){
            if($data->user_id !== Auth::user()->id){
                return back()->with('error', 'Tidak mempunyai akses');
            }
        }

        $data->delete();
        return back()->with('success', 'Data pengundi berjaya dipadam');
    }

    public function exportExcelDataPengundi(Request $request){
        $from = Carbon::parse($request->from)->format('Y-m-d');
        $to = Carbon::parse($request->to)->addDay()->format('Y-m-d');
        $fileName = 'Data Pengundi dari ' . $from . ' hingga ' . $to;
        $data = new DataPengundiExport($from, $to);
        return Excel::download($data, $fileName . '.xlsx');
    }
}
