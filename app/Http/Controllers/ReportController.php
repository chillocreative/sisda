<?php

namespace App\Http\Controllers;

use App\Exports\DataPengundi as DataPengundiExport;
use App\Exports\MulaCulaanExport;
use App\Models\DataPengundi;
use App\Models\MulaCulaan;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
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
        return view('pages.report.mula-culaan', compact('mulaCulaan'));
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
        $dataPengundi = DataPengundi::whereBetween('created_at', [$from, $to])->get();
        return view('pages.report.data-pengundi', compact('dataPengundi'));
    }

    public function exportExcelDataPengundi(Request $request){
        $from = Carbon::parse($request->from)->format('Y-m-d');
        $to = Carbon::parse($request->to)->addDay()->format('Y-m-d');
        $fileName = 'Data Pengundi dari ' . $from . ' hingga ' . $to;
        $data = new DataPengundiExport($from, $to);
        return Excel::download($data, $fileName . '.xlsx');
    }
}
